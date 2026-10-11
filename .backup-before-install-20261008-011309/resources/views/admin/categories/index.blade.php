@extends('admin.layout')

@section('title', 'Categories')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Categories</h1>
    <a class="ad-btn" href="{{ route('admin.categories.create') }}">＋ New Category</a>
  </div>

  <section class="ad-card">
    @if ($categories->isEmpty())
      <p class="ad-muted">No categories yet.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Order</th><th>Icon</th><th>Name</th><th>Products</th><th></th></tr>
          @foreach ($categories as $c)
            <tr>
              <td>{{ $c->sort_order }}</td>
              <td class="ad-thumb">
                @if ($c->image)
                  <img src="{{ asset('assets/images/categories/' . $c->image) }}" alt="">
                @else
                  <span>{{ $c->icon ?: '📁' }}</span>
                @endif
              </td>
              <td><a href="{{ route('admin.categories.edit', $c->id) }}"><strong>{{ $c->label }}</strong></a></td>
              <td>{{ $c->products_count }}</td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.categories.edit', $c->id) }}">Edit</a>
                <form action="{{ route('admin.categories.destroy', $c->id) }}" method="POST" onsubmit="return confirm('Really delete this category?');">
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
