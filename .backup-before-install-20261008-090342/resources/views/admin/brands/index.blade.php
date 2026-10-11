@extends('admin.layout')

@section('title', 'Brands')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Brands</h1>
    <a class="ad-btn" href="{{ route('admin.brands.create') }}">＋ New Brand</a>
  </div>

  <section class="ad-card">
    @if ($brands->isEmpty())
      <p class="ad-muted">No brands yet.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Order</th><th>Logo</th><th>Name</th><th>Products</th><th></th></tr>
          @foreach ($brands as $b)
            <tr>
              <td>{{ $b->sort_order }}</td>
              <td class="ad-thumb">
                @if ($b->logo)
                  <img src="{{ asset('uploads/brands/' . $b->logo) }}" alt="">
                @else
                  <span>🏷️</span>
                @endif
              </td>
              <td><a href="{{ route('admin.brands.edit', $b->id) }}"><strong>{{ $b->name }}</strong></a></td>
              <td><a href="{{ route('admin.products.index', ['brand' => $b->id]) }}">{{ $b->products_count }}</a></td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.brands.edit', $b->id) }}">Edit</a>
                <form action="{{ route('admin.brands.destroy', $b->id) }}" method="POST" onsubmit="return confirm('Really delete this brand?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  </section>
@endsection
