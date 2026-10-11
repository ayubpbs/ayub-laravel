@php $editing = $category->exists; @endphp

<form method="POST" enctype="multipart/form-data"
      action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
  @csrf
  @if ($editing) @method('PUT') @endif

  <div class="form-grid">
    <div class="full">
      <label>ক্যাটাগরির নাম *</label>
      <input type="text" name="label" value="{{ old('label', $category->label) }}" required>
    </div>

    <div>
      <label>আইকন (ইমোজি) <small class="muted">— ছবি না থাকলে দেখাবে</small></label>
      <input type="text" name="icon" maxlength="20" value="{{ old('icon', $category->icon) }}">
    </div>

    <div>
      <label>ক্রম (ছোট সংখ্যা আগে দেখাবে) *</label>
      <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order) }}" required>
    </div>

    <div class="full">
      <label>ছবি <small class="muted">— jpg, png, webp, সর্বোচ্চ ২ MB</small></label>
      @if ($editing && $category->image)
        <p><img class="preview" src="{{ asset('assets/images/categories/' . $category->image) }}" alt=""></p>
        <label class="check"><input type="checkbox" name="remove_image" value="1"> বর্তমান ছবি মুছুন</label>
      @endif
      <input type="file" name="image" accept="image/*">
    </div>
  </div>

  <p>
    <button class="btn primary" type="submit">{{ $editing ? 'আপডেট করুন' : 'সেভ করুন' }}</button>
    <a class="btn" href="{{ route('admin.categories.index') }}">বাতিল</a>
  </p>
</form>
