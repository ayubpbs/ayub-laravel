@extends('admin.layout')

@php $editing = $brand->exists; @endphp

@section('title', $editing ? 'Edit Brand' : 'New Brand')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.brands.index') }}">← All brands</a></p>
  <h1 class="ad-title">{{ $editing ? 'Edit Brand' : 'New Brand' }}</h1>

  <form class="ad-card ad-form" method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('admin.brands.update', $brand->id) : route('admin.brands.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <label>Brand name *
      <input type="text" name="name" value="{{ old('name', $brand->name) }}" required maxlength="100">
    </label>

    <div class="ad-row">
      <label>Sort order <small>(smaller numbers come first)</small>
        <input type="number" name="sort_order" value="{{ old('sort_order', $brand->sort_order ?? 0) }}" min="0">
      </label>
      <label>Logo <small>(optional, max 2 MB)</small>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
      </label>
    </div>

    @if ($editing && $brand->logo)
      <div class="ad-current">
        <img src="{{ asset('uploads/brands/' . $brand->logo) }}" alt="">
        <label class="ad-check"><input type="checkbox" name="remove_logo" value="1"> Remove the current logo</label>
      </div>
    @endif

    <div class="ad-submit">
      <button type="submit" class="ad-btn">{{ $editing ? 'Update' : 'Add Brand' }}</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.brands.index') }}">Cancel</a>
    </div>
  </form>
@endsection
