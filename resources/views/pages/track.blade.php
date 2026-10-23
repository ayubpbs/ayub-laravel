@extends('layouts.app')

@section('title', 'অর্ডার ট্র্যাক করুন — ' . config('shop.name'))

@section('content')
@php
  $bn = function ($n) { return strtr(number_format((int) $n), '0123456789', '০১২৩৪৫৬৭৮৯'); };
  $labels = [
    'pending'      => 'অর্ডার গ্রহণ করা হয়েছে',
    'confirmed'    => 'কনফার্ম করা হয়েছে',
    'courier_sent' => 'কুরিয়ারে পাঠানো হয়েছে',
    'delivered'    => 'ডেলিভারি সম্পন্ন',
    'cancelled'    => 'অর্ডার বাতিল হয়েছে',
    'returned'     => 'অর্ডার ফেরত এসেছে',
  ];
  $flow = ['pending' => 'অর্ডার', 'confirmed' => 'কনফার্ম', 'courier_sent' => 'কুরিয়ারে', 'delivered' => 'ডেলিভারি'];
@endphp
<section class="pg">
  <h1>অর্ডার ট্র্যাক করুন</h1>

  <div class="pg-card pg-narrow" style="margin-bottom:20px">
    <p>অর্ডার করার সময় যে মোবাইল নম্বর দিয়েছিলেন, সেটি লিখুন।</p>

    @if ($error)<div class="pg-alert err">{{ $error }}</div>@endif
    @if ($errors->any())<div class="pg-alert err">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('track.lookup') }}" class="pg-form">
      @csrf
      <div>
        <label for="t-phone">ফোন নম্বর *</label>
        <input type="tel" id="t-phone" name="phone" value="{{ $phone }}" placeholder="01XXXXXXXXX" required autofocus>
      </div>
      <button type="submit" class="pg-btn">ট্র্যাক করুন</button>
    </form>
  </div>

  @if ($orders->isNotEmpty())
    <h2 style="font-size:19px;margin:0 0 12px">আপনার অর্ডারসমূহ</h2>

    @foreach ($orders as $order)
      <div class="pg-card" style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
          <div><strong style="color:var(--ink);font-size:18px">অর্ডার #{{ $order->id }}</strong><br>
            <small>{{ $order->created_at->format('d M Y, h:i A') }}</small></div>
          <span class="pg-badge s-{{ $order->status }}">{{ $labels[$order->status] ?? $order->statusLabel() }}</span>
        </div>

        @if (isset($flow[$order->status]))
          @php $reached = array_search($order->status, array_keys($flow), true); @endphp
          <ul class="pg-steps">
            @foreach (array_values($flow) as $i => $label)
              <li class="{{ $i <= $reached ? 'on' : '' }}">{{ $label }}</li>
            @endforeach
          </ul>
        @elseif ($order->status === 'cancelled')
          <div class="pg-alert err">এই অর্ডারটি বাতিল করা হয়েছে। বিস্তারিত জানতে আমাদের সাথে যোগাযোগ করুন।</div>
        @elseif ($order->status === 'returned')
          <div class="pg-alert info">এই অর্ডারের পণ্য আমাদের কাছে ফেরত এসেছে। বিস্তারিত জানতে আমাদের সাথে যোগাযোগ করুন।</div>
        @endif

        <div>
          <div class="pg-row"><span>মোট</span><span><strong>৳ {{ $bn($order->total) }}</strong></span></div>
          @if (!in_array($order->status, ['cancelled', 'returned'], true))
            <div class="pg-row"><span>পরিশোধ করা হয়েছে</span><span>৳ {{ $bn($order->netPaid()) }}</span></div>
            <div class="pg-row"><span>বাকি</span><span>৳ {{ $bn($order->dueAmount()) }}</span></div>
          @endif
          @if ($order->consignment_id)
            <div class="pg-row"><span>কুরিয়ার ({{ $order->courier ?: 'Steadfast' }})</span>
              <span>@if ($order->tracking_code)কোড: {{ $order->tracking_code }}@endif
                @if ($order->courier_status) · {{ \App\Support\Steadfast::label($order->courier_status) }}@endif</span></div>
          @endif
        </div>

        <details>
          <summary style="cursor:pointer;color:var(--brand);font-weight:600">পণ্য ও বিস্তারিত দেখুন</summary>
          <div style="margin-top:12px;display:flex;flex-direction:column;gap:14px">
            <div class="pg-scroll">
              <table>
                <thead><tr><th>পণ্য</th><th>দাম</th><th>পরিমাণ</th><th style="text-align:right">মোট</th></tr></thead>
                <tbody>
                  @foreach ($order->items as $item)
                    <tr>
                      <td>{{ $item->title }}</td>
                      <td>৳ {{ $bn($item->price) }}</td>
                      <td>{{ $bn($item->quantity) }}</td>
                      <td style="text-align:right">৳ {{ $bn($item->line_total) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <div>
              <div class="pg-row"><span>পণ্যের মূল্য</span><span>৳ {{ $bn($order->subtotal) }}</span></div>
              @if ((int) $order->discount > 0)
                <div class="pg-row"><span>ডিসকাউন্ট @if ($order->coupon_code)({{ $order->coupon_code }})@endif</span><span>− ৳ {{ $bn($order->discount) }}</span></div>
              @endif
              <div class="pg-row"><span>ডেলিভারি চার্জ</span><span>৳ {{ $bn($order->delivery_charge) }}</span></div>
              <div class="pg-row total"><span>মোট</span><span>৳ {{ $bn($order->total) }}</span></div>
            </div>

            @php $orderClaims = $claims->get($order->id, collect()); @endphp
            @if ($orderClaims->isNotEmpty())
              <div>
                <strong style="color:var(--ink)">এই অর্ডারের ক্লেইম</strong>
                <ul>
                  @foreach ($orderClaims as $c)
                    <li>ক্লেইম #{{ $c->id }} · {{ $c->typeLabel() }} · {{ $c->statusLabel() }} · {{ $c->created_at->format('d M Y') }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            @if ($order->status === 'delivered')
              <p style="margin:0">পণ্যে সমস্যা হলে <a href="{{ route('claim.form') }}">ওয়ারেন্টি ক্লেইম করুন</a>।</p>
            @endif
          </div>
        </details>
      </div>
    @endforeach

    @if ($orders->count() >= \App\Http\Controllers\TrackController::MAX_ORDERS)
      <p class="ad-muted" style="color:var(--muted)">সবচেয়ে নতুন {{ $bn(\App\Http\Controllers\TrackController::MAX_ORDERS) }}টি অর্ডার দেখানো হয়েছে।</p>
    @endif
  @endif

  <div class="pg-card pg-narrow">
    <h4>সাহায্য দরকার?</h4>
    <p>অর্ডার খুঁজে পাচ্ছেন না বা কোনো প্রশ্ন আছে? ফোন করুন: @include('pages._phone')</p>
  </div>
</section>
@endsection
