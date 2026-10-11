// Checkout page: district/upazila lists, quantity stepper, remove item, coupon, live totals.
// The server always recalculates everything when the order is placed — this script only updates what the customer sees.
(function () {
  var form = document.getElementById('checkout-form');
  var dataEl = document.getElementById('co-data');
  if (!form || !dataEl) return;

  var D = JSON.parse(dataEl.textContent);
  var tokenEl = form.querySelector('input[name="_token"]');
  var token = tokenEl ? tokenEl.value : '';

  var state = {
    subtotal: D.subtotal || 0,
    discount: D.discount || 0,
    district: D.district || ''
  };

  var $ = function (id) { return document.getElementById(id); };
  var elDistrict = $('f-district'), elUpazila = $('f-upazila');
  var elZone = $('co-zone'), elZoneTitle = $('co-zone-title'), elZoneText = $('co-zone-text');
  var elItems = $('co-items');

  function money(n) { return '৳ ' + Number(n).toLocaleString('en-US'); }
  function urlFor(base, id) { return base.replace(/\/0$/, '/' + id); }

  function flash(el) {
    if (!el) return;
    el.classList.remove('co-flash');
    void el.offsetWidth;
    el.classList.add('co-flash');
  }

  // ---------- Districts & upazilas ----------
  function fillDistricts() {
    D.locations.forEach(function (d) {
      var o = document.createElement('option');
      o.value = d.n;
      o.textContent = d.n + (d.b ? ' — ' + d.b : '');
      elDistrict.appendChild(o);
    });
    if (state.district) elDistrict.value = state.district;
  }

  function fillUpazilas(selected) {
    elUpazila.innerHTML = '';
    var d = D.locations.filter(function (x) { return x.n === elDistrict.value; })[0];

    if (!d) {
      elUpazila.disabled = true;
      elUpazila.innerHTML = '<option value="">Select district first</option>';
      return;
    }

    var first = document.createElement('option');
    first.value = '';
    first.textContent = 'Select upazila / thana';
    elUpazila.appendChild(first);

    d.u.forEach(function (u) {
      var o = document.createElement('option');
      o.value = u[0];
      o.textContent = u[0] + (u[1] ? ' — ' + u[1] : '');
      elUpazila.appendChild(o);
    });

    elUpazila.disabled = false;
    if (selected) elUpazila.value = selected;
  }

  // ---------- Totals ----------
  function deliveryCharge() {
    if (!state.district) return null;
    return state.district === 'Dhaka' ? D.rates.inside : D.rates.outside;
  }

  function render() {
    var charge = deliveryCharge();
    var total = state.subtotal - state.discount + (charge || 0);

    $('t-subtotal').textContent = money(state.subtotal);

    // Discount row is always visible (shows ৳ 0 until a coupon is applied)
    $('t-discount-row').classList.toggle('has-disc', state.discount > 0);
    $('t-discount').textContent = state.discount > 0 ? '− ' + money(state.discount) : money(0);

    var dEl = $('t-delivery');
    if (charge === null) {
      dEl.textContent = 'Select district';
      dEl.className = 'muted';
    } else {
      dEl.textContent = charge > 0 ? money(charge) : 'Free';
      dEl.className = '';
    }

    $('t-total').textContent = money(total);
    $('t-btn-total').textContent = money(total);
    flash($('t-total'));

    // delivery zone box next to the city field
    if (charge === null) {
      elZone.dataset.zone = '';
      elZoneTitle.textContent = 'Delivery charge';
      elZoneText.textContent = 'Select your district to see the charge';
    } else {
      var inside = state.district === 'Dhaka';
      elZone.dataset.zone = inside ? 'inside' : 'outside';
      elZoneTitle.textContent = inside ? 'Inside Dhaka' : 'Outside Dhaka';
      elZoneText.textContent = 'Delivery charge: ' + (charge > 0 ? money(charge) : 'Free');
    }
  }

  // ---------- Talking to the server ----------
  function post(url, body) {
    var fd = new FormData();
    fd.append('_token', token);
    Object.keys(body || {}).forEach(function (k) { fd.append(k, body[k]); });

    return fetch(url, {
      method: 'POST',
      body: fd,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().then(function (data) { return { ok: res.ok, data: data }; });
    });
  }

  function applyCart(data) {
    if (data.empty) {
      window.location.href = data.redirect;
      return;
    }

    state.subtotal = data.subtotal;
    state.discount = data.discount;

    var live = {};
    data.items.forEach(function (it) { live[it.id] = it; });

    Array.prototype.slice.call(elItems.querySelectorAll('.co-item')).forEach(function (row) {
      var it = live[row.dataset.id];
      if (!it) { row.remove(); return; }
      var stock = parseInt(row.dataset.stock, 10);
      row.querySelector('.co-qty').textContent = it.qty;
      row.querySelector('.co-line').textContent = money(it.total);
      row.querySelector('[data-act="minus"]').disabled = it.qty <= 1;
      row.querySelector('[data-act="plus"]').disabled = it.qty >= stock;
    });

    $('co-count').textContent = data.count + (data.count === 1 ? ' item' : ' items');

    // header cart badge
    var badge = document.getElementById('cart-count');
    if (badge) { badge.textContent = data.count; badge.hidden = !(data.count > 0); }

    setCoupon(data.coupon);
    if (data.notice) couponMsg(data.notice, false);

    render();
  }

  // ---------- Items: stepper & remove ----------
  elItems.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-act]');
    if (!btn || btn.disabled) return;

    var row = btn.closest('.co-item');
    var id = row.dataset.id;
    var act = btn.dataset.act;
    var qty = parseInt(row.querySelector('.co-qty').textContent, 10) || 1;

    row.classList.add('is-busy');
    var req;

    if (act === 'remove') {
      if (!window.confirm('Remove this product from your order?')) { row.classList.remove('is-busy'); return; }
      req = post(urlFor(form.dataset.removeUrl, id));
    } else {
      req = post(urlFor(form.dataset.qtyUrl, id), { quantity: act === 'plus' ? qty + 1 : qty - 1 });
    }

    req.then(function (r) {
      if (act === 'remove' && !r.data.empty) {
        row.classList.add('is-leaving');
        setTimeout(function () { applyCart(r.data); }, 220);
      } else {
        applyCart(r.data);
      }
    }).catch(function () {
      window.location.reload();
    }).then(function () {
      row.classList.remove('is-busy');
    });
  });

  // ---------- Coupon ----------
  var cForm = $('co-coupon-form'), cOn = $('co-coupon-on'), cInput = $('f-coupon'),
      cApply = $('co-coupon-apply'), cMsg = $('co-coupon-msg');

  function couponMsg(text, ok) {
    if (!text) { cMsg.hidden = true; return; }
    cMsg.textContent = text;
    cMsg.className = 'co-coupon-msg ' + (ok ? 'ok' : 'bad');
    cMsg.hidden = false;
  }

  function setCoupon(c) {
    if (c) {
      $('co-coupon-code').textContent = c.code;
      $('co-coupon-label').textContent = c.label + ' applied';
      cForm.hidden = true;
      cOn.hidden = false;
    } else {
      cForm.hidden = false;
      cOn.hidden = true;
    }
  }

  function applyCoupon() {
    var code = cInput.value.trim();
    if (!code) { couponMsg('Please enter a coupon code.', false); cInput.focus(); return; }

    cApply.disabled = true;
    post(form.dataset.couponUrl, { code: code }).then(function (r) {
      if (r.data.empty) { applyCart(r.data); return; }
      if (r.ok) {
        cInput.value = '';
        applyCart(r.data);
        couponMsg(r.data.message, true);
      } else {
        couponMsg(r.data.message || 'This coupon could not be applied.', false);
      }
    }).catch(function () {
      couponMsg('Something went wrong. Please try again.', false);
    }).then(function () {
      cApply.disabled = false;
    });
  }

  cApply.addEventListener('click', applyCoupon);
  // Enter in the coupon box applies the coupon — it must not place the order
  cInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); }
  });

  $('co-coupon-remove').addEventListener('click', function () {
    post(form.dataset.couponRemoveUrl).then(function (r) {
      applyCart(r.data);
      couponMsg('Coupon removed.', true);
    }).catch(function () { window.location.reload(); });
  });

  // ---------- Place order ----------
  form.addEventListener('submit', function () {
    var btn = $('co-submit');
    // let the browser finish its own validation first; only lock the button for a valid form
    if (!form.checkValidity()) return;
    btn.disabled = true;
    btn.classList.add('is-loading');
  });

  // Coming back with the browser Back button: make sure the button is usable again
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      var btn = $('co-submit');
      btn.disabled = false;
      btn.classList.remove('is-loading');
    }
  });

  // ---------- Start ----------
  fillDistricts();
  fillUpazilas(D.upazila);
  elDistrict.addEventListener('change', function () {
    state.district = elDistrict.value;
    fillUpazilas('');
    render();
  });
  render();
})();
