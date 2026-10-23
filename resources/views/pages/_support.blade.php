@php
  $c   = (array) config('shop.contact');
  $tel = !empty($c['phone']) ? 'tel:+88' . preg_replace('/\D+/', '', $c['phone']) : '';
@endphp
<ul>
  @if (!empty($c['phone']))<li>ফোন: <a href="{{ $tel }}">{{ $c['phone'] }}</a></li>@endif
  @if (!empty($c['email']))<li>ইমেইল: <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></li>@endif
</ul>
