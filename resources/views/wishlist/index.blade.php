@extends('layouts.app')

@section('title', 'Wishlist — ' . config('shop.name'))

@section('content')
  <section class="section">
    <h1 class="page-title">♥ Your Wishlist</h1>

    @if ($products->isEmpty())
      <div class="empty">
        <p>Your wishlist is empty. Tap the ♡ on any product to save it here.</p>
        <a class="btn-primary" href="{{ route('home') }}">Start shopping</a>
      </div>
    @else
      <div class="grid" data-wishlist-grid>
        @foreach ($products as $p) @include('shop._card') @endforeach
      </div>
    @endif
  </section>
@endsection
