{{-- Product media column: big stage (image or YouTube video), prev/next, thumbnails.
     Expects: $product, $gallery (list of image URLs), $state --}}
@php
  // Images first, then the YouTube videos
  $items = [];
  foreach ($gallery as $u) {
      $items[] = ['type' => 'image', 'src' => $u];
  }
  foreach ($product->videos() as $v) {
      $items[] = ['type' => 'video', 'thumb' => $v['thumb'], 'hq' => $v['hq'], 'embed' => $v['embed']];
  }
  $first      = $items[0] ?? null;
  $firstIsImg = $first && $first['type'] === 'image';
@endphp
<div class="pd-media-col" data-pd-gallery data-items="{{ json_encode($items) }}" data-title="{{ $product->title }}">
  <div class="pd-media">
    @if ($product->discountPercent() > 0 && $state !== 'upcoming')
      <span class="pd-badge">-{{ $product->discountPercent() }}%</span>
    @elseif ($state === 'upcoming')
      <span class="pd-badge pd-badge-soon">Upcoming</span>
    @elseif ($state === 'out')
      <span class="pd-badge pd-badge-out">Stock out</span>
    @endif

    @if ($first)
      <button type="button" class="pd-zoom" data-pd-zoom aria-label="Enlarge image" @if (!$firstIsImg) hidden @endif>
        <img @if ($firstIsImg) src="{{ $first['src'] }}" fetchpriority="high" @endif alt="{{ $product->title }}" data-pd-main>
        <span class="pd-zoom-hint" aria-hidden="true">🔍 Click to enlarge</span>
      </button>

      <div class="pd-video" data-pd-video @if ($firstIsImg) hidden @endif>
        <button type="button" class="pd-video-play" data-pd-play aria-label="Play video">
          <img @if (!$firstIsImg) src="{{ $first['hq'] }}" @endif alt="" data-pd-video-thumb>
          <span class="pd-play-icon" aria-hidden="true">▶</span>
        </button>
      </div>

      @if (count($items) > 1)
        <button type="button" class="pd-nav pd-prev" data-pd-prev aria-label="Previous">‹</button>
        <button type="button" class="pd-nav pd-next" data-pd-next aria-label="Next">›</button>
        <span class="pd-counter" data-pd-counter aria-live="polite">1 / {{ count($items) }}</span>
      @endif
    @else
      <div class="pd-noimg"><span class="emoji">{{ $product->icon ?: '📦' }}</span></div>
    @endif
  </div>

  @if (count($items) > 1)
    <ul class="pd-thumbs" data-pd-thumbs>
      @foreach ($items as $i => $it)
        <li>
          <button type="button" class="pd-thumb {{ $i === 0 ? 'is-active' : '' }}" data-pd-thumb="{{ $i }}"
                  aria-label="{{ $it['type'] === 'video' ? 'Show video' : 'Show image' }} {{ $i + 1 }}" @if ($i === 0) aria-current="true" @endif>
            <img src="{{ $it['type'] === 'video' ? $it['thumb'] : $it['src'] }}" alt="" loading="lazy">
            @if ($it['type'] === 'video')<span class="pd-thumb-play" aria-hidden="true">▶</span>@endif
          </button>
        </li>
      @endforeach
    </ul>
  @endif
</div>
