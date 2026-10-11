// Product details page: quantity stepper, tabs (incl. reviews), image/video gallery, and the mobile buy bar.
// Add-to-cart itself is handled by shop.js (it listens for the .buy-form submit).
(function () {
  'use strict';

  var fmt = function (n) { return Number(n).toLocaleString('en-US'); };

  /* ---------- quantity stepper ---------- */
  var qtyBox = document.querySelector('[data-pd-qty]');
  if (qtyBox) {
    var input = qtyBox.querySelector('input');
    var dec = qtyBox.querySelector('[data-step="-1"]');
    var inc = qtyBox.querySelector('[data-step="1"]');
    var max = parseInt(qtyBox.getAttribute('data-max'), 10) || 1;
    var price = parseInt(qtyBox.getAttribute('data-price'), 10) || 0;
    var totalEl = document.querySelector('[data-pd-total]');

    var clamp = function () {
      var v = parseInt(input.value, 10);
      if (isNaN(v) || v < 1) v = 1;
      return Math.min(v, max);
    };
    var render = function (v) {
      input.value = v;
      dec.disabled = v <= 1;
      inc.disabled = v >= max;
      if (totalEl) totalEl.textContent = fmt(v * price);
    };

    dec.addEventListener('click', function () { render(Math.max(1, clamp() - 1)); });
    inc.addEventListener('click', function () { render(Math.min(max, clamp() + 1)); });
    input.addEventListener('input', function () {
      var v = parseInt(input.value, 10);
      if (!isNaN(v) && v >= 1) { if (totalEl) totalEl.textContent = fmt(Math.min(v, max) * price); }
    });
    input.addEventListener('change', function () { render(clamp()); });

    // Runs before shop.js (the form listener fires before the document listener)
    var form = qtyBox.closest('form');
    if (form) form.addEventListener('submit', function () { render(clamp()); });

    render(clamp());
  }

  /* ---------- tabs ---------- */
  var tabs = document.querySelector('[data-pd-tabs]');
  if (tabs) {
    var buttons = Array.prototype.slice.call(tabs.querySelectorAll('[role="tab"]'));
    var panels = buttons.map(function (b) { return document.getElementById(b.getAttribute('aria-controls')); });

    var select = function (idx, focus) {
      buttons.forEach(function (b, i) {
        var on = i === idx;
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        b.tabIndex = on ? 0 : -1;
        if (panels[i]) panels[i].hidden = !on;
      });
      if (focus) buttons[idx].focus();
    };

    buttons.forEach(function (b, i) {
      b.addEventListener('click', function () { select(i, false); });
      b.addEventListener('keydown', function (e) {
        var next = null;
        if (e.key === 'ArrowRight') next = (i + 1) % buttons.length;
        else if (e.key === 'ArrowLeft') next = (i - 1 + buttons.length) % buttons.length;
        else if (e.key === 'Home') next = 0;
        else if (e.key === 'End') next = buttons.length - 1;
        if (next !== null) { e.preventDefault(); select(next, true); }
      });
    });

    tabs.classList.add('is-ready');
    select(0, false);

    // "Reviews" links (and #reviews in the address, or a just-sent review) open the Reviews tab
    var openReviews = function () {
      for (var k = 0; k < buttons.length; k++) {
        if (buttons[k].id === 'pd-tab-reviews') { select(k, false); return; }
      }
    };
    if (window.location.hash === '#reviews' || document.querySelector('[data-pd-open-reviews]')) openReviews();
    window.addEventListener('hashchange', function () { if (window.location.hash === '#reviews') openReviews(); });
    Array.prototype.forEach.call(document.querySelectorAll('[data-pd-goto-reviews]'), function (a) {
      a.addEventListener('click', openReviews);
    });
  }

  /* ---------- gallery: images + YouTube videos, thumbnails, prev/next, swipe, hover zoom, full-screen view ---------- */
  var gal = document.querySelector('[data-pd-gallery]');
  var box = document.querySelector('[data-pd-lightbox]');
  if (gal) {
    var items = [];
    try { items = JSON.parse(gal.getAttribute('data-items') || '[]'); } catch (err) { items = []; }
    var pageTitle = gal.getAttribute('data-title') || 'Product';

    var main = gal.querySelector('[data-pd-main]');
    var zoom = gal.querySelector('[data-pd-zoom]');
    var stage = gal.querySelector('[data-pd-video]');
    var playBtn = gal.querySelector('[data-pd-play]');
    var vThumb = gal.querySelector('[data-pd-video-thumb]');
    var counter = gal.querySelector('[data-pd-counter]');
    var thumbs = Array.prototype.slice.call(gal.querySelectorAll('[data-pd-thumb]'));
    var current = 0;

    // positions of the image items (the full-screen view only walks through images)
    var imgIdx = [];
    items.forEach(function (it, k) { if (it.type === 'image') imgIdx.push(k); });

    // put the play button (thumbnail) back; this also stops a video that is playing
    var resetVideo = function () {
      var f = stage && stage.querySelector('iframe');
      if (f) f.parentNode.removeChild(f);
      if (playBtn) playBtn.hidden = false;
    };

    var show = function (i) {
      if (!items.length) return;
      current = (i + items.length) % items.length;
      var it = items[current];
      resetVideo();

      if (it.type === 'video') {
        if (zoom) zoom.hidden = true;
        if (stage) stage.hidden = false;
        if (vThumb) vThumb.src = it.hq;
      } else {
        if (stage) stage.hidden = true;
        if (zoom) zoom.hidden = false;
        if (main) main.src = it.src;
      }

      if (counter) counter.textContent = (current + 1) + ' / ' + items.length;
      thumbs.forEach(function (t, k) {
        var on = k === current;
        t.classList.toggle('is-active', on);
        if (on) t.setAttribute('aria-current', 'true'); else t.removeAttribute('aria-current');
      });
      var active = thumbs[current];
      if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    };

    // click the thumbnail / play button: load the YouTube player (nothing is loaded from YouTube before this)
    if (playBtn && stage) {
      playBtn.addEventListener('click', function () {
        var it = items[current];
        if (!it || it.type !== 'video') return;
        var f = document.createElement('iframe');
        f.src = it.embed;
        f.title = pageTitle + ' — video';
        f.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen';
        f.setAttribute('allowfullscreen', '');
        f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        playBtn.hidden = true;
        stage.appendChild(f);
      });
    }

    thumbs.forEach(function (t, k) { t.addEventListener('click', function () { show(k); }); });
    var prev = gal.querySelector('[data-pd-prev]');
    var next = gal.querySelector('[data-pd-next]');
    if (prev) prev.addEventListener('click', function () { show(current - 1); });
    if (next) next.addEventListener('click', function () { show(current + 1); });

    // swipe left / right
    var swipe = function (el, onLeft, onRight) {
      var x0 = null;
      el.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
      el.addEventListener('touchend', function (e) {
        if (x0 === null) return;
        var dx = e.changedTouches[0].clientX - x0;
        x0 = null;
        if (Math.abs(dx) > 45 && items.length > 1) { if (dx < 0) onLeft(); else onRight(); }
      }, { passive: true });
    };
    if (zoom) swipe(zoom, function () { show(current + 1); }, function () { show(current - 1); });

    // hover zoom (mouse devices only)
    if (zoom && main && window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      zoom.addEventListener('mouseenter', function () { zoom.classList.add('is-zoomed'); });
      zoom.addEventListener('mouseleave', function () { zoom.classList.remove('is-zoomed'); main.style.transformOrigin = ''; });
      zoom.addEventListener('mousemove', function (e) {
        var r = zoom.getBoundingClientRect();
        main.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
      });
    }

    // full-screen view (images only)
    if (zoom && box) {
      var boxImg = box.querySelector('img');
      var closeBtn = box.querySelector('[data-pd-close]');
      var lbPrev = box.querySelector('[data-pd-lb-prev]');
      var lbNext = box.querySelector('[data-pd-lb-next]');

      var lbStep = function (dir) {
        var pos = imgIdx.indexOf(current);
        if (pos < 0 || imgIdx.length < 2) return;
        show(imgIdx[(pos + dir + imgIdx.length) % imgIdx.length]);
        boxImg.src = items[current].src;
      };
      var open = function () {
        if (!items[current] || items[current].type !== 'image') return;
        boxImg.src = items[current].src;
        box.hidden = false;
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
      };
      var close = function () {
        box.hidden = true;
        boxImg.removeAttribute('src');
        document.body.style.overflow = '';
        if (!zoom.hidden) zoom.focus();
      };

      zoom.addEventListener('click', open);
      closeBtn.addEventListener('click', close);
      if (lbPrev) lbPrev.addEventListener('click', function (e) { e.stopPropagation(); lbStep(-1); });
      if (lbNext) lbNext.addEventListener('click', function (e) { e.stopPropagation(); lbStep(1); });
      box.addEventListener('click', function (e) { if (e.target === box) close(); });
      swipe(box, function () { lbStep(1); }, function () { lbStep(-1); });
      document.addEventListener('keydown', function (e) {
        if (box.hidden) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowRight') lbStep(1);
        else if (e.key === 'ArrowLeft') lbStep(-1);
      });
    }

    show(0);
  }

  /* ---------- mobile buy bar: show it while the main buttons are off screen ---------- */
  var bar = document.querySelector('[data-pd-bar]');
  var actions = document.querySelector('[data-pd-actions]');
  if (bar && actions) {
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        bar.classList.toggle('is-visible', !entries[0].isIntersecting);
      }, { threshold: 0 }).observe(actions);
    } else {
      bar.classList.add('is-visible');
    }
  }
})();
