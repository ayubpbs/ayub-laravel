{{-- Reviews tab: summary, approved reviews and the "write a review" form. Expects: $product --}}
@php
  $rs   = $product->reviewStats();
  $list = $product->approvedReviews(20);
@endphp

@if (session('review_sent') || $errors->any() || session('error'))
  <span data-pd-open-reviews hidden></span>
@endif

@if (session('review_sent'))
  <div class="flash flash-ok" role="status">{{ session('review_sent') }}</div>
@endif
@if (session('error'))
  <div class="flash flash-err" role="alert">{{ session('error') }}</div>
@endif

<div class="pd-rev-top">
  <div class="pd-rev-sum">
    @if ($rs['count'] > 0)
      <div class="pd-rev-avg">{{ number_format($rs['avg'], 1) }}</div>
      <div class="stars" aria-hidden="true">{{ str_repeat('★', (int) round($rs['avg'])) . str_repeat('☆', 5 - (int) round($rs['avg'])) }}</div>
      <div class="pd-muted">{{ $rs['count'] }} {{ $rs['count'] == 1 ? 'review' : 'reviews' }}</div>
    @else
      <div class="pd-muted">No reviews yet.<br>Be the first to review this product.</div>
    @endif
  </div>

  @if ($rs['count'] > 0)
    <div class="pd-rev-bars">
      @foreach ([5, 4, 3, 2, 1] as $star)
        @php $pct = $rs['count'] ? round($rs['dist'][$star] / $rs['count'] * 100) : 0; @endphp
        <div class="pd-rev-bar">
          <span>{{ $star }} ★</span>
          <div class="pd-rev-track"><div style="width:{{ $pct }}%"></div></div>
          <span class="pd-muted">{{ $rs['dist'][$star] }}</span>
        </div>
      @endforeach
    </div>
  @endif
</div>

@if ($list->isNotEmpty())
  <ul class="pd-rev-list">
    @foreach ($list as $r)
      <li>
        <div class="pd-rev-head">
          <span class="stars" aria-label="{{ $r->rating }} out of 5 stars">{{ $r->stars() }}</span>
          <strong>{{ $r->name }}</strong>
          <span class="pd-muted">{{ $r->created_at->format('d M Y') }}</span>
        </div>
        @if ($r->comment)
          <p class="pd-rev-text">{!! nl2br(e($r->comment)) !!}</p>
        @endif
      </li>
    @endforeach
  </ul>
@endif

<form class="pd-rev-form" method="POST" action="{{ route('product.review.store', $product->id) }}">
  @csrf
  <h4>Write a review</h4>

  @if ($errors->any())
    <div class="flash flash-err" role="alert">
      @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
  @endif

  <fieldset class="pd-starinput">
    <legend>Your rating *</legend>
    <div class="pd-starinput-row">
      @for ($i = 5; $i >= 1; $i--)
        <input type="radio" name="rating" id="pd-star-{{ $i }}" value="{{ $i }}" {{ (int) old('rating') === $i ? 'checked' : '' }} required>
        <label for="pd-star-{{ $i }}" title="{{ $i }} {{ $i == 1 ? 'star' : 'stars' }}"><span class="pd-sr">{{ $i }} {{ $i == 1 ? 'star' : 'stars' }}</span>★</label>
      @endfor
    </div>
  </fieldset>

  <label class="pd-field">Your name *
    <input type="text" name="name" value="{{ old('name') }}" maxlength="80" required autocomplete="name">
  </label>
  <label class="pd-field">Your review <small class="pd-muted">(optional)</small>
    <textarea name="comment" rows="4" maxlength="1000">{{ old('comment') }}</textarea>
  </label>

  {{-- Spam trap: hidden from people, bots tend to fill it --}}
  <div class="pd-hp" aria-hidden="true">
    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
  </div>

  <button type="submit" class="btn-primary pd-btn pd-rev-submit">Submit review</button>
  <p class="pd-muted pd-rev-note">Your review will be shown after it is approved.</p>
</form>
