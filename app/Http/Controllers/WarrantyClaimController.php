<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ServiceRequest;
use App\Services\ClaimService;
use App\Services\WarrantyService;
use App\Support\Notify;
use App\Support\Phone;
use App\Support\WarrantyPolicy;
use Illuminate\Http\Request;

/**
 * Public warranty claim form.
 *   1. The customer proves the order with the mobile number + order number.
 *   2. They choose the product, describe the problem and may add photos / a video.
 *   3. A normal warranty claim is opened (status "Open") exactly like staff do it, so the shop team
 *      accepts or rejects it in Admin > Claims. Only products that are inside their warranty can be sent.
 */
class WarrantyClaimController extends Controller
{
    const CUSTOMER = 'Customer (website)';

    private WarrantyService $warranty;
    private ClaimService $claims;

    public function __construct(WarrantyService $warranty, ClaimService $claims)
    {
        $this->warranty = $warranty;
        $this->claims   = $claims;
    }

    private function ready(): bool
    {
        return ServiceRequest::tableReady() && WarrantyPolicy::columnsReady();
    }

    public function form(Request $request)
    {
        if ($request->boolean('change')) {
            $request->session()->forget(['wc_order_id', 'wc_phone']);
        }

        $order = $this->verifiedOrder($request);
        $lines = $order ? $this->lines($order) : [];

        return view('pages.warranty-claim', [
            'ready' => $this->ready(),
            'order' => $order,
            'lines' => $lines,
            'done'  => session('claim_done'),
        ]);
    }

    /** Step 1: check the mobile number and order number */
    public function find(Request $request)
    {
        $data = $request->validate([
            'phone'    => 'required|string|max:30',
            'order_no' => 'required|string|max:20',
        ], [
            'phone.required'    => 'ফোন নম্বর লিখুন।',
            'order_no.required' => 'অর্ডার নম্বর লিখুন।',
        ]);

        $phone = Phone::normalize($data['phone']);
        $no    = (int) preg_replace('/\D+/', '', Phone::latinDigits($data['order_no']));
        $order = ($phone !== null && $no > 0)
            ? Order::where('id', $no)->whereIn('phone', Phone::variants($phone))->first()
            : null;

        if (!$order) {
            return back()->withInput()->with('error', 'এই ফোন নম্বর ও অর্ডার নম্বরের সাথে মিল পাওয়া যায়নি। দুটোই ঠিক আছে কিনা দেখে আবার চেষ্টা করুন।');
        }
        if ($order->status !== 'delivered') {
            return back()->withInput()->with('error', 'ওয়ারেন্টি ক্লেইম শুধু ডেলিভারি হয়ে যাওয়া অর্ডারে করা যায়। এই অর্ডারটির অবস্থা এখন: ' . $order->statusLabel() . '।');
        }

        $request->session()->put('wc_order_id', $order->id);
        $request->session()->put('wc_phone', $phone);

        return redirect()->route('claim.form');
    }

    /** Step 2: open the claim */
    public function store(Request $request)
    {
        $order = $this->verifiedOrder($request);
        if (!$order) {
            return redirect()->route('claim.form')->with('error', 'সময় শেষ হয়ে গেছে। আবার ফোন নম্বর ও অর্ডার নম্বর দিন।');
        }
        if (!$this->ready()) {
            return back()->with('error', 'এই মুহূর্তে অনলাইনে ক্লেইম নেওয়া যাচ্ছে না। ফোনে যোগাযোগ করুন।');
        }

        $data = $request->validate([
            'order_item_id' => 'required|integer',
            'quantity'      => 'required|integer|min:1|max:999',
            'reason'        => 'required|string|max:1000',
            'serial_number' => 'nullable|string|max:100',
            'files'         => 'nullable|array',
            'files.*'       => 'nullable|file',
        ], [
            'order_item_id.required' => 'যে পণ্যে সমস্যা, সেটা বেছে নিন।',
            'quantity.required'      => 'কয়টি পণ্যে সমস্যা, সেটা লিখুন।',
            'reason.required'        => 'সমস্যার বিবরণ লিখুন।',
        ]);

        $files  = (array) $request->file('files', []);
        $errors = $this->claims->checkFiles($files);
        if ($errors) {
            return back()->withInput()->with('error', implode(' ', $errors));
        }

        try {
            $claim = $this->warranty->open($order, [
                'order_item_id'     => $data['order_item_id'],
                'quantity'          => $data['quantity'],
                'reason'            => $data['reason'],
                'serial_number'     => $data['serial_number'] ?? null,
                'record_ineligible' => false,     // a claim outside the warranty is not recorded from the website
                'opened_by_name'    => self::CUSTOMER,
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($files) {
            $this->claims->addEvidence($claim, $files, 'Sent by the customer on the website');
        }
        Notify::newWarrantyClaim($claim);

        $request->session()->forget(['wc_order_id', 'wc_phone']);

        return redirect()->route('claim.form')->with('claim_done', $claim->id);
    }

    // ------------------------------------------------------------------

    private function verifiedOrder(Request $request): ?Order
    {
        $id    = (int) $request->session()->get('wc_order_id');
        $phone = (string) $request->session()->get('wc_phone');
        if ($id < 1 || $phone === '') {
            return null;
        }

        $order = Order::with('items')->where('id', $id)->whereIn('phone', Phone::variants($phone))->first();

        return ($order && $order->status === 'delivered') ? $order : null;
    }

    /** The products of the order with what the customer may do with each of them */
    private function lines(Order $order): array
    {
        $out = [];
        foreach ($order->items as $line) {
            $left = $this->ready() ? $this->warranty->piecesLeft($order->id, $line) : 0;
            $a    = null;
            try {
                $a = $this->ready() ? $this->warranty->assess($order, $line) : null;
            } catch (\Throwable $e) {
                $a = null;
            }

            $state = $a['state'] ?? 'unknown';
            $note  = [
                'in_warranty' => 'ওয়ারেন্টি আছে — মেয়াদ ' . optional($a['expires'] ?? null)->format('d M Y') . ' পর্যন্ত',
                'expired'     => 'ওয়ারেন্টির মেয়াদ শেষ হয়েছে (' . optional($a['expires'] ?? null)->format('d M Y') . ')',
                'not_covered' => 'এই পণ্যে ওয়ারেন্টি নেই',
                'unknown'     => 'ডেলিভারির তারিখ রেকর্ড নেই — ফোনে যোগাযোগ করুন',
            ][$state] ?? 'ফোনে যোগাযোগ করুন';

            if ($state === 'in_warranty' && $left < 1) {
                $note .= ' (এর জন্য আগেই ক্লেইম করা আছে)';
            }

            $out[] = [
                'line'      => $line,
                'note'      => $note,
                'left'      => $left,
                'claimable' => $state === 'in_warranty' && $left > 0,
            ];
        }

        return $out;
    }
}
