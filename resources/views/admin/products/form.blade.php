@extends('admin.layout')

@php $editing = $product->exists; @endphp

@php
  // Category dropdowns: main category first, then its subcategories (second dropdown)
  $mains = $categories->filter(function ($c) { return !$c->depth; });
  $subMap = [];
  foreach ($categories->filter(function ($c) { return $c->depth; }) as $s) {
      $subMap[(int) $s->parent_id][] = ['id' => $s->id, 'label' => $s->label];
  }
  $selectedId   = (int) old('category_id', $product->category_id);
  $selectedCat  = $categories->firstWhere('id', $selectedId);
  $selectedMain = $selectedCat ? ($selectedCat->depth ? (int) $selectedCat->parent_id : (int) $selectedCat->id) : 0;
  $selectedSub  = ($selectedCat && $selectedCat->depth) ? (int) $selectedCat->id : 0;
@endphp

@section('title', $editing ? 'Edit Product' : 'New Product')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.products.index') }}">← All products</a></p>
  <h1 class="ad-title">{{ $editing ? 'Edit Product' : 'Add New Product' }}</h1>

  <form class="ad-card ad-form" method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('admin.products.update', $product->id) : route('admin.products.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <fieldset class="ad-sec" id="sec-basic">
      <legend>1. Basic Information</legend>

      @if ($editing)
        <p class="ad-muted" style="margin:0">Product ID: <strong>#{{ $product->id }}</strong></p>
      @endif

      <label>Product name *
        <input type="text" name="title" id="pTitle" value="{{ old('title', $product->title) }}" required maxlength="255">
      </label>

      <label>Product slug / URL <small>(the web address of the product page; leave empty to make it from the name)</small>
        <span class="ad-slug">
          <span class="ad-slug-base">{{ url('/product') }}/</span>
          <input type="text" name="slug" id="pSlug" value="{{ old('slug', $product->slug) }}" maxlength="150" placeholder="e.g. led-bulb-12w" autocomplete="off">
        </span>
      </label>

      <div class="ad-row">
        <label>SKU / Product code <small>(optional, must be unique)</small>
          <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" maxlength="60" placeholder="e.g. LED-12W-WW">
        </label>
        <label>Product type
          <select name="product_type">
            @foreach (\App\Models\Product::TYPES as $tk => $tl)
              <option value="{{ $tk }}" {{ (string) old('product_type', $product->product_type ?: 'simple') === (string) $tk ? 'selected' : '' }}>{{ $tl }}</option>
            @endforeach
          </select>
          <small>Variations are added in a later step; until then a variable product behaves like a simple one.</small>
        </label>
      </div>

      <div class="ad-row">
        <label>Category
          <input type="hidden" name="category_id" id="categoryId" value="{{ $selectedId ?: '' }}">
          <select id="mainCategory">
            <option value="">— None —</option>
            @foreach ($mains as $m)
              <option value="{{ $m->id }}" {{ (int) $selectedMain === (int) $m->id ? 'selected' : '' }}>{{ $m->label }}</option>
            @endforeach
          </select>
        </label>
        <label id="subCategoryWrap" @if (empty($subMap[$selectedMain] ?? [])) style="display:none" @endif>Subcategory
          <select id="subCategory"></select>
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

      <div class="ad-field">
        <div class="st-label">Short description <small>(shown beside the product, next to the price — keep it short, up to 600 characters)</small></div>
        <textarea name="short_description" id="shortDesc" rows="5">{{ old('short_description', \App\Models\Product::editorHtml($product->short_description)) }}</textarea>
      </div>

      <div class="ad-field">
        <div class="st-label">Full description <small>(shown below the product. You can paste text copied from any web page — its formatting is kept)</small></div>
        <textarea name="description" id="fullDesc" rows="12">{{ old('description', \App\Models\Product::editorHtml($product->description)) }}</textarea>
      </div>

      @php $statusVal = (string) (int) old('is_published', $editing ? $product->is_published : 1); @endphp
      <div class="ad-row">
        <label>Product status
          <select name="is_published">
            <option value="1" {{ $statusVal === '1' ? 'selected' : '' }}>Published — visible on the website</option>
            <option value="0" {{ $statusVal === '0' ? 'selected' : '' }}>Draft — hidden from customers</option>
          </select>
        </label>
        <div class="ad-checks" style="align-self:end">
          <input type="hidden" name="is_upcoming" value="0">
          <label class="ad-check"><input type="checkbox" name="is_upcoming" value="1"
            {{ old('is_upcoming', $product->is_upcoming) ? 'checked' : '' }}> "Coming soon" (cannot be bought yet)</label>
        </div>
      </div>
    </fieldset>

    <fieldset class="ad-sec" id="sec-other">
      <legend>Other details <small>(these are moved into their own sections in the next steps)</small></legend>

    <div class="ad-row">
      <label>Price (৳) *
        <input type="number" name="price" value="{{ old('price', $product->price) }}" required min="0">
      </label>
      <label>Buying price (৳) <small>(ক্রয়মূল্য, per unit — only you can see it)</small>
        <input type="number" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" min="0">
      </label>
      <label>Old price (৳) <small>(to show a discount)</small>
        <input type="number" name="old_price" value="{{ old('old_price', $product->old_price) }}" min="0">
      </label>
      <label>Stock (units available) *
        <input type="number" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required min="0">
      </label>
    </div>

    {{-- Phase 6: warranty of this product. Empty = the shop default from Settings. The policy is copied onto every order line when an order is created. --}}
    @if (\App\Support\WarrantyPolicy::columnsReady())
      <div class="ad-row">
        <label>🛡 Warranty <small>(for this product)</small>
          <select name="warranty_type">
            <option value="">Use the shop default (Settings)</option>
            <option value="none" {{ old('warranty_type', $product->warranty_type) === 'none' ? 'selected' : '' }}>No warranty</option>
            @foreach (\App\Support\WarrantyPolicy::types() as $k => $label)
              <option value="{{ $k }}" {{ old('warranty_type', $product->warranty_type) === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>Warranty period (days)
          <input type="number" name="warranty_days" value="{{ old('warranty_days', $product->warranty_days) }}" min="0" max="3650" placeholder="e.g. 365">
        </label>
      </div>
      <label>Warranty terms <small>(shown to your staff on a claim; empty = shop default terms)</small>
        <textarea name="warranty_terms" rows="2" maxlength="1000" placeholder="e.g. No cover for burn, water or physical damage.">{{ old('warranty_terms', $product->warranty_terms) }}</textarea>
      </label>
      <p class="ad-hint">Changing this affects orders created from now on. Orders that were already created keep the warranty they were sold with.</p>
    @endif

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

    @php $galleryImages = $editing ? $product->galleryImages() : collect(); @endphp
    <label>More images <small>(gallery — up to 8, jpg, png, webp — max 2 MB each; hold Ctrl to pick several)</small>
      <input type="file" name="gallery[]" accept="image/png,image/jpeg,image/webp" multiple>
    </label>
    @if ($galleryImages->isNotEmpty())
      <div style="display:flex;flex-wrap:wrap;gap:12px;margin:0 0 14px">
        @foreach ($galleryImages as $g)
          <div style="width:96px;text-align:center;font-size:12px">
            <img src="{{ $g->url() }}" alt="" style="width:96px;height:96px;object-fit:contain;background:#fafafa;border:1px solid #e5e5e5;border-radius:6px">
            <label class="ad-check" style="display:block;margin-top:4px"><input type="checkbox" name="remove_gallery[]" value="{{ $g->id }}"> Remove</label>
          </div>
        @endforeach
      </div>
    @endif

    <label>YouTube video links <small>(optional — one link per line, up to 5)</small>
      <textarea name="video_urls" rows="3" placeholder="https://www.youtube.com/watch?v=...&#10;https://youtu.be/...">{{ old('video_urls', $product->video_urls) }}</textarea>
    </label>

    @php
      // Saved rows (or the rows the admin just typed, when the form came back with an error); one empty row for a new product
      $oldSpecs = old('specifications');
      $specRows = is_array($oldSpecs) ? array_values($oldSpecs) : $product->specs();
      if (!$specRows) { $specRows = [['name' => '', 'value' => '']]; }
    @endphp
    <div class="ad-specs" id="specsBox">
      <div class="st-label">Specifications <small>(optional — e.g. Watt: 12W, Voltage: 220V; up to 30 rows)</small></div>
      <div id="specsRows">
        @foreach ($specRows as $i => $row)
          <div class="ad-spec-row">
            <input type="text" name="specifications[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="100" placeholder="Name (e.g. Watt)" list="specNames" aria-label="Specification name">
            <input type="text" name="specifications[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" maxlength="255" placeholder="Value (e.g. 12W)" aria-label="Specification value">
            <button type="button" class="ad-spec-remove" data-spec-remove aria-label="Remove this specification" title="Remove">×</button>
          </div>
        @endforeach
      </div>
      <datalist id="specNames">
        <option value="Watt"><option value="Voltage"><option value="Current"><option value="Frequency">
        <option value="Color"><option value="Color Temperature"><option value="Lumens"><option value="Base / Socket">
        <option value="Material"><option value="Size"><option value="Weight"><option value="Warranty">
      </datalist>
      <div><button type="button" class="ad-btn ad-btn-gray" id="addSpec">+ Add specification</button></div>
    </div>

    </fieldset>

    <div class="ad-submit">
      <button type="submit" class="ad-btn">{{ $editing ? 'Update' : 'Add Product' }}</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.products.index') }}">Cancel</a>
    </div>
  </form>

  <script>
  (function () {
    var subMap = @json($subMap);
    var selectedSub = {{ $selectedSub }};
    var main = document.getElementById('mainCategory');
    var sub = document.getElementById('subCategory');
    var wrap = document.getElementById('subCategoryWrap');
    var hidden = document.getElementById('categoryId');
    if (!main || !sub || !wrap || !hidden) return;

    function fillSubs(keepId) {
      var list = subMap[main.value] || [];
      sub.innerHTML = '';
      var none = document.createElement('option');
      none.value = '';
      none.textContent = '— None —';
      sub.appendChild(none);
      list.forEach(function (s) {
        var o = document.createElement('option');
        o.value = s.id;
        o.textContent = s.label;
        if (String(s.id) === String(keepId)) o.selected = true;
        sub.appendChild(o);
      });
      wrap.style.display = list.length ? '' : 'none';
    }

    // The saved category is the subcategory when one is chosen, otherwise the main category
    function sync() { hidden.value = sub.value || main.value || ''; }

    main.addEventListener('change', function () { fillSubs(''); sync(); });
    sub.addEventListener('change', sync);

    fillSubs(selectedSub);
    sync();
  })();
  </script>

  {{-- Rich-text editor (TinyMCE from cdnjs). If it cannot load, the boxes stay plain text boxes and everything still saves. --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
  <script>
  (function () {
    // ----- Slug: filled from the product name until the admin edits it by hand -----
    var title = document.getElementById('pTitle');
    var slug = document.getElementById('pSlug');
    if (title && slug) {
      var slugify = function (t) {
        return t.toLowerCase().replace(/[^\p{L}\p{M}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '').slice(0, 150);
      };
      var auto = slug.value === '' || slug.value === slugify(title.value);
      title.addEventListener('input', function () { if (auto) slug.value = slugify(title.value); });
      slug.addEventListener('input', function () { auto = slug.value === ''; });
      slug.addEventListener('blur', function () { slug.value = slugify(slug.value); if (slug.value === '') { auto = true; slug.value = slugify(title.value); } });
    }

    // ----- Rich-text editors -----
    if (typeof tinymce === 'undefined') return;

    var common = {
      branding: false,
      promotion: false,
      convert_urls: false,
      relative_urls: false,
      paste_data_images: false,          // pasted pictures must come from a web address, not be stored inside the text
      paste_merge_formats: true,
      paste_webkit_styles: 'all',        // keep colors, sizes, fonts of text copied from other pages
      paste_remove_styles_if_webkit: false,
      link_default_target: '_blank',
      link_assume_external_targets: 'https',
      font_size_formats: '10px 12px 14px 16px 18px 20px 24px 28px 32px 36px 48px',
      font_family_formats: 'Default=inherit; Arial=arial,helvetica,sans-serif; Georgia=georgia,serif; Times New Roman=times new roman,times,serif; Courier New=courier new,courier,monospace; Verdana=verdana,geneva,sans-serif; SolaimanLipi=SolaimanLipi,sans-serif; Kalpurush=Kalpurush,sans-serif',
      content_style: 'body{font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;margin:10px} img{max-width:100%;height:auto} table{border-collapse:collapse} td,th{border:1px solid #ccc;padding:4px 8px}',
      setup: function (ed) { ed.on('change input undo redo', function () { ed.save(); }); }
    };

    tinymce.init(Object.assign({}, common, {
      selector: '#fullDesc',
      height: 480,
      menubar: 'edit insert format table tools',
      plugins: 'advlist autolink lists link image charmap searchreplace visualblocks code fullscreen table wordcount',
      toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | ' +
               'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table hr | ' +
               'subscript superscript blockquote | removeformat | code fullscreen'
    }));

    tinymce.init(Object.assign({}, common, {
      selector: '#shortDesc',
      height: 230,
      menubar: false,
      plugins: 'lists link autolink code',
      toolbar: 'bold italic underline | forecolor | fontsize | bullist numlist | link | removeformat | code'
    }));
  })();
  </script>

  <script>
  (function () {
    var rows = document.getElementById('specsRows');
    var add = document.getElementById('addSpec');
    if (!rows || !add) return;

    var next = rows.querySelectorAll('.ad-spec-row').length + 1000;   // unique index for every new row

    function newRow() {
      var d = document.createElement('div');
      d.className = 'ad-spec-row';
      d.innerHTML =
        '<input type="text" name="specifications[' + next + '][name]" maxlength="100" placeholder="Name (e.g. Watt)" list="specNames" aria-label="Specification name">' +
        '<input type="text" name="specifications[' + next + '][value]" maxlength="255" placeholder="Value (e.g. 12W)" aria-label="Specification value">' +
        '<button type="button" class="ad-spec-remove" data-spec-remove aria-label="Remove this specification" title="Remove">\u00d7</button>';
      next++;
      return d;
    }

    add.addEventListener('click', function () {
      if (rows.querySelectorAll('.ad-spec-row').length >= 30) { alert('You can add at most 30 specifications.'); return; }
      var r = newRow();
      rows.appendChild(r);
      r.querySelector('input').focus();
    });

    rows.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-spec-remove]');
      if (!btn) return;
      var row = btn.closest('.ad-spec-row');
      if (rows.querySelectorAll('.ad-spec-row').length <= 1) {   // keep one empty row instead of none
        row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      } else {
        row.remove();
      }
    });
  })();
  </script>
@endsection
