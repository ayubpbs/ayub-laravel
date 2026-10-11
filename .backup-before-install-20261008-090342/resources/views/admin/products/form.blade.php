@extends('admin.layout')

@php $editing = $product->exists; @endphp

@section('title', $editing ? 'Edit Product' : 'New Product')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.products.index') }}">← All products</a></p>
  <h1 class="ad-title">{{ $editing ? 'Edit Product' : 'Add New Product' }}</h1>

  <form class="ad-card ad-form" method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('admin.products.update', $product->id) : route('admin.products.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <label>Product name *
      <input type="text" name="title" value="{{ old('title', $product->title) }}" required maxlength="255">
    </label>

    <div class="ad-row">
      <label>Category
        <select name="category_id">
          <option value="">— None —</option>
          @foreach ($categories as $c)
            <option value="{{ $c->id }}" {{ (string) old('category_id', $product->category_id) === (string) $c->id ? 'selected' : '' }}>{{ $c->label }}</option>
          @endforeach
        </select>
      </label>
      <label>Brand <small>(<a class="ad-link" href="{{ route('admin.brands.create') }}" target="_blank">add a new brand</a>)</small>
        <select name="brand_id">
          <option value="">— None —</option>
          @foreach ($brands as $b)
            <option value="{{ $b->id }}" {{ (string) old('brand_id', $product->brand_id) === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
          @endforeach
        </select>
      </label>
    </div>

    <div class="ad-row">
      <label>Price (৳) *
        <input type="number" name="price" value="{{ old('price', $product->price) }}" required min="0">
      </label>
      <label>Old price (৳) <small>(to show a discount)</small>
        <input type="number" name="old_price" value="{{ old('old_price', $product->old_price) }}" min="0">
      </label>
      <label>Stock (units available) *
        <input type="number" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required min="0">
      </label>
    </div>

    <label>Description
      <textarea name="description" rows="4" maxlength="5000">{{ old('description', $product->description) }}</textarea>
    </label>

    <div class="ad-row">
      <label>Badge <small>(e.g. -20%, New)</small>
        <input type="text" name="badge" value="{{ old('badge', $product->badge) }}" maxlength="50">
      </label>
      <label>Which home page section should show it?
        <select name="section">
          <option value="">None</option>
          <option value="top" {{ old('section', $product->section) === 'top' ? 'selected' : '' }}>Top selling</option>
          <option value="new" {{ old('section', $product->section) === 'new' ? 'selected' : '' }}>New products</option>
        </select>
      </label>
    </div>

    <div class="ad-row">
      <label>Image <small>(jpg, png, webp — max 2 MB)</small>
        <input type="file" name="image" accept="image/png,image/jpeg,image/webp">
      </label>
      <label>Emoji icon <small>(shown when there is no image)</small>
        <input type="text" name="icon" value="{{ old('icon', $product->icon) }}" maxlength="20" placeholder="💡">
      </label>
    </div>

    @if ($editing && $product->image)
      <div class="ad-current">
        <img src="{{ asset('uploads/products/' . $product->image) }}" alt="">
        <label class="ad-check"><input type="checkbox" name="remove_image" value="1"> Remove the current image</label>
      </div>
    @endif

    <div class="ad-checks">
      <input type="hidden" name="is_published" value="0">
      <label class="ad-check"><input type="checkbox" name="is_published" value="1"
        {{ old('is_published', $editing ? $product->is_published : true) ? 'checked' : '' }}> Show on the site (published)</label>

      <input type="hidden" name="is_upcoming" value="0">
      <label class="ad-check"><input type="checkbox" name="is_upcoming" value="1"
        {{ old('is_upcoming', $product->is_upcoming) ? 'checked' : '' }}> "Coming soon" (cannot be bought yet)</label>
    </div>

    <div class="ad-submit">
      <button type="submit" class="ad-btn">{{ $editing ? 'Update' : 'Add Product' }}</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.products.index') }}">Cancel</a>
    </div>
  </form>
@endsection
