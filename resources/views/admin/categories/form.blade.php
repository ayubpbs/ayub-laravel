@extends('admin.layout')

@php $editing = $category->exists; @endphp

@section('title', $editing ? 'Edit Category' : 'New Category')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.categories.index') }}">← All categories</a></p>
  <h1 class="ad-title">{{ $editing ? 'Edit Category' : 'New Category' }}</h1>

  <form class="ad-card ad-form" method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('admin.categories.update', $category->id) : route('admin.categories.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <label>Category name *
      <input type="text" name="label" value="{{ old('label', $category->label) }}" required maxlength="100">
    </label>

    @if ($hasChildren)
      <p class="ad-muted">This category has subcategories, so it stays a main category.</p>
    @else
      <label>Parent category <small>(leave empty to make this a main category)</small>
        <select name="parent_id">
          <option value="">— None (main category) —</option>
          @foreach ($parents as $p)
            <option value="{{ $p->id }}" {{ (string) old('parent_id', $category->parent_id) === (string) $p->id ? 'selected' : '' }}>{{ $p->label }}</option>
          @endforeach
        </select>
      </label>
    @endif

    <div class="ad-row">
      <label>Emoji icon <small>(shown when there is no image)</small>
        <input type="text" name="icon" value="{{ old('icon', $category->icon) }}" maxlength="20" placeholder="💡">
      </label>
      <label>Sort order <small>(smaller numbers come first)</small>
        <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0">
      </label>
    </div>

    <label>Image <small>(optional, max 2 MB)</small>
      <input type="file" name="image" accept="image/png,image/jpeg,image/webp">
    </label>

    @if ($editing && $category->image)
      <div class="ad-current">
        <img src="{{ asset('assets/images/categories/' . $category->image) }}" alt="">
        <label class="ad-check"><input type="checkbox" name="remove_image" value="1"> Remove the current image</label>
      </div>
    @endif

    <div class="ad-submit">
      <button type="submit" class="ad-btn">{{ $editing ? 'Update' : 'Add Category' }}</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.categories.index') }}">Cancel</a>
    </div>
  </form>
@endsection
