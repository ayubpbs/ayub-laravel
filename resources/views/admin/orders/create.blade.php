@extends('admin.layout')

@section('title', $source ? 'Reorder #' . $source->id : 'New Order')

@section('content')
  <p class="ad-crumbs"><a href="{{ $source ? route('admin.orders.show', $source->id) : route('admin.orders.index') }}">← {{ $source ? 'Order #' . $source->id : 'All orders' }}</a></p>
  <h1 class="ad-title">{{ $source ? 'Reorder from order #' . $source->id : 'New Order' }}</h1>

  @if (!$ready)
    <div class="ad-flash ad-err">The new order columns are missing. Run <code>php artisan migrate</code> first, then reload this page.</div>
  @endif

  @if ($source)
    <p class="ad-hint">This makes a <strong>new order</strong> with a new order number. Today's prices are used, and the old order is not changed.</p>
  @endif
  @foreach ($skipped as $msg)
    <div class="ad-flash ad-err">{{ $msg }}</div>
  @endforeach

  <form class="ad-form" id="order-form" method="POST" action="{{ route('admin.orders.store') }}" autocomplete="off">
    @csrf
    <input type="hidden" name="client_token" value="{{ $token }}">
    <input type="hidden" name="source_order_id" value="{{ $source->id ?? '' }}">
    <input type="hidden" name="address_id" id="f-address-id" value="{{ old('address_id') }}">

    <section class="ad-card">
      <h2>1. Customer</h2>
      <div class="ad-row">
        <label>Mobile number *
          <input type="text" name="phone" id="f-phone" value="{{ old('phone', $form['phone']) }}" required maxlength="30" inputmode="tel" placeholder="01712345678">
          <small id="f-phone-msg"></small>
        </label>
        <label>Customer name *
          <input type="text" name="name" id="f-name" value="{{ old('name', $form['name']) }}" required maxlength="100">
        </label>
      </div>
      <div id="saved-box" hidden>
        <label>Use a saved address
          <select id="f-saved"></select>
        </label>
      </div>
    </section>

    <section class="ad-card">
      <h2>2. Delivery address</h2>
      <div class="ad-row">
        <label>District *
          <select name="district" id="f-district" required><option value="">Select district</option></select>
        </label>
        <label>Upazila / Thana *
          <select name="upazila" id="f-upazila" required disabled><option value="">Select district first</option></select>
        </label>
        <label>City / Town *
          <input type="text" name="city" id="f-city" value="{{ old('city', $form['city']) }}" required maxlength="100">
        </label>
      </div>
      <label>Full address *
        <textarea name="address" id="f-address" rows="2" required maxlength="500">{{ old('address', $form['address']) }}</textarea>
      </label>
      <label>Order note <small>(optional)</small>
        <input type="text" name="note" value="{{ old('note', $form['note']) }}" maxlength="500">
      </label>
      <label class="ad-check">
        <input type="checkbox" name="save_address" value="1" {{ old('save_address', '1') ? 'checked' : '' }}>
        Save this address for the customer <small>(skipped if it is already saved)</small>
      </label>
    </section>

    <section class="ad-card">
      <h2>3. Products</h2>
      <label>Search product <small>(name or SKU, at least 2 letters)</small>
        <input type="text" id="p-search" maxlength="60">
      </label>
      <div id="p-results" class="ad-hint"></div>

      <div style="overflow-x:auto">
        <table class="ad-table" id="p-table">
          <thead><tr><th>Product</th><th class="r">Price</th><th>Qty</th><th class="r">Total</th><th></th></tr></thead>
          <tbody id="p-body"></tbody>
        </table>
      </div>
      <p class="ad-muted" id="p-empty">No product added yet.</p>
    </section>

    <section class="ad-card">
      <h2>4. Coupon, delivery, discount and payment</h2>
      <div class="ad-row">
        <label>Coupon code <small>(optional, checked when you save)</small>
          <input type="text" name="coupon_code" value="{{ old('coupon_code', $form['coupon_code']) }}" maxlength="30">
        </label>
        <label>Delivery charge (৳) <small>(blank = automatic, 0 = free delivery)</small>
          <input type="number" name="delivery_charge" id="f-delivery" value="{{ old('delivery_charge') }}" min="0" step="1" placeholder="Automatic">
        </label>
        <label>Discount (৳) <small>(optional)</small>
          <input type="number" name="discount_amount" id="f-discount" value="{{ old('discount_amount') }}" min="0" step="1">
        </label>
      </div>
      <div class="ad-row">
        <label>bKash amount received (৳) <small>(optional)</small>
          <input type="number" name="bkash_amount" id="f-bkash" value="{{ old('bkash_amount') }}" min="1" step="1">
        </label>
        <label>bKash transaction ID <small>(optional)</small>
          <input type="text" name="bkash_trx_id" value="{{ old('bkash_trx_id') }}" maxlength="100">
        </label>
      </div>
      <dl class="ad-dl">
        <dt>Subtotal</dt><dd id="t-sub">৳ 0</dd>
        <dt>Discount</dt><dd id="t-disc">− ৳ 0</dd>
        <dt>Delivery charge</dt><dd id="t-del">৳ 0</dd>
        <dt>Total</dt><dd><strong id="t-total">৳ 0</strong> <small class="ad-muted">(before any coupon)</small></dd>
        <dt>bKash received</dt><dd id="t-bk">৳ 0</dd>
        <dt>Due</dt><dd id="t-due">৳ 0</dd>
        <dt>Payment</dt><dd>The due amount is cash on delivery. Record the money in Payments when it is received.</dd>
      </dl>
    </section>

    <div class="ad-submit">
      <button type="submit" class="ad-btn" id="f-submit" {{ $ready ? '' : 'disabled' }}>Create order</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.orders.index') }}">Cancel</a>
    </div>
  </form>

  <script>
    (function () {
      var locations = @json($locations);
      var rates     = @json($rates);
      var items     = @json($items);
      var selDistrict = @json(old('district', $form['district']));
      var selUpazila  = @json(old('upazila', $form['upazila']));
      var urlCustomer = @json(route('admin.orders.create.customer'));
      var urlProducts = @json(route('admin.orders.create.products'));

      var $ = function (id) { return document.getElementById(id); };
      var dEl = $('f-district'), uEl = $('f-upazila');
      var money = function (n) { return '৳ ' + Number(n).toLocaleString('en-US'); };

      function opt(value, label, selected) {
        var o = document.createElement('option');
        o.value = value; o.textContent = label; if (selected) o.selected = true;
        return o;
      }

      // ----- district / upazila -----
      locations.forEach(function (d) { dEl.appendChild(opt(d.n, d.n + ' (' + d.b + ')', d.n === selDistrict)); });
      function fillUpazilas(name, keep) {
        uEl.innerHTML = '';
        var d = locations.filter(function (x) { return x.n === name; })[0];
        if (!d) { uEl.appendChild(opt('', 'Select district first')); uEl.disabled = true; return; }
        uEl.disabled = false;
        uEl.appendChild(opt('', 'Select upazila'));
        d.u.forEach(function (u) { uEl.appendChild(opt(u[0], u[0] + ' (' + u[1] + ')', keep && u[0] === selUpazila)); });
      }
      fillUpazilas(selDistrict, true);
      dEl.addEventListener('change', function () { fillUpazilas(dEl.value, false); clearAddressId(); totals(); });
      uEl.addEventListener('change', clearAddressId);
      $('f-city').addEventListener('input', clearAddressId);
      $('f-address').addEventListener('input', clearAddressId);
      function clearAddressId() { $('f-address-id').value = ''; }

      // ----- customer lookup -----
      var saved = [], last = null, timer = null;
      function useAddress(a) {
        $('f-address-id').value = a.id || '';
        dEl.value = a.district; selUpazila = a.upazila; fillUpazilas(a.district, true);
        $('f-city').value = a.city; $('f-address').value = a.address;
        totals();
      }
      function lookup() {
        var v = $('f-phone').value.trim(), msg = $('f-phone-msg');
        if (v.replace(/\D/g, '').length < 10) { msg.textContent = ''; return; }
        fetch(urlCustomer + '?phone=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            var box = $('saved-box'), sel = $('f-saved');
            if (!d.valid) { msg.textContent = 'This is not a valid mobile number.'; box.hidden = true; return; }
            $('f-phone').value = d.phone;
            msg.textContent = d.known ? 'Existing customer.' : 'New customer.';
            if (d.name && !$('f-name').value.trim()) { $('f-name').value = d.name; }
            saved = d.addresses || []; last = d.last;
            sel.innerHTML = '';
            sel.appendChild(opt('', 'Choose…'));
            saved.forEach(function (a, i) { sel.appendChild(opt('s' + i, (a.is_default ? '★ ' : '') + a.line)); });
            if (last) { sel.appendChild(opt('last', 'Last order: ' + last.line)); }
            box.hidden = !(saved.length || last);
            // an empty address form is filled with the default saved address (or the last order's address)
            if (!$('f-address').value.trim() && !dEl.value) {
              if (saved.length) { useAddress(saved[0]); sel.value = 's0'; }
              else if (last) { useAddress({ id: '', district: last.district, upazila: last.upazila, city: last.city, address: last.address }); sel.value = 'last'; }
            }
          })
          .catch(function () { msg.textContent = ''; });
      }
      $('f-phone').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(lookup, 500); });
      $('f-phone').addEventListener('blur', lookup);
      $('f-saved').addEventListener('change', function () {
        var v = this.value;
        if (v === 'last' && last) { useAddress({ id: '', district: last.district, upazila: last.upazila, city: last.city, address: last.address }); }
        else if (v.charAt(0) === 's') { useAddress(saved[parseInt(v.slice(1), 10)]); }
      });
      if ($('f-phone').value) { lookup(); }

      // ----- products -----
      var pTimer = null;
      $('p-search').addEventListener('input', function () {
        clearTimeout(pTimer);
        var q = this.value.trim(), box = $('p-results');
        if (q.length < 2) { box.textContent = ''; return; }
        pTimer = setTimeout(function () {
          fetch(urlProducts + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (list) {
              box.innerHTML = '';
              if (!list.length) { box.textContent = 'No product found.'; return; }
              list.forEach(function (p) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'ad-btn ad-btn-gray'; b.style.margin = '0 6px 6px 0';
                b.textContent = p.title + ' — ' + money(p.price) + ' (stock ' + p.stock + ')';
                if (p.stock <= 0) { b.disabled = true; b.title = 'Out of stock'; }
                b.addEventListener('click', function () { addItem(p); });
                box.appendChild(b);
              });
            });
        }, 300);
      });

      function addItem(p) {
        var found = items.filter(function (i) { return i.id === p.id; })[0];
        if (found) { found.qty = Math.min(found.qty + 1, found.stock || 999); }
        else { items.push({ id: p.id, title: p.title, sku: p.sku, price: p.price, stock: p.stock, qty: 1, note: null }); }
        render();
      }

      function cell(tr, cls) { var td = document.createElement('td'); if (cls) td.className = cls; tr.appendChild(td); return td; }
      function render() {
        var body = $('p-body'); body.innerHTML = '';
        items.forEach(function (it, idx) {
          var tr = document.createElement('tr');
          var c1 = cell(tr); c1.textContent = it.title + (it.sku ? ' (' + it.sku + ')' : '');
          if (it.note) { var n = document.createElement('div'); n.className = 'ad-hint'; n.style.color = '#b45309'; n.textContent = it.note; c1.appendChild(n); }
          var hid = document.createElement('input'); hid.type = 'hidden'; hid.name = 'items[' + idx + '][product_id]'; hid.value = it.id; c1.appendChild(hid);
          cell(tr, 'r').textContent = money(it.price);
          var c3 = cell(tr);
          var q = document.createElement('input'); q.type = 'number'; q.min = 1; q.max = it.stock || 999; q.value = it.qty; q.name = 'items[' + idx + '][quantity]'; q.style.width = '80px';
          q.addEventListener('input', function () { it.qty = Math.max(1, parseInt(q.value, 10) || 1); lineTotal.textContent = money(it.qty * it.price); totals(); });
          c3.appendChild(q);
          var s = document.createElement('small'); s.className = 'ad-muted'; s.textContent = ' stock ' + it.stock; c3.appendChild(s);
          var lineTotal = cell(tr, 'r'); lineTotal.textContent = money(it.qty * it.price);
          var c5 = cell(tr);
          var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'ad-btn ad-btn-gray'; rm.textContent = 'Remove';
          rm.addEventListener('click', function () { items.splice(idx, 1); render(); });
          c5.appendChild(rm);
          body.appendChild(tr);
        });
        $('p-empty').hidden = items.length > 0;
        $('p-table').hidden = items.length === 0;
        totals();
      }

      function totals() {
        var sub  = items.reduce(function (s, i) { return s + i.qty * i.price; }, 0);
        var auto = (sub > 0 && dEl.value) ? (dEl.value === 'Dhaka' ? rates.inside : rates.outside) : 0;
        var dv   = $('f-delivery').value.trim();
        var del  = dv !== '' ? Math.max(0, parseInt(dv, 10) || 0) : auto;
        var disc = Math.min(sub, Math.max(0, parseInt($('f-discount').value, 10) || 0));
        var bk   = Math.max(0, parseInt($('f-bkash').value, 10) || 0);
        var total = sub - disc + del;
        $('t-sub').textContent = money(sub);
        $('t-disc').textContent = '− ' + money(disc);
        $('t-del').textContent = (sub > 0 && del === 0 && (dv !== '' || dEl.value)) ? 'Free delivery' : money(del);
        $('t-total').textContent = money(total);
        $('t-bk').textContent = money(bk);
        $('t-due').textContent = money(Math.max(0, total - bk)) + (bk > total ? ' (bKash is more than the total)' : '');
      }
      ['f-delivery', 'f-discount', 'f-bkash'].forEach(function (id) { $(id).addEventListener('input', totals); });

      // The button is locked after the first click, so a double click cannot send the form twice
      $('order-form').addEventListener('submit', function () {
        var b = $('f-submit'); setTimeout(function () { b.disabled = true; b.textContent = 'Saving…'; }, 0);
      });

      render();
    })();
  </script>
@endsection
