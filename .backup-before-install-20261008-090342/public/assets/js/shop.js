// Clicking "Add to Cart" adds the product to the cart without reloading the page.
// "Buy Now" still goes straight to the checkout page.
(function () {
  var toast = document.getElementById('toast');
  var countEl = document.getElementById('cart-count');
  var timer = null;

  function showToast(msg, isError) {
    if (!toast) return;
    toast.textContent = msg;
    toast.className = 'toast' + (isError ? ' toast-err' : '');
    toast.hidden = false;
    clearTimeout(timer);
    timer = setTimeout(function () { toast.hidden = true; }, 3000);
  }

  function setCount(n) {
    if (!countEl) return;
    countEl.textContent = n;
    countEl.hidden = !(n > 0);
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('.card-actions, .buy-form')) return;

    // "Buy Now" submits normally (goes to checkout)
    var submitter = e.submitter;
    if (submitter && submitter.name === 'buy_now') return;

    e.preventDefault();

    var btn = submitter || form.querySelector('button');
    if (btn) btn.disabled = true;

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
      })
      .then(function (r) {
        if (typeof r.data.count !== 'undefined') setCount(r.data.count);
        showToast(r.data.message || (r.ok ? 'Added to cart.' : 'Something went wrong.'), !r.ok);
      })
      .catch(function () {
        // If JavaScript fails for any reason, fall back to a normal submit
        form.submit();
      })
      .then(function () {
        if (btn) btn.disabled = false;
      });
  });
})();
