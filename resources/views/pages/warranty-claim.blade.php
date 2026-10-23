@extends('layouts.app')

@section('title', 'ওয়ারেন্টি ক্লেইম — ' . config('shop.name'))

@section('content')
<section class="pg">
  <h1>ওয়ারেন্টি ক্লেইম</h1>

  @if (session('error'))<div class="pg-alert err" style="margin-bottom:14px">{{ session('error') }}</div>@endif
  @if ($errors->any())<div class="pg-alert err" style="margin-bottom:14px">{{ $errors->first() }}</div>@endif

  @if ($done)
    <div class="pg-card pg-narrow">
      <div class="pg-alert ok"><strong>আপনার ক্লেইম জমা হয়েছে। ক্লেইম নম্বর #{{ $done }}</strong></div>
      <p>আমাদের টিম ওয়ারেন্টি যাচাই করে আপনার মোবাইল নম্বরে যোগাযোগ করবে। অনুমোদন পেলে আমরা জানিয়ে দেব কীভাবে পণ্যটি আমাদের কাছে পাঠাবেন। অনুমোদনের আগে পণ্য পাঠাবেন না।</p>
      <p>আপনার ক্লেইমের অবস্থা <a href="{{ route('track.show') }}">অর্ডার ট্র্যাক</a> পেজে দেখতে পারবেন।</p>
      <p>জরুরি প্রয়োজনে ফোন করুন: @include('pages._phone')</p>
    </div>

  @elseif (!$ready)
    <div class="pg-card pg-narrow">
      <div class="pg-alert info">এই মুহূর্তে অনলাইনে ক্লেইম নেওয়া যাচ্ছে না। অনুগ্রহ করে ফোনে যোগাযোগ করুন: @include('pages._phone')</div>
    </div>

  @elseif (!$order)
    <div class="pg-card pg-narrow">
      <p>ওয়ারেন্টি ক্লেইমের জন্য যে মোবাইল নম্বর দিয়ে অর্ডার করেছিলেন সেটি এবং অর্ডার নম্বর দিন। ওয়ারেন্টির নিয়ম জানতে <a href="{{ route('page.warranty') }}">ওয়ারেন্টি পলিসি</a> দেখুন।</p>

      <form method="POST" action="{{ route('claim.find') }}" class="pg-form">
        @csrf
        <div>
          <label for="c-phone">মোবাইল নম্বর *</label>
          <input type="tel" id="c-phone" name="phone" value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required>
          <p class="pg-hint">অর্ডার করার সময় যে নম্বর দিয়েছেন</p>
        </div>
        <div>
          <label for="c-no">অর্ডার নম্বর *</label>
          <input type="text" id="c-no" name="order_no" value="{{ old('order_no') }}" placeholder="যেমন: 125" inputmode="numeric" required>
        </div>
        <button type="submit" class="pg-btn">পরের ধাপ</button>
      </form>
    </div>

  @else
    <div class="pg-card">
      <p>অর্ডার <strong>#{{ $order->id }}</strong> · {{ $order->name }} ·
        <a href="{{ route('claim.form', ['change' => 1]) }}">অন্য অর্ডার</a></p>

      @if (collect($lines)->where('claimable', true)->isEmpty())
        <div class="pg-alert info">এই অর্ডারের কোনো পণ্যে এখন ওয়ারেন্টি ক্লেইম করা যাচ্ছে না।</div>
        <ul>
          @foreach ($lines as $l)
            <li><strong>{{ $l['line']->title }}</strong> — {{ $l['note'] }}</li>
          @endforeach
        </ul>
        <p>কোনো ভুল মনে হলে ফোন করুন: @include('pages._phone')</p>
      @else
        <form method="POST" action="{{ route('claim.store') }}" enctype="multipart/form-data" class="pg-form">
          @csrf

          <div>
            <label>কোন পণ্যে সমস্যা? *</label>
            @foreach ($lines as $l)
              <label class="pg-radio {{ $l['claimable'] ? '' : 'off' }}">
                <input type="radio" name="order_item_id" value="{{ $l['line']->id }}" {{ $l['claimable'] ? '' : 'disabled' }}
                  {{ (string) old('order_item_id') === (string) $l['line']->id ? 'checked' : '' }}>
                <span>{{ $l['line']->title }}
                  <small>অর্ডার করা: {{ $l['line']->quantity }}টি · {{ $l['note'] }}</small></span>
              </label>
            @endforeach
          </div>

          <div>
            <label for="c-qty">কয়টি পণ্যে সমস্যা? *</label>
            <input type="number" id="c-qty" name="quantity" min="1" max="999" value="{{ old('quantity', 1) }}" required style="max-width:120px">
          </div>

          <div>
            <label for="c-reason">সমস্যার বিবরণ *</label>
            <textarea id="c-reason" name="reason" rows="5" maxlength="1000" required placeholder="পণ্যে কী সমস্যা, কবে থেকে হচ্ছে, কীভাবে ব্যবহার করছিলেন — বিস্তারিত লিখুন">{{ old('reason') }}</textarea>
          </div>

          <div>
            <label for="c-serial">সিরিয়াল নম্বর (ঐচ্ছিক)</label>
            <input type="text" id="c-serial" name="serial_number" maxlength="100" value="{{ old('serial_number') }}">
          </div>

          <div>
            <label for="c-files">ছবি বা ভিডিও (ঐচ্ছিক)</label>
            <input type="file" id="c-files" name="files[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm">
            <p class="pg-hint">ছবি/ভিডিও দিলে দ্রুত সমাধান পাবেন। ছবি (JPG, PNG, WebP) ৫ MB পর্যন্ত, ভিডিও (MP4, MOV, WebM) ৩০ MB পর্যন্ত, সর্বোচ্চ ১২টি ফাইল।</p>
          </div>

          <button type="submit" class="pg-btn">ক্লেইম জমা দিন</button>
          <p class="pg-hint">ক্লেইম জমা দেওয়ার পর আমাদের টিম যাচাই করে যোগাযোগ করবে। অনুমোদনের আগে পণ্য পাঠাবেন না।</p>
        </form>
      @endif
    </div>
  @endif
</section>
@endsection
