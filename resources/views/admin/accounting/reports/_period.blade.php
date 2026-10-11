<div class="ad-tabs">
  @foreach ($ranges as $key => $label)
    <a href="{{ route($route, ['range' => $key]) }}" class="{{ $range === $key ? 'on' : '' }}">{{ $label }}</a>
  @endforeach
</div>

<form class="ad-filter" method="GET" action="{{ route($route) }}">
  <input type="hidden" name="range" value="custom">
  <label>From <input type="date" name="from" value="{{ $range === 'custom' ? $from : '' }}"></label>
  <label>To <input type="date" name="to" value="{{ $range === 'custom' ? $to : '' }}"></label>
  <button type="submit" class="ad-btn ad-btn-gray">Show custom period</button>
</form>

<p class="ad-muted" style="margin:0 0 12px">Showing: <strong>{{ $title }}</strong> ({{ $span }})</p>
