@extends('layouts.app')

@section('title', $title . ' — ' . config('shop.name'))

@section('content')
  <section class="section">
    <div class="section-head">
      <h2>{{ $title }}</h2>
      <a href="{{ route('home') }}">← Home</a>
    </div>

    @if (isset($subcategories) && $subcategories->isNotEmpty())
      @php $activeId = isset($category) ? $category->id : null; @endphp
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px">
        <a class="btn-small" href="{{ route('category.show', $root->id) }}"
           @if ($activeId === $root->id) style="border-color:var(--brand);color:var(--brand);font-weight:700" @endif>All {{ $root->label }}</a>
        @foreach ($subcategories as $sub)
          <a class="btn-small" href="{{ route('category.show', $sub->id) }}"
             @if ($activeId === $sub->id) style="border-color:var(--brand);color:var(--brand);font-weight:700" @endif>{{ $sub->label }}</a>
        @endforeach
      </div>
    @endif

    @if ($products->isEmpty())
      <p class="empty">No products found.</p>
    @else
      <div class="grid">
        @foreach ($products as $p) @include('shop._card') @endforeach
      </div>
      <div class="pager">{{ $products->links('pagination::simple-default') }}</div>
    @endif
  </section>
@endsection
