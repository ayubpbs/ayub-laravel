@php
  $c   = (array) config('shop.contact');
  $tel = !empty($c['phone']) ? 'tel:+88' . preg_replace('/\D+/', '', $c['phone']) : '';
@endphp
@if (!empty($c['phone']))<a href="{{ $tel }}">{{ $c['phone'] }}</a>@endif
