<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ServiceRequest;
use App\Support\Phone;
use Illuminate\Http\Request;

/**
 * Public "track your order" page: the customer gives the mobile number used for the order and sees the
 * latest orders of that number. Only order facts are shown (status, items, amounts, courier). The name,
 * address and phone number of the order are never shown on this page.
 */
class TrackController extends Controller
{
    /** How many of the newest orders are listed */
    const MAX_ORDERS = 20;

    public function show()
    {
        return view('pages.track', ['searched' => false, 'orders' => collect(), 'claims' => collect(), 'phone' => '', 'error' => null]);
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:30',
        ], [
            'phone.required' => 'ফোন নম্বর লিখুন।',
        ]);

        $phone = Phone::normalize($data['phone']);
        if ($phone === null) {
            return $this->result($data['phone'], collect(), collect(), 'ফোন নম্বরটি সঠিক নয়। সঠিক বাংলাদেশি মোবাইল নম্বর দিন (যেমন 01712345678 বা ০১৭১২৩৪৫৬৭৮)।');
        }

        $orders = Order::withPaid()->with('items')
            ->whereIn('phone', Phone::variants($phone))
            ->orderByDesc('id')
            ->limit(self::MAX_ORDERS)
            ->get();

        if ($orders->isEmpty()) {
            return $this->result($phone, collect(), collect(), 'এই ফোন নম্বরের সাথে কোনো অর্ডার খুঁজে পাওয়া যায়নি। অর্ডারের সময় যে নম্বর দিয়েছিলেন সেটি দিন।');
        }

        $claims = ServiceRequest::tableReady()
            ? ServiceRequest::whereIn('order_id', $orders->pluck('id'))->orderByDesc('id')->get()->groupBy('order_id')
            : collect();

        return $this->result($phone, $orders, $claims, null);
    }

    private function result(string $phone, $orders, $claims, ?string $error)
    {
        return view('pages.track', [
            'searched' => true,
            'orders'   => $orders,
            'claims'   => $claims,
            'phone'    => $phone,
            'error'    => $error,
        ]);
    }
}
