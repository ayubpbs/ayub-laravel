@php $editing = $product->exists; @endphp

<form method="POST" enctype="multipart/form-data"
      action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}">
  @csrf
  @if ($editing) @method('PUT') @endif

  <div class="form-grid">
    <div class="full">
      <label>প্রোডাক্টের নাম *</label>
      <input type="text" name="title" value="{{ old('title', $product->title) }}" required>
    </div>

    <div>
      <label>ক্যাটাগরি</label>
      <select name="category_id">
        <option value="">— নেই —</option>
        @foreach ($categories as $c)
          <option value="{{ $c->id }}" @selected((string) old('category_id', $product->category_id) === (string) $c->id)>{{ $c->label }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label>ব্র্যান্ড</label>
      <input type="text" name="brand" value="{{ old('brand', $product->brand) }}">
    </div>

    <div>
      <label>দাম (৳) *</label>
      <input type="number" name="price" min="0" value="{{ old('price', $product->price) }}" required>
    </div>

    <div>
      <label>পুরোনো দাম (৳) <small class="muted">— ডিসকাউন্ট দেখাতে</small></label>
      <input type="number" name="old_price" min="0" value="{{ old('old_price', $product->old_price) }}">
    </div>

    <div>
      <label>স্টক সংখ্যা *</label>
      <input type="number" name="stock_quantity" min="0" value="{{ old('stock_quantity', $product->stock_quantity) }}" required>
    </div>

    <div>
      <label>ব্যাজ <small class="muted">— যেমন: -২০%, নতুন</small></label>
      <input type="text" name="badge" value="{{ old('badge', $product->badge) }}">
    </div>

    <div>
      <label>হোমপেজ সেকশন</label>
      <select name="section">
        <option value="">— কোনোটাই না —</option>
        <option value="top" @selected(old('section', $product->section) === 'top')>শীর্ষ পণ্য</option>
        <option value="new" @selected(old('section', $product->section) === 'new')>নতুন পণ্য</option>
      </select>
    </div>

    <div>
      <label>আইকন (ইমোজি) <small class="muted">— ছবি না থাকলে দেখাবে</small></label>
      <input type="text" name="icon" maxlength="20" value="{{ old('icon', $product->icon) }}">
    </div>

    <div>
      <label>রেটিং (০–৫)</label>
      <input type="number" step="0.1" min="0" max="5" name="rating" value="{{ old('rating', $product->rating) }}">
    </div>

    <div>
      <label>রিভিউ সংখ্যা</label>
      <input type="number" min="0" name="reviews" value="{{ old('reviews', $product->reviews) }}">
    </div>

    <div class="full">
      <label>বিবরণ</label>
      <textarea name="description" rows="5">{{ old('description', $product->description) }}</textarea>
    </div>

    <div class="full">
      <label>ছবি <small class="muted">— jpg, png, webp, সর্বোচ্চ ২ MB</small></label>
      @if ($editing && $product->image)
        <p><img class="preview" src="{{ asset('uploads/products/' . $product->image) }}" alt=""></p>
        <label class="check"><input type="checkbox" name="remove_image" value="1"> বর্তমান ছবি মুছুন</label>
      @endif
      <input type="file" name="image" accept="image/*">
    </div>

    <div class="full checks">
      <input type="hidden" name="is_published" value="0">
      <label class="check"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $product->is_published))> সাইটে প্রকাশ করুন</label>

      <input type="hidden" name="is_upcoming" value="0">
      <label class="check"><input type="checkbox" name="is_upcoming" value="1" @checked(old('is_upcoming', $product->is_upcoming))> "শীঘ্রই আসছে" হিসেবে দেখান</label>
    </div>
  </div>

  <p>
    <button class="btn primary" type="submit">{{ $editing ? 'আপডেট করুন' : 'সেভ করুন' }}</button>
    <a class="btn" href="{{ route('admin.products.index') }}">বাতিল</a>
  </p>
</form>
