// Main navigation: hamburger menu, click-to-open dropdowns (touch/mobile), hover handled by CSS.
(function () {
  var nav = document.getElementById('mainNav');
  if (!nav) return;

  var toggle = nav.querySelector('.nav-toggle');
  var items = nav.querySelectorAll('.has-sub');
  var hoverMode = window.matchMedia('(min-width: 992px) and (hover: hover)');

  function setExpanded(li, open) {
    var t = li.querySelector('.nav-trigger');
    if (t) t.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function closeAll(except) {
    items.forEach(function (li) {
      if (li !== except) {
        li.classList.remove('open');
        setExpanded(li, false);
      }
    });
  }

  function closeMenu() {
    closeAll();
    nav.classList.remove('open');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = !nav.classList.contains('open');
      nav.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!open) closeAll();
    });
  }

  nav.querySelectorAll('.nav-trigger').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      // Desktop with a mouse: CSS hover opens the menu, so the click does nothing
      if (hoverMode.matches) {
        if (e.detail !== 0) btn.blur(); // mouse click: don't keep the menu open by focus
        return;
      }
      var li = btn.parentNode;
      var open = !li.classList.contains('open');
      closeAll(li);
      li.classList.toggle('open', open);
      setExpanded(li, open);
    });
  });

  document.addEventListener('click', function (e) {
    if (!nav.contains(e.target)) closeMenu();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenu();
  });

  // Reset when the screen size / input type changes (rotate phone, resize window)
  var onChange = function () { closeMenu(); };
  if (hoverMode.addEventListener) hoverMode.addEventListener('change', onChange);
  else if (hoverMode.addListener) hoverMode.addListener(onChange);
  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) nav.classList.remove('open');
  });
})();
