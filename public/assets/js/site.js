/* Pizza Slice Pezinok – public site behaviour.
   Cart lives in the "ps_cart" cookie ("id:qty:t1.t2|id:qty") so PHP can render it server-side
   (no layout shift) and everything also works without JavaScript. The server re-prices
   every order, prices here are only for display. */
(function () {
  'use strict';

  var MAX_LINES = 30;
  var dataEl = document.getElementById('ps-data');
  var DATA = dataEl ? JSON.parse(dataEl.textContent) : null;

  /* ---------- helpers ---------- */
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function money(c) { return (c / 100).toFixed(2).replace('.', ',') + ' €'; }
  function plural(n, one, few, many) { return n === 1 ? one : (n >= 2 && n <= 4 ? few : many); }

  /* ---------- motion helpers (transform/opacity only → never moves the layout) ---------- */
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var EASE = 'cubic-bezier(.22, 1, .36, 1)';
  var SPRING = 'cubic-bezier(.34, 1.56, .64, 1)';
  function anim(el, frames, opts) {
    if (reduced || !el || !el.animate) return null;
    return el.animate(frames, opts);
  }
  function bump(el) { anim(el, [{ transform: 'scale(1)' }, { transform: 'scale(1.35)' }, { transform: 'scale(1)' }], { duration: 420, easing: SPRING }); }
  function popIn(el) { anim(el, [{ opacity: 0, transform: 'scale(.92)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: EASE }); }
  function visible(el) { if (!el) return false; var r = el.getBoundingClientRect(); return r.width > 0 && r.bottom > 0 && r.top < innerHeight; }

  /* a little pizza slice (or cup) flies from the button into the cart */
  function flyToCart(fromEl, icon) {
    var target = [$('[data-cart-go]'), $('[data-cart-link]')].filter(visible)[0];
    if (reduced || !fromEl || !target || !document.body.animate) return;
    var a = fromEl.getBoundingClientRect();
    var b = target.getBoundingClientRect();
    var x0 = a.left + a.width / 2 - 19, y0 = a.top + a.height / 2 - 19;
    var x1 = b.left + b.width / 2 - 19, y1 = b.top + b.height / 2 - 19;
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('class', 'fly');
    svg.setAttribute('aria-hidden', 'true');
    var use = document.createElementNS(ns, 'use');
    use.setAttribute('href', '#i-' + (icon || 'slice'));
    svg.appendChild(use);
    document.body.appendChild(svg);
    var midX = (x0 + x1) / 2, midY = Math.min(y0, y1) - 90;
    svg.animate([
      { transform: 'translate(' + x0 + 'px,' + y0 + 'px) scale(.6) rotate(0deg)', opacity: 0 },
      { transform: 'translate(' + x0 + 'px,' + (y0 - 20) + 'px) scale(1.1) rotate(-20deg)', opacity: 1, offset: .15 },
      { transform: 'translate(' + midX + 'px,' + midY + 'px) scale(1) rotate(120deg)', opacity: 1, offset: .55 },
      { transform: 'translate(' + x1 + 'px,' + y1 + 'px) scale(.35) rotate(300deg)', opacity: .4 }
    ], { duration: 720, easing: 'cubic-bezier(.45, 0, .25, 1)' }).onfinish = function () {
      svg.remove();
      bump(target);
    };
  }
  function iconFor(el) {
    var item = el && el.closest('.item');
    var ico = item && item.querySelector('.thumb use');
    return ico ? ico.getAttribute('href').replace('#i-', '') : 'slice';
  }

  /* ---------- cart cookie ---------- */
  function readCart() {
    var m = document.cookie.match(/(?:^|;\s*)ps_cart=([^;]*)/);
    if (!m) return [];
    var lines = [];
    decodeURIComponent(m[1]).split('|').forEach(function (chunk) {
      var p = chunk.match(/^(\d{1,9}):(\d{1,2})(?::([\d.]*))?$/);
      if (!p || +p[2] < 1) return;
      var tops = (p[3] || '').split('.').filter(Boolean).map(Number).sort(function (a, b) { return a - b; });
      lines.push({ id: +p[1], qty: +p[2], tops: tops });
    });
    return lines.slice(0, MAX_LINES);
  }
  function writeCart(lines) {
    var v = lines.map(function (l) { return l.id + ':' + l.qty + (l.tops.length ? ':' + l.tops.join('.') : ''); }).join('|');
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = 'ps_cart=' + v + '; Path=/; SameSite=Lax' + secure + (v ? '; Max-Age=172800' : '; Max-Age=0');
    // checkout form carries the cart the customer is looking at; the server rejects a mismatch
    $$('input[name="cart"]').forEach(function (i) { i.value = v; });
    refreshCartUI(lines);
  }
  function keyOf(l) { return l.id + ':' + l.tops.join('.'); }
  function maxQty() { return DATA ? DATA.maxQty : 20; }
  function count(lines) { return lines.reduce(function (n, l) { return n + l.qty; }, 0); }
  function unitPrice(l) {
    if (!DATA || !DATA.items[l.id]) return 0;
    return l.tops.reduce(function (s, t) { return s + (DATA.toppings[t] ? DATA.toppings[t].p : 0); }, DATA.items[l.id].p);
  }
  function subtotal(lines) { return lines.reduce(function (s, l) { return s + unitPrice(l) * l.qty; }, 0); }

  function addLine(id, qty, tops) {
    var lines = readCart();
    tops = (tops || []).slice().sort(function (a, b) { return a - b; });
    var k = id + ':' + tops.join('.');
    var found = lines.filter(function (l) { return keyOf(l) === k; })[0];
    if (found) found.qty = Math.min(maxQty(), found.qty + qty);
    else if (lines.length < MAX_LINES) lines.push({ id: id, qty: Math.min(maxQty(), qty), tops: tops });
    else { toast('Košík je plný'); return lines; }
    writeCart(lines);
    return lines;
  }
  function changeLine(key, op) {
    var lines = readCart();
    lines = lines.filter(function (l) {
      if (keyOf(l) !== key) return true;
      if (op === 'inc') l.qty = Math.min(maxQty(), l.qty + 1);
      else if (op === 'dec' && l.qty > 1) l.qty -= 1;
      else return false;
      return true;
    });
    writeCart(lines);
    return lines;
  }

  /* ---------- shared UI ---------- */
  var lastCount = null;
  function refreshCartUI(lines) {
    var n = count(lines);
    var grew = lastCount !== null && n > lastCount;
    lastCount = n;
    $$('[data-cart-count]').forEach(function (b) { b.textContent = n; b.hidden = n === 0; if (grew) setTimeout(function () { bump(b); }, 650); });
    $$('[data-cart-link]').forEach(function (a) { a.setAttribute('aria-label', 'Košík – ' + n + ' ks'); });
    var total = $('[data-cart-total]');
    if (total && DATA) {
      var newTotal = money(subtotal(lines));
      if (total.textContent !== newTotal) { total.textContent = newTotal; bump(total); }
      $('[data-cart-items]').textContent = n + ' ' + plural(n, 'položka', 'položky', 'položiek') + ' v košíku';
      var go = $('[data-cart-go]');
      go.classList.toggle('is-disabled', n === 0);
      if (n === 0) go.setAttribute('aria-disabled', 'true'); else go.removeAttribute('aria-disabled');
    }
  }

  var toastTimer;
  function toast(msg) {
    var t = $('[data-toast]');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('is-on'); }, 1800);
  }

  /* ---------- menu page ---------- */
  function plainQty(id) {
    var l = readCart().filter(function (x) { return x.id === id && x.tops.length === 0; })[0];
    return l ? l.qty : 0;
  }
  function syncStepper(form, animate) {
    var id = +form.getAttribute('data-id');
    var q = plainQty(id);
    var add = $('[data-add-plain]', form), step = $('[data-stepper]', form), out = $('[data-qty-out]', form);
    var swapped = add.hidden !== (q > 0);
    add.hidden = q > 0;
    step.hidden = q === 0;
    if (out.textContent !== String(q)) { out.textContent = q; if (animate && !swapped) bump(out); }
    if (animate && swapped) popIn(q > 0 ? step : add);
  }

  function initMenu() {
    if (!DATA) return;
    var dlg = $('#topdlg');
    var current = null;
    var qty = 1;

    function dlgTotal() {
      var tops = $$('input[name="t"]:checked', dlg).map(function (i) { return +i.value; });
      var unit = unitPrice({ id: current, tops: tops });
      $('[data-dlg-total]', dlg).textContent = money(unit * qty);
      $('[data-dlg-qty]', dlg).textContent = qty;
      var full = tops.length >= DATA.maxTop;
      $$('input[name="t"]', dlg).forEach(function (i) { i.disabled = full && !i.checked; });
    }

    function openDialog(id) {
      current = id;
      qty = 1;
      $('[data-dlg-name]', dlg).textContent = DATA.items[id].n;
      $('[data-dlg-price]', dlg).textContent = money(DATA.items[id].p);
      $$('input[name="t"]', dlg).forEach(function (i) { i.checked = false; i.disabled = false; });
      dlgTotal();
      if (typeof dlg.showModal === 'function') dlg.showModal(); else addLine(id, 1, []);
    }

    if (dlg) {
      dlg.addEventListener('change', dlgTotal);
      $('[data-dlg-inc]', dlg).addEventListener('click', function () { if (qty < maxQty()) { qty++; dlgTotal(); } });
      $('[data-dlg-dec]', dlg).addEventListener('click', function () { if (qty > 1) { qty--; dlgTotal(); } });
      // add on submit (before the sheet closes), so the cart is saved before anything else can happen
      $('[data-dlg-form]', dlg).addEventListener('submit', function (e) {
        var btn = e.submitter || document.activeElement;
        if (!btn || btn.value !== 'add' || !current) return;
        var tops = $$('input[name="t"]:checked', dlg).map(function (i) { return +i.value; });
        var from = $('.dlg-add', dlg).getBoundingClientRect();
        addLine(current, qty, tops);
        toast('Pridané do košíka: ' + qty + '× ' + DATA.items[current].n);
        // fly from where the add button was, once the sheet is out of the way
        var ghost = { getBoundingClientRect: function () { return from; } };
        setTimeout(function () { flyToCart(ghost, 'slice'); }, 180);
      });
      // tap on the dimmed backdrop closes the sheet
      dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close('cancel'); });
    }

    $$('form[data-add]').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var id = +f.getAttribute('data-id');
        if (DATA.items[id] && DATA.items[id].t && Object.keys(DATA.toppings).length && dlg) openDialog(id);
        else { var b = $('button', f); addLine(id, 1, []); toast('Pridané do košíka'); flyToCart(b, iconFor(b)); }
      });
    });

    $$('form[data-qty]').forEach(function (f) {
      var id = +f.getAttribute('data-id');
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = e.submitter || document.activeElement;
        var op = btn && btn.value ? btn.value.split('|')[0] : 'add';
        if (op === 'add' || op === 'inc') flyToCart(btn, iconFor(f));
        if (op === 'add') addLine(id, 1, []);
        else changeLine(id + ':', op);
        syncStepper(f, true);
        // keep keyboard focus inside the control when the button swaps
        var target = op === 'add' || plainQty(id) > 0 ? $('[data-inc]', f) : $('[data-add-plain]', f);
        if (btn && btn === document.activeElement && target) target.focus({ preventScroll: true });
      });
    });

    // cookie may have changed in another tab / via back-forward cache
    window.addEventListener('pageshow', function () {
      $$('form[data-qty]').forEach(function (f) { syncStepper(f, false); });
      refreshCartUI(readCart());
    });

    // category chips follow the scroll position
    var chips = $('[data-chips]');
    if (chips && 'IntersectionObserver' in window) {
      var links = $$('a', chips);
      var byId = {};
      links.forEach(function (a) { byId[a.getAttribute('href').slice(1)] = a; });
      var setActive = function (id) {
        var a = byId[id];
        if (!a || a.getAttribute('aria-current') === 'true') return;
        links.forEach(function (x) { x.removeAttribute('aria-current'); });
        a.setAttribute('aria-current', 'true');
        var left = a.offsetLeft - chips.clientWidth / 2 + a.offsetWidth / 2;
        chips.scrollTo({ left: Math.max(0, left), behavior: reduced ? 'auto' : 'smooth' });
      };
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) setActive(en.target.id); });
      }, { rootMargin: '-30% 0px -60% 0px' });
      Object.keys(byId).forEach(function (id) { var sec = document.getElementById(id); if (sec) io.observe(sec); });
    }
  }

  /* ---------- checkout ---------- */
  function initCheckout() {
    var co = $('[data-checkout]');
    if (!co || !DATA) return;
    var fee = +co.getAttribute('data-fee') || 0;
    var coupon = null;
    try { coupon = JSON.parse(co.getAttribute('data-coupon') || 'null'); } catch (e) { coupon = null; }

    // same integer maths as the server (app/lib/coupons.php), so the preview matches to the cent
    function couponDiscount(sub, mode) {
      if (!coupon || sub < coupon.min) return 0;
      if (coupon.type === 'percent') return Math.floor((sub * Math.min(100, coupon.value) + 50) / 100);
      if (coupon.type === 'amount') return Math.min(coupon.value, sub);
      if (coupon.type === 'free_delivery') return mode === 'delivery' ? fee : 0;
      return 0;
    }

    function totals() {
      var lines = readCart();
      var sub = subtotal(lines);
      $$('[data-subtotal]').forEach(function (el) { el.textContent = money(sub); });
      ['pickup', 'delivery'].forEach(function (mode) {
        var d = couponDiscount(sub, mode);
        var t = Math.max(0, sub - d + (mode === 'delivery' ? fee : 0));
        $$('[data-total-' + mode + ']').forEach(function (el) { if (el.textContent !== money(t)) { el.textContent = money(t); bump(el); } });
        $$('[data-disc-' + mode + ']').forEach(function (el) {
          el.textContent = coupon && coupon.type === 'free_delivery' && mode === 'pickup' ? 'len pri donáške' : '−' + money(d);
        });
      });
      var msg = $('[data-coupon-msg]');
      if (msg && coupon && !msg.classList.contains('is-err')) {
        msg.textContent = sub < coupon.min ? 'Kód platí pri objednávke od ' + money(coupon.min) + '.' : '';
      }
      return lines;
    }

    /* ----- coupon: apply / remove without reloading (the typed form stays as it is) ----- */
    var cForm = $('[data-coupon-form]');
    var cApplied = $('[data-coupon-applied]');
    var cRemove = $('[data-coupon-remove]');
    var cMsg = $('[data-coupon-msg]');
    var dRow = $('[data-discount-row]');
    function setCoupon(c) {
      coupon = c;
      cForm.hidden = !!c;
      cApplied.hidden = !c;
      dRow.hidden = !c;
      $$('[data-coupon-code]').forEach(function (el) { el.textContent = c ? c.code : ''; });
      $('[data-coupon-label]').textContent = c ? c.label : '';
      $$('[data-coupon-field]').forEach(function (i) { i.value = c ? c.code : ''; });
      cMsg.classList.remove('is-err');
      cMsg.textContent = '';
      totals();
      popIn(c ? cApplied : cForm);
      if (c) anim(dRow, [{ opacity: 0, transform: 'translateY(-4px)' }, { opacity: 1, transform: 'none' }], { duration: 300, easing: EASE });
    }
    function send(form) {
      return fetch('/kosik', { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); });
    }
    function couponError(text) {
      cMsg.textContent = text;
      cMsg.classList.add('is-err');
      anim($('input[name="code"]', cForm), [{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' }], { duration: 320 });
    }
    if (cForm && cApplied && cRemove) {
      cForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var input = $('input[name="code"]', cForm);
        var btn = $('button', cForm);
        if (!input.value.trim()) { input.focus(); return; }
        if (btn.getAttribute('aria-busy') === 'true') return;
        btn.setAttribute('aria-busy', 'true');
        send(cForm)
          .then(function (d) {
            if (d.ok) { input.value = ''; setCoupon(d.coupon); }
            else couponError(d.error || 'Kód sa nepodarilo použiť.');
          })
          .catch(function () { couponError('Kód sa nepodarilo overiť. Skontrolujte pripojenie a skúste znova.'); })
          .then(function () { btn.removeAttribute('aria-busy'); });
      });
      cRemove.addEventListener('submit', function (e) {
        e.preventDefault();
        send(cRemove).then(function (d) { if (d.ok) setCoupon(null); }).catch(function () { cRemove.submit(); });
      });
    }

    var cartForm = $('[data-cartform]');
    cartForm.addEventListener('submit', function (e) {
      var btn = e.submitter || document.activeElement;
      if (!btn || !btn.value) return;
      e.preventDefault();
      var parts = btn.value.split('|');
      var lines = changeLine(parts[1], parts[0]);
      if (!lines.length) { location.reload(); return; }
      var li = cartForm.querySelector('[data-line="' + parts[1] + '"]');
      var line = lines.filter(function (l) { return keyOf(l) === parts[1]; })[0];
      if (!line) {
        var next = li.nextElementSibling || li.previousElementSibling;
        var done = function () {
          li.remove();
          if (next) { var b = next.querySelector('.line-del'); if (b) b.focus({ preventScroll: true }); }
        };
        // fold the row away smoothly instead of letting everything below jump up
        var h = li.offsetHeight;
        li.style.overflow = 'hidden';
        var a = anim(li, [
          { opacity: 1, height: h + 'px', paddingTop: '14px', paddingBottom: '14px' },
          { opacity: 0, height: '0px', paddingTop: '0px', paddingBottom: '0px' }
        ], { duration: 260, easing: EASE });
        if (a) a.onfinish = done; else done();
      } else {
        $('[data-line-qty]', li).textContent = line.qty;
        bump($('[data-line-qty]', li));
        $('[data-line-total]', li).textContent = money(unitPrice(line) * line.qty);
      }
      totals();
    });

    $$('input[name="fulfillment"]').forEach(function (r) {
      r.addEventListener('change', function () { co.classList.toggle('is-delivery', r.value === 'delivery' && r.checked); });
    });

    var orderForm = $('[data-orderform]');
    if (orderForm) {
      orderForm.addEventListener('submit', function (e) {
        var btn = $('[data-submit]', orderForm);
        if (btn.getAttribute('aria-busy') === 'true') { e.preventDefault(); return; }
        btn.setAttribute('aria-busy', 'true');
        // disabled look without changing size; re-enabled if the page is restored from cache
        btn.classList.add('is-disabled');
      });
      window.addEventListener('pageshow', function () {
        var btn = $('[data-submit]', orderForm);
        btn.removeAttribute('aria-busy');
        btn.classList.remove('is-disabled');
      });
    }
  }

  /* ---------- order status (confirmation page) ---------- */
  function initOrderStatus() {
    var box = $('[data-order]');
    if (!box) return;
    var code = box.getAttribute('data-code');
    var token = box.getAttribute('data-token');
    var status = box.getAttribute('data-status');
    var delay = 15000;

    function render(st, text) {
      status = st;
      box.setAttribute('data-status', st);
      $('[data-status-text]', box).textContent = text;
      var prog = $('[data-progress]', box);
      var steps = $$('li', prog);
      anim($('[data-status-text]', box), [{ backgroundColor: '#f7c9c3' }, { backgroundColor: '#fcebe7' }], { duration: 1200, easing: 'ease-out' });
      var idx = steps.map(function (li) { return li.getAttribute('data-step'); }).indexOf(st);
      prog.classList.toggle('is-cancelled', st === 'cancelled');
      steps.forEach(function (li, i) {
        li.className = st === 'cancelled' || idx < 0 ? '' : (i < idx || st === 'completed' ? 'is-done' : (i === idx ? 'is-current' : ''));
      });
    }
    function poll() {
      if (status === 'completed' || status === 'cancelled') return;
      if (document.hidden) { setTimeout(poll, delay); return; }
      fetch('/api/status.php?code=' + encodeURIComponent(code) + '&k=' + encodeURIComponent(token), { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) {
          if (r.status === 410) { location.reload(); throw new Error('expired'); }
          return r.ok ? r.json() : null;
        })
        .then(function (d) { if (d && d.status && d.status !== status) render(d.status, d.text); })
        .catch(function () {})
        .then(function () { setTimeout(poll, delay); });
    }
    setTimeout(poll, delay);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) delay = 15000; });
  }

  initMenu();
  initCheckout();
  initOrderStatus();
})();
