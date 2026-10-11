@extends('layouts.app')

@section('title', $title . ' — ' . config('shop.name'))

@section('content')
  <section class="section">
    <div class="section-head">
      <h2>{{ $title }}</h2>
      <a href="{{ route('home') }}">← Home</a>
    </div>

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
