@extends('layouts.app')

@section('content')

  {{-- HERO --}}
  <section class="hero-section">
    <div class="hero-grid">
      <div class="hero-main-slider">
        <div class="hero-main-track" id="heroMainTrack">
          @foreach ([1, 2, 3] as $i)
            <a class="hero-main-slide" href="#products">
              @if (file_exists(public_path("assets/images/hero/slide-{$i}.jpg")))
                <img src="{{ asset("assets/images/hero/slide-{$i}.jpg") }}" alt="Slide {{ $i }}">
              @else
                <div class="hero-ph hero-ph-{{ $i }}">
                  <h2>{{ config('shop.name') }}</h2>
                  <p>Slide {{ $i }} — add public/assets/images/hero/slide-{{ $i }}.jpg to show an image</p>
                </div>
              @endif
            </a>
          @endforeach
        </div>
        <button class="hero-nav prev" id="heroPrev" aria-label="Previous slide">‹</button>
        <button class="hero-nav next" id="heroNext" aria-label="Next slide">›</button>
        <div class="hero-main-dots" id="heroMainDots"></div>
      </div>
      <div class="hero-side">
        @foreach ([1, 2] as $i)
          <a class="hero-side-card" href="{{ route('category.index') }}">
            @if (file_exists(public_path("assets/images/hero/side-{$i}.jpg")))
              <img src="{{ asset("assets/images/hero/side-{$i}.jpg") }}" alt="Offer {{ $i }}">
            @else
              <div class="hero-ph hero-side-ph-{{ $i }}"><p>View all products</p></div>
            @endif
          </a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- CATEGORIES --}}
  <section class="section">
    <div class="section-head"><h2>Shop by Category</h2></div>
    <div class="cat-scroll">
      @foreach ($categories as $c)
        <a class="cat-item" href="{{ route('category.show', $c->id) }}">
          <div class="cat-icon">
            @if ($c->image)
              <img src="{{ asset('assets/images/categories/' . $c->image) }}" alt="{{ $c->label }}" loading="lazy">
            @else
              <span>{{ $c->icon ?: '📦' }}</span>
            @endif
          </div>
          <span>{{ $c->label }}</span>
        </a>
      @endforeach
    </div>
  </section>

  {{-- TOP PRODUCTS --}}
  <section class="section" id="products">
    <div class="section-head">
      <h2>Top Products</h2>
      <a href="{{ route('category.index') }}">View all →</a>
    </div>
    <div class="grid">
      @foreach ($topProducts as $p) @include('shop._card') @endforeach
    </div>
  </section>

  {{-- PROMO BAND --}}
  <div class="promo-band">
    <div>
      <h3>Ordering in bulk?</h3>
      <p>Special wholesale prices for contractors and electricians</p>
    </div>
    <a class="btn-primary" href="#contact">Request a Quote</a>
  </div>

  {{-- NEW PRODUCTS --}}
  <section class="section">
    <div class="section-head">
      <h2>New Products</h2>
      <a href="{{ route('category.index') }}">View all →</a>
    </div>
    <div class="grid">
      @foreach ($newProducts as $p) @include('shop._card') @endforeach
    </div>
  </section>

  {{-- UPCOMING --}}
  @if ($upcomingProducts->isNotEmpty())
    <section class="section" id="upcoming">
      <div class="section-head"><h2>Coming Soon</h2></div>
      <div class="grid">
        @foreach ($upcomingProducts as $p) @include('shop._card') @endforeach
      </div>
    </section>
  @endif

  {{-- CATEGORY SECTIONS --}}
  @foreach ($categories as $c)
    @php $catProducts = $c->products->take(8); @endphp
    @continue($catProducts->isEmpty())

    @php
      $catImg = null;
      foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
          if (file_exists(public_path("assets/images/sections/cat-{$c->id}.{$ext}"))) {
              $catImg = "assets/images/sections/cat-{$c->id}.{$ext}";
              break;
          }
      }
    @endphp

    <section class="section" id="cat-{{ $c->id }}">
      <div class="section-head">
        <h2>{{ $c->label }}</h2>
        <a href="{{ route('category.show', $c->id) }}">View all →</a>
      </div>
      @if ($catImg)
        <a class="cat-section-banner" href="{{ route('category.show', $c->id) }}">
          <img src="{{ asset($catImg) }}" alt="{{ $c->label }}" loading="lazy">
        </a>
      @endif
      <div class="grid">
        @foreach ($catProducts as $p) @include('shop._card') @endforeach
      </div>
    </section>
  @endforeach

@endsection

@push('scripts')
<script>
(function(){
  var track = document.getElementById('heroMainTrack');
  var dotsWrap = document.getElementById('heroMainDots');
  var prevBtn = document.getElementById('heroPrev');
  var nextBtn = document.getElementById('heroNext');
  if(!track || !dotsWrap) return;
  var slides = Array.prototype.slice.call(track.children);
  if(!slides.length) return;

  slides.forEach(function(_, i){
    var b = document.createElement('button');
    b.className = 'hsd-dot' + (i === 0 ? ' active' : '');
    b.type = 'button'; b.dataset.i = i;
    b.setAttribute('aria-label', 'Slide ' + (i + 1));
    dotsWrap.appendChild(b);
  });
  var dots = dotsWrap.querySelectorAll('.hsd-dot');
  var current = 0, timer = null;

  function updateDots(){ dots.forEach(function(d, i){ d.classList.toggle('active', i === current); }); }
  function goTo(i){
    current = (i + slides.length) % slides.length;
    track.scrollTo({ left: current * track.clientWidth, behavior: 'smooth' });
    updateDots();
  }
  function startAutoplay(){ stopAutoplay(); timer = setInterval(function(){ goTo(current + 1); }, 4000); }
  function stopAutoplay(){ if(timer){ clearInterval(timer); timer = null; } }

  prevBtn.addEventListener('click', function(){ goTo(current - 1); startAutoplay(); });
  nextBtn.addEventListener('click', function(){ goTo(current + 1); startAutoplay(); });
  dots.forEach(function(d){ d.addEventListener('click', function(){ goTo(parseInt(d.dataset.i, 10)); startAutoplay(); }); });

  var ticking = false;
  track.addEventListener('scroll', function(){
    if(ticking) return; ticking = true;
    requestAnimationFrame(function(){
      current = Math.round(track.scrollLeft / track.clientWidth);
      updateDots(); ticking = false;
    });
  });
  track.addEventListener('touchstart', stopAutoplay, {passive:true});
  track.addEventListener('touchend', startAutoplay, {passive:true});
  window.addEventListener('resize', function(){ track.scrollTo({ left: current * track.clientWidth, behavior: 'auto' }); });
  if(slides.length > 1) startAutoplay();
})();
</script>
@endpush
