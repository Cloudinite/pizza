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

  /* ---------- sound, vibration, notifications ---------- */
  var actx = null;
  var soundOn = store.get('ps_sound') === '1';
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
      store.set('ps_sound', soundOn ? '1' : '0');
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
  var wakeWanted = store.get('ps_wake') === '1';
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
      store.set('ps_wake', wakeWanted ? '1' : '0');
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
    if (conn) { conn.textContent = '↻ obnoviť'; conn.classList.remove('is-bad'); }
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
    return '<article class="o-card st-' + o.status + '">' +
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
      '<p class="o-total"><span>' + esc(o.payment) + (o.delivery ? ' · donáška ' + money(o.delivery) : '') + '</span><b>' + money(o.total) + '</b></p>' +
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
      $('[data-ordering-label]').textContent = d.ordering ? 'Prijímame' : 'Pozastavené';
    }
    if (json === lastJson) return;
    lastJson = json;
    board.innerHTML = d.orders.map(card).join('');
    $('[data-empty]').hidden = d.orders.length > 0;
  }

  function poll() {
    var req = board ? get('action=orders&view=' + view) : get('action=ping');
    return req.then(function (d) {
      handlePending(d);
      if (board && d.view === view) renderBoard(d);
    }).catch(function (e) {
      if (e.message === 'auth') return;
      var conn = $('[data-conn]');
      if (conn) { conn.textContent = 'bez spojenia!'; conn.classList.add('is-bad'); }
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
        .then(function () { return poll(); })
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
        $('[data-ordering-label]').textContent = on ? 'Prijímame' : 'Pozastavené';
        post('ordering', { enabled: on ? '1' : '0' }).catch(function () {
          tog.checked = !on;
          $('[data-ordering-label]').textContent = !on ? 'Prijímame' : 'Pozastavené';
          alert('Nastavenie sa nepodarilo uložiť.');
        });
      });
    }
  }

  if (CSRF && PAGE !== 'login') { poll(); schedule(); }
})();
