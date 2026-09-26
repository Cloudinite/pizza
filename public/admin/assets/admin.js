/* Pizza Slice – admin app behaviour.
   Polls for new orders on every admin screen, alerts with sound / vibration / system
   notification, renders the live orders board and handles quick toggles. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrfMeta = $('meta[name="csrf-token"]');
  var CSRF = csrfMeta ? csrfMeta.content : '';
  var PAGE = document.body.getAttribute('data-page');
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };
  // preferences go into cookies so the server can draw buttons/theme in their final state (no jumps, no flash)
  function getPref(k) { var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + k + '=([^;]*)')); return m ? m[1] : null; }
  function setPref(k, v) {
    document.cookie = k + '=' + v + '; Path=/admin/; Max-Age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }
  var html = document.documentElement;
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var EASE = 'cubic-bezier(.22, 1, .36, 1)';
  function anim(el, frames, opts) { return (!reduced && el && el.animate) ? el.animate(frames, opts) : null; }

  /* ---------- toast (floating, never moves the page) ---------- */
  var toastEl = $('[data-toast]');
  var toastTimer;
  function toast(msg, ok) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.toggle('is-ok', !!ok);
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2400);
  }
  if ($('[data-flash]')) {
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2600);
    // drop ?ok=… so a reload does not show the message again
    if (history.replaceState) history.replaceState(null, '', location.pathname + location.hash);
  }

  /* ---------- light / dark theme ---------- */
  var THEME_LABEL = { light: 'svetlý', dark: 'tmavý', auto: 'podľa telefónu' };
  var darkMq = window.matchMedia ? matchMedia('(prefers-color-scheme: dark)') : null;
  function themeMode() { return html.getAttribute('data-theme') || 'auto'; }
  function paintThemeColor(mode) {
    var dark = mode === 'dark' || (mode === 'auto' && darkMq && darkMq.matches);
    $$('meta[name="theme-color"]').forEach(function (m) { m.remove(); });
    var meta = document.createElement('meta');
    meta.name = 'theme-color';
    meta.content = dark ? '#1b1a1d' : '#b41116';
    document.head.appendChild(meta);
  }
  function applyTheme(mode) {
    var apply = function () {
      if (mode === 'auto') html.removeAttribute('data-theme'); else html.setAttribute('data-theme', mode);
      paintThemeColor(mode);
      $$('[data-theme-btn]').forEach(function (b) {
        b.setAttribute('data-mode', mode);
        b.setAttribute('aria-label', 'Vzhľad: ' + THEME_LABEL[mode] + ' (ťuknutím zmeníte)');
      });
      $$('[data-theme-set]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-theme-set') === mode ? 'true' : 'false'); });
    };
    setPref('ps_theme', mode);
    if (document.startViewTransition && !reduced) document.startViewTransition(apply);
    else {
      html.classList.add('theme-anim');
      apply();
      setTimeout(function () { html.classList.remove('theme-anim'); }, 450);
    }
    toast('Vzhľad: ' + THEME_LABEL[mode]);
  }
  $$('[data-theme-btn]').forEach(function (b) {
    b.addEventListener('click', function () {
      var order = ['light', 'dark', 'auto'];
      applyTheme(order[(order.indexOf(themeMode()) + 1) % order.length]);
    });
  });
  $$('[data-theme-set]').forEach(function (b) {
    b.addEventListener('click', function () { applyTheme(b.getAttribute('data-theme-set')); });
  });
  if (darkMq && darkMq.addEventListener) darkMq.addEventListener('change', function () { if (themeMode() === 'auto') paintThemeColor('auto'); });

  function money(c) { return (c / 100).toFixed(2).replace('.', ',') + ' €'; }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
  }
  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    return fetch('/admin/api.php', { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF } })
      .then(function (r) {
        if (r.status === 401) { location.href = '/admin/login.php'; throw new Error('auth'); }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      });
  }
  function get(params) {
    return fetch('/admin/api.php?' + params, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
      if (r.status === 401) { location.href = '/admin/login.php'; throw new Error('auth'); }
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  /* ---------- PWA: service worker + install ---------- */
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).catch(function () {});
  }
  var installEvt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    installEvt = e;
    var b = $('[data-install]');
    if (b) b.hidden = false;
  });
  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var installBtn = $('[data-install]');
  if (installBtn) {
    if (isIOS && !standalone) installBtn.hidden = false;
    installBtn.addEventListener('click', function () {
      if (installEvt) {
        installEvt.prompt();
        installEvt.userChoice.then(function () { installBtn.hidden = true; installEvt = null; });
      } else if (isIOS) {
        alert('iPhone / iPad: v Safari ťuknite na ikonu Zdieľať (štvorec so šípkou) a zvoľte „Pridať na plochu“.');
      }
    });
  }

  /* ---------- confirm dialogs + quick toggles ---------- */
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  $$('input[data-toggle]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var row = inp.closest('.a-row');
      var on = inp.checked;
      if (row) row.classList.toggle('is-off', !on);
      post(inp.getAttribute('data-toggle') + '_toggle', { id: inp.getAttribute('data-id'), on: on ? '1' : '0' })
        .catch(function () {
          inp.checked = !on;
          if (row) row.classList.toggle('is-off', on);
          alert('Zmenu sa nepodarilo uložiť. Skontrolujte pripojenie.');
        });
    });
  });

  /* ---------- coupon form: value field follows the type, random code generator ---------- */
  var typeSel = $('[data-coupon-type]');
  if (typeSel) {
    var valueWrap = $('[data-coupon-value]');
    var valueLabel = $('[data-value-label]');
    var valueInput = $('input[name="value"]', valueWrap);
    typeSel.addEventListener('change', function () {
      var t = typeSel.value;
      valueWrap.hidden = t === 'free_delivery';
      valueLabel.textContent = t === 'amount' ? 'Zľava (€)' : 'Zľava (%)';
      valueInput.placeholder = t === 'amount' ? '2,00' : '10';
    });
  }
  var gen = $('[data-gen-code]');
  if (gen) {
    gen.addEventListener('click', function () {
      var abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
      var buf = new Uint32Array(6);
      (window.crypto || window.msCrypto).getRandomValues(buf);
      var code = 'PIZZA-';
      for (var i = 0; i < buf.length; i++) code += abc[buf[i] % abc.length];
      var input = $('[data-code-input]');
      input.value = code;
      anim(input, [{ backgroundColor: 'rgba(242,169,0,.35)' }, { backgroundColor: 'transparent' }], { duration: 700 });
    });
  }

  /* ---------- sound, vibration, notifications ---------- */
  var actx = null;
  var soundOn = getPref('ps_sound') === '1';
  function ensureAudio() {
    if (!actx) {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      actx = new AC();
    }
    if (actx.state === 'suspended') actx.resume();
  }
  function beep() {
    if (!soundOn || !actx) return;
    var t = actx.currentTime;
    [[0, 880], [0.2, 880], [0.4, 1320]].forEach(function (n) {
      var o = actx.createOscillator();
      var g = actx.createGain();
      o.type = 'sine';
      o.frequency.value = n[1];
      g.gain.setValueAtTime(0.0001, t + n[0]);
      g.gain.exponentialRampToValueAtTime(0.6, t + n[0] + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, t + n[0] + 0.17);
      o.connect(g);
      g.connect(actx.destination);
      o.start(t + n[0]);
      o.stop(t + n[0] + 0.2);
    });
  }
  if (soundOn) {
    // browsers only allow audio after a tap – unlock on the first interaction
    var unlock = function () { ensureAudio(); document.removeEventListener('pointerdown', unlock); };
    document.addEventListener('pointerdown', unlock);
  }
  var notifyBtn = $('[data-notify]');
  function paintNotify() {
    if (!notifyBtn) return;
    notifyBtn.classList.toggle('is-on', soundOn);
    notifyBtn.textContent = soundOn ? '🔔 Zvuk zapnutý' : '🔔 Zapnúť zvuk a upozornenia';
  }
  paintNotify();
  if (notifyBtn) {
    notifyBtn.addEventListener('click', function () {
      soundOn = !soundOn;
      setPref('ps_sound', soundOn ? '1' : '0');
      if (soundOn) {
        ensureAudio();
        beep();
        if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
      }
      paintNotify();
    });
  }
  function systemNotify(o) {
    if (!('Notification' in window) || Notification.permission !== 'granted' || !navigator.serviceWorker) return;
    navigator.serviceWorker.ready.then(function (reg) {
      reg.showNotification('Nová objednávka č. ' + o.no, {
        body: o.count + ' ks · ' + money(o.total),
        tag: 'order-' + o.id,
        renotify: true,
        icon: '/admin/assets/icon-192.png',
        badge: '/admin/assets/icon-192.png',
        vibrate: [250, 120, 250]
      });
    }).catch(function () {});
  }

  /* ---------- wake lock (keep the kitchen tablet screen on) ---------- */
  var wakeBtn = $('[data-wake]');
  var wakeLock = null;
  var wakeWanted = getPref('ps_wake') === '1';
  function paintWake() { if (wakeBtn) { wakeBtn.textContent = 'Displej nezhasína: ' + (wakeWanted ? 'zap.' : 'vyp.'); wakeBtn.classList.toggle('is-on', wakeWanted); } }
  function requestWake() {
    if (!wakeWanted || !('wakeLock' in navigator) || document.hidden) return;
    navigator.wakeLock.request('screen').then(function (l) { wakeLock = l; }).catch(function () {});
  }
  if (wakeBtn && 'wakeLock' in navigator) {
    wakeBtn.hidden = false;
    paintWake();
    wakeBtn.addEventListener('click', function () {
      wakeWanted = !wakeWanted;
      setPref('ps_wake', wakeWanted ? '1' : '0');
      if (wakeWanted) requestWake(); else if (wakeLock) { wakeLock.release(); wakeLock = null; }
      paintWake();
    });
    requestWake();
    document.addEventListener('visibilitychange', requestWake);
  }

  /* ---------- polling + new-order alerts (every admin screen) ---------- */
  var seen = [];
  try { seen = JSON.parse(store.get('ps_seen') || '[]'); } catch (e) { seen = []; }
  var baseTitle = document.title;
  var lastReminder = Date.now();

  function handlePending(d) {
    var fresh = d.new_orders.filter(function (o) { return seen.indexOf(o.id) === -1; });
    if (fresh.length) {
      beep();
      var activated = !navigator.userActivation || navigator.userActivation.hasBeenActive;
      if (navigator.vibrate && activated) navigator.vibrate([250, 120, 250]);
      fresh.forEach(systemNotify);
      seen = seen.concat(fresh.map(function (o) { return o.id; })).slice(-300);
      store.set('ps_seen', JSON.stringify(seen));
      lastReminder = Date.now();
    } else if (d.counts.new > 0 && Date.now() - lastReminder > 60000) {
      beep(); // gentle reminder every minute while orders wait for confirmation
      lastReminder = Date.now();
    }
    var n = d.counts.new;
    document.title = (n ? '(' + n + ') ' : '') + baseTitle;
    $$('[data-new-badge]').forEach(function (b) { b.textContent = n; b.hidden = n === 0; });
    if (navigator.setAppBadge) { if (n) navigator.setAppBadge(n).catch(function () {}); else navigator.clearAppBadge().catch(function () {}); }
    var ca = $('[data-count-active]');
    if (ca) ca.textContent = d.counts.active || '';
    var clock = $('[data-stat-clock]');
    if (clock) clock.textContent = d.time;
    var conn = $('[data-conn]');
    if (conn && conn.classList.contains('is-bad')) { conn.innerHTML = '<i class="spin" aria-hidden="true">↻</i> obnoviť'; conn.classList.remove('is-bad'); }
  }

  /* ---------- orders board ---------- */
  var board = $('[data-orders]');
  var view = 'active';
  var lastJson = '';
  var STATUS = { new: 'Nová', accepted: 'Pripravuje sa', ready: 'Pripravená', delivering: 'Na ceste', completed: 'Hotovo', cancelled: 'Zrušená' };
  // next-step buttons; the customer's tracking page follows every step
  function actionsFor(o) {
    var cancel = ['cancelled', 'Zrušiť', 'a-btn-danger'];
    switch (o.status) {
      case 'new': return [['accepted', 'Prijať objednávku', 'a-btn-primary'], cancel];
      case 'accepted': return [['ready', 'Pizza je hotová', 'a-btn-ok'], cancel];
      case 'ready': return o.fulfillment === 'delivery'
        ? [['delivering', '🚗 Odoslať – na ceste', 'a-btn-blue'], ['completed', '✓ Doručená', '']]
        : [['completed', '✓ Vyzdvihnutá – hotovo', 'a-btn-ok']];
      case 'delivering': return [['completed', '✓ Doručená – hotovo', 'a-btn-ok']];
      case 'completed': return [['accepted', 'Vrátiť medzi aktívne', '']];
      case 'cancelled': return [['accepted', 'Obnoviť objednávku', '']];
    }
    return [];
  }

  function card(o) {
    var c = o.customer;
    var time = o.created.slice(11, 16);
    var date = view === 'history' ? o.created.slice(8, 10) + '.' + o.created.slice(5, 7) + '. ' : '';
    var when = o.time ? 'o ' + o.time : 'čo najskôr';
    var items = o.items.map(function (i) {
      return '<li><span class="q">' + i.qty + '×</span><span class="n">' + esc(i.name) +
        (i.toppings ? '<small>+ ' + esc(i.toppings) + '</small>' : '') + '</span><span class="p">' + money(i.line) + '</span></li>';
    }).join('');
    var acts = actionsFor(o).map(function (a) {
      return '<button type="button" class="a-btn ' + a[2] + '" data-set="' + a[0] + '" data-id="' + o.id + '" data-no="' + o.no + '">' + a[1] + '</button>';
    }).join('');
    return '<article class="o-card st-' + o.status + '" data-oid="' + o.id + '">' +
      '<div class="o-head"><span class="o-no">č. ' + o.no + '</span><span class="o-code">#' + esc(o.code) + '</span>' +
      '<span class="o-st st-' + o.status + '">' + STATUS[o.status] + '</span></div>' +
      '<p class="o-meta"><span class="o-type ' + (o.fulfillment === 'delivery' ? 'is-del">🚗 Donáška' : 'is-pick">🏪 Osobný odber') + '</span> ' +
      '<span class="o-when">' + when + '</span> <span>· prijatá ' + date + time + '</span></p>' +
      '<div class="o-cust"><b>' + esc(c.name) + '</b>' +
      (c.address ? '<span class="o-addr">📍 ' + esc(c.address) + '</span>' : '') +
      '<span class="o-contact">' +
      (c.phone ? '<a class="a-btn a-btn-sm" href="tel:' + esc(c.phone) + '">📞 ' + esc(c.phone) + '</a>' : '') +
      (c.address ? '<a class="a-btn a-btn-sm" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=' +
        encodeURIComponent(c.address) + '">🧭 Navigovať</a>' : '') +
      '</span></div>' +
      '<ul class="o-items">' + items + '</ul>' +
      (c.note ? '<p class="o-note">📝 ' + esc(c.note) + '</p>' : '') +
      '<p class="o-total"><span>' + esc(o.payment) + (o.delivery ? ' · donáška ' + money(o.delivery) : '') +
      (o.discount ? ' <span class="o-coupon">🎟️ ' + esc(o.coupon) + ' −' + money(o.discount) + '</span>' : '') + '</span><b>' + money(o.total) + '</b></p>' +
      (acts ? '<div class="o-actions">' + acts + '</div>' : '') +
      '</article>';
  }

  function renderBoard(d) {
    var json = JSON.stringify([d.view, d.orders]);
    if (d.time) { var clk = $('[data-stat-clock]'); if (clk) clk.textContent = d.time; }
    if (d.stats) {
      $('[data-stat-count]').textContent = d.stats.count;
      $('[data-stat-total]').textContent = money(d.stats.total);
    }
    var tog = $('[data-ordering]');
    if (tog && typeof d.ordering === 'boolean' && document.activeElement !== tog) {
      tog.checked = d.ordering;
      paintOrdering(d.ordering);
    }
    if (json === lastJson) return;
    var first = lastJson === '';
    lastJson = json;
    flipRender(d.orders.map(card).join(''), first);
    $('[data-empty]').hidden = d.orders.length > 0;
  }

  /* re-render the board, but let cards glide to their new place instead of jumping (FLIP) */
  function flipRender(markup, first) {
    var before = {};
    var ghosts = [];
    if (!first && !reduced) {
      $$('.o-card', board).forEach(function (c) { before[c.getAttribute('data-oid')] = c; });
    }
    var rects = {};
    Object.keys(before).forEach(function (id) { rects[id] = before[id].getBoundingClientRect(); });
    board.innerHTML = markup;
    if (first || reduced) return;
    var now = {};
    $$('.o-card', board).forEach(function (c) {
      var id = c.getAttribute('data-oid');
      now[id] = true;
      var r0 = rects[id];
      if (!r0) {
        anim(c, [{ opacity: 0, transform: 'translateY(-14px) scale(.97)' }, { opacity: 1, transform: 'none' }], { duration: 420, easing: EASE });
        return;
      }
      var r1 = c.getBoundingClientRect();
      var dx = r0.left - r1.left, dy = r0.top - r1.top;
      if (dx || dy) anim(c, [{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'none' }], { duration: 380, easing: EASE });
    });
    // cards that left the list fade out where they were (as floating ghosts, so nothing jumps)
    Object.keys(rects).forEach(function (id) {
      if (now[id]) return;
      var g = before[id];
      var r = rects[id];
      g.style.position = 'fixed';
      g.style.left = r.left + 'px';
      g.style.top = r.top + 'px';
      g.style.width = r.width + 'px';
      g.style.margin = '0';
      g.style.zIndex = '15';
      g.style.pointerEvents = 'none';
      document.body.appendChild(g);
      ghosts.push(g);
      var a = anim(g, [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'scale(.94) translateY(8px)' }], { duration: 320, easing: EASE });
      if (a) a.onfinish = function () { g.remove(); }; else g.remove();
    });
  }

  var orderingCard = $('[data-ordering-card]');
  function paintOrdering(on) {
    if (orderingCard) orderingCard.classList.toggle('is-off', !on);
    var l = $('[data-ordering-label]');
    if (l) l.textContent = on ? 'Prijímame – web berie objednávky' : 'Pozastavené – web objednávky neberie';
  }

  function poll() {
    var req = board ? get('action=orders&view=' + view) : get('action=ping');
    return req.then(function (d) {
      handlePending(d);
      if (board && d.view === view) renderBoard(d);
    }).catch(function (e) {
      if (e.message === 'auth') return;
      var conn = $('[data-conn]');
      if (conn) { conn.textContent = '⚠ bez spojenia'; conn.classList.add('is-bad'); }
    });
  }

  var timer = null;
  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () { poll().then(schedule); }, document.hidden ? 30000 : (board ? 8000 : 20000));
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); schedule(); } });

  if (board) {
    var init = JSON.parse($('#orders-data').textContent);
    renderBoard(init);

    board.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-set]');
      if (!btn) return;
      var st = btn.getAttribute('data-set');
      if (st === 'cancelled' && !confirm('Naozaj zrušiť objednávku č. ' + btn.getAttribute('data-no') + '?')) return;
      btn.classList.add('is-busy');
      post('status', { id: btn.getAttribute('data-id'), status: st })
        .then(function () {
          toast('Objednávka č. ' + btn.getAttribute('data-no') + ': ' + STATUS[st], st !== 'cancelled');
          return poll();
        })
        .catch(function () { btn.classList.remove('is-busy'); alert('Stav sa nepodarilo zmeniť. Skontrolujte pripojenie.'); });
    });

    $$('[data-view]').forEach(function (b) {
      b.addEventListener('click', function () {
        view = b.getAttribute('data-view');
        $$('[data-view]').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
        board.setAttribute('aria-busy', 'true');
        poll().then(function () { board.setAttribute('aria-busy', 'false'); });
      });
    });

    var refresh = $('[data-refresh]');
    if (refresh) {
      refresh.addEventListener('click', function () {
        refresh.classList.add('is-busy');
        lastJson = '';
        poll().then(function () { refresh.classList.remove('is-busy'); });
      });
    }

    var tog = $('[data-ordering]');
    if (tog) {
      tog.addEventListener('change', function () {
        var on = tog.checked;
        paintOrdering(on);
        post('ordering', { enabled: on ? '1' : '0' }).then(function () {
          toast(on ? 'Online objednávky zapnuté' : 'Online objednávky pozastavené', on);
        }).catch(function () {
          tog.checked = !on;
          paintOrdering(!on);
          alert('Nastavenie sa nepodarilo uložiť.');
        });
      });
    }
  }

  if (CSRF && PAGE !== 'login') { poll(); schedule(); }
})();
