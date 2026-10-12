<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BkashController extends Controller
{
    /**
     * The admin has checked the money in the bKash app: record it as a real payment.
     * It then shows on the invoice (Paid / Due) and in Payments like any other payment.
     */
    public function verify(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $back  = redirect()->route('admin.orders.show', $order->id);

        if (!$order->isBkash()) {
            return $back->withErrors(['amount' => 'This order was not placed with bKash.']);
        }
        if ($order->isLocked()) {
            return $back->withErrors(['amount' => 'This order is ' . strtolower($order->statusLabel()) . ', so a payment cannot be verified.']);
        }
        if ($order->bkashVerified()) {
            return $back->with('success', 'This bKash payment is already verified.');
        }

        $request->merge(['amount' => preg_replace('/\D/', '', (string) $request->input('amount'))]);
        $data = $request->validate(
            ['amount' => 'required|integer|min:1|max:10000000'],
            ['amount.required' => 'Please enter the amount you received.', 'amount.integer' => 'Please enter the amount in numbers only.', 'amount.min' => 'Please enter the amount you received.']
        );
        $amount = (int) $data['amount'];

        DB::transaction(function () use ($order, $amount) {
            // lock the order so a double click cannot add the payment twice
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($fresh->bkashVerified()) {
                return;
            }

            $row = [
                'order_id' => $fresh->id,
                'type'     => 'payment',
                'method'   => $this->methodKey(),
                'amount'   => $amount,
                'trx_id'   => $fresh->bkash_trx_id,
                'note'     => 'bKash payment from checkout, verified by admin',
                'paid_at'  => now(),
            ];

            // only fill the columns the payments table really has
            $columns = array_flip(Schema::getColumnListing('payments'));
            $payment = new Payment();
            $payment->forceFill(array_intersect_key($row, $columns))->save();

            $fresh->update(['bkash_payment_id' => $payment->id]);
        });

        return $back->with('success', 'bKash payment verified: ৳ ' . number_format($amount) . ' added to the order and invoice.');
    }

    /** The key the Payments section uses for bKash (falls back to "bkash") */
    private function methodKey(): string
    {
        if (method_exists(Payment::class, 'methods')) {
            foreach (array_keys(Payment::methods()) as $key) {
                if (stripos((string) $key, 'bkash') !== false) {
                    return (string) $key;
                }
            }
        }

        return 'bkash';
    }
}
