(() => {
  'use strict';

  /* ================= helpers ================= */

  const KEY = 'liftlog:v1';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const uid = () => Math.random().toString(36).slice(2, 9) + Date.now().toString(36).slice(-5);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const clone = (o) => JSON.parse(JSON.stringify(o));
  const vibrate = (p) => { try { navigator.vibrate && navigator.vibrate(p); } catch (e) { /* not supported */ } };

  const pad = (n) => String(n).padStart(2, '0');
  const toKey = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const fromKey = (k) => { const [y, m, d] = k.split('-').map(Number); return new Date(y, m - 1, d); };
  const todayKey = () => toKey(new Date());
  const addDays = (k, n) => { const d = fromKey(k); d.setDate(d.getDate() + n); return toKey(d); };
  const weekdayOf = (k) => fromKey(k).getDay();
  const weekStartOf = (k) => addDays(k, -((weekdayOf(k) - state.weekStart + 7) % 7));
  const DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const DOW_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const MON_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const weekOrder = () => (state.weekStart === 1 ? [1, 2, 3, 4, 5, 6, 0] : [0, 1, 2, 3, 4, 5, 6]);
  const shortDate = (k) => { const d = fromKey(k); return `${d.getDate()} ${MON[d.getMonth()]}`; };
  const longDate = (k) => { const d = fromKey(k); return `${DOW[d.getDay()]} · ${d.getDate()} ${MON[d.getMonth()]}`; };
  const relDay = (k) => {
    const t = todayKey();
    if (k === t) return 'Today';
    if (k === addDays(t, -1)) return 'Yesterday';
    if (k === addDays(t, 1)) return 'Tomorrow';
    return DOW_LONG[weekdayOf(k)];
  };

  const num = (v) => { const n = parseFloat(String(v).replace(',', '.')); return Number.isFinite(n) && n > 0 ? n : 0; };
  const fmt = (n) => (Number.isInteger(n) ? String(n) : String(+n.toFixed(2)));
  const fmtVol = (v) => (v >= 10000 ? `${(v / 1000).toFixed(1)}k` : Math.round(v).toLocaleString());
  const e1rm = (w, r) => (r <= 1 ? w : w * (1 + r / 30));
  const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

  const ICON = {
    check: '<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
    dots: '<svg viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/></svg>',
    gear: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/></svg>',
    left: '<svg viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></svg>',
    right: '<svg viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>',
    plus: '<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
    up: '<svg viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6"/></svg>',
    down: '<svg viewBox="0 0 24 24"><path d="M12 5v14M6 13l6 6 6-6"/></svg>',
    trash: '<svg viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>',
    copy: '<svg viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"/></svg>',
    swap: '<svg viewBox="0 0 24 24"><path d="M7 4L3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/></svg>',
    chart: '<svg viewBox="0 0 24 24"><path d="M4 19V11M10 19V5M16 19v-6M22 19H2"/></svg>',
    flag: '<svg viewBox="0 0 24 24"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>',
    today: '<svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/><circle cx="12" cy="15" r="1.5"/></svg>',
    edit: '<svg viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"/></svg>',
  };

  /* ================= templates & library ================= */

  const ex = (name, sets, reps) => ({ name, sets, reps, weight: 0 });
  const TEMPLATES = [
    {
      id: 'ppl', name: 'Push / Pull / Legs', desc: '6 days · each muscle twice a week',
      days: [
        { name: 'Push', weekdays: [1, 4], exercises: [ex('Bench Press', 4, 6), ex('Overhead Press', 3, 8), ex('Incline Dumbbell Press', 3, 10), ex('Lateral Raise', 3, 15), ex('Triceps Pushdown', 3, 12)] },
        { name: 'Pull', weekdays: [2, 5], exercises: [ex('Deadlift', 3, 5), ex('Pull-up', 3, 8), ex('Barbell Row', 3, 8), ex('Face Pull', 3, 15), ex('Biceps Curl', 3, 12)] },
        { name: 'Legs', weekdays: [3, 6], exercises: [ex('Squat', 4, 6), ex('Romanian Deadlift', 3, 8), ex('Leg Press', 3, 10), ex('Leg Curl', 3, 12), ex('Calf Raise', 4, 15)] },
      ],
    },
    {
      id: 'ul', name: 'Upper / Lower', desc: '4 days · great balance of volume and rest',
      days: [
        { name: 'Upper A', weekdays: [1], exercises: [ex('Bench Press', 4, 6), ex('Barbell Row', 4, 8), ex('Overhead Press', 3, 8), ex('Lat Pulldown', 3, 10), ex('Biceps Curl', 3, 12)] },
        { name: 'Lower A', weekdays: [2], exercises: [ex('Squat', 4, 6), ex('Romanian Deadlift', 3, 8), ex('Leg Extension', 3, 12), ex('Calf Raise', 4, 15)] },
        { name: 'Upper B', weekdays: [4], exercises: [ex('Incline Dumbbell Press', 4, 8), ex('Pull-up', 4, 8), ex('Dumbbell Shoulder Press', 3, 10), ex('Cable Row', 3, 10), ex('Triceps Pushdown', 3, 12)] },
        { name: 'Lower B', weekdays: [5], exercises: [ex('Deadlift', 3, 5), ex('Leg Press', 3, 10), ex('Leg Curl', 3, 12), ex('Hip Thrust', 3, 10)] },
      ],
    },
    {
      id: 'fb', name: 'Full Body', desc: '3 days · simple and efficient',
      days: [
        { name: 'Full Body A', weekdays: [1], exercises: [ex('Squat', 3, 5), ex('Bench Press', 3, 5), ex('Barbell Row', 3, 8), ex('Plank', 3, 1)] },
        { name: 'Full Body B', weekdays: [3], exercises: [ex('Deadlift', 3, 5), ex('Overhead Press', 3, 8), ex('Pull-up', 3, 8), ex('Lunges', 3, 10)] },
        { name: 'Full Body C', weekdays: [5], exercises: [ex('Front Squat', 3, 6), ex('Incline Dumbbell Press', 3, 10), ex('Lat Pulldown', 3, 10), ex('Hip Thrust', 3, 10)] },
      ],
    },
    {
      id: 'bro', name: 'Body-part split', desc: '5 days · one muscle group per day',
      days: [
        { name: 'Chest', weekdays: [1], exercises: [ex('Bench Press', 4, 8), ex('Incline Dumbbell Press', 3, 10), ex('Chest Fly', 3, 12), ex('Dips', 3, 10)] },
        { name: 'Back', weekdays: [2], exercises: [ex('Deadlift', 3, 5), ex('Pull-up', 4, 8), ex('Barbell Row', 3, 8), ex('Lat Pulldown', 3, 12)] },
        { name: 'Shoulders', weekdays: [3], exercises: [ex('Overhead Press', 4, 8), ex('Lateral Raise', 4, 15), ex('Rear Delt Fly', 3, 15), ex('Shrugs', 3, 12)] },
        { name: 'Arms', weekdays: [4], exercises: [ex('Barbell Curl', 4, 10), ex('Skull Crusher', 4, 10), ex('Hammer Curl', 3, 12), ex('Triceps Pushdown', 3, 12)] },
        { name: 'Legs', weekdays: [5], exercises: [ex('Squat', 4, 8), ex('Leg Press', 3, 12), ex('Leg Curl', 3, 12), ex('Calf Raise', 4, 15)] },
      ],
    },
    { id: 'blank', name: 'Start from scratch', desc: 'Build your own split', days: [{ name: 'Day 1', weekdays: [], exercises: [] }] },
  ];

  const LIBRARY = [
    'Bench Press', 'Incline Bench Press', 'Incline Dumbbell Press', 'Dumbbell Bench Press', 'Chest Fly', 'Cable Crossover', 'Push-up', 'Dips',
    'Overhead Press', 'Dumbbell Shoulder Press', 'Lateral Raise', 'Rear Delt Fly', 'Face Pull', 'Shrugs', 'Upright Row',
    'Deadlift', 'Romanian Deadlift', 'Pull-up', 'Chin-up', 'Lat Pulldown', 'Barbell Row', 'Dumbbell Row', 'Cable Row', 'T-Bar Row',
    'Squat', 'Front Squat', 'Leg Press', 'Hack Squat', 'Bulgarian Split Squat', 'Lunges', 'Leg Extension', 'Leg Curl', 'Hip Thrust', 'Calf Raise',
    'Biceps Curl', 'Barbell Curl', 'Hammer Curl', 'Preacher Curl', 'Triceps Pushdown', 'Skull Crusher', 'Overhead Triceps Extension', 'Close-Grip Bench Press',
    'Plank', 'Crunch', 'Hanging Leg Raise', 'Cable Crunch', 'Russian Twist',
  ];

  /* ================= state ================= */

  const defaults = () => ({
    v: 1, units: 'kg', step: 2.5, rest: 90, weekStart: 1, theme: 'auto',
    activeSplitId: null, splits: [], sessions: [],
  });

  function load() {
    try {
      const s = JSON.parse(localStorage.getItem(KEY));
      if (s && s.v) return Object.assign(defaults(), s);
    } catch (e) { /* corrupted or blocked storage */ }
    return defaults();
  }

  let state = load();
  let saveTimer = null;
  function saveNow() {
    clearTimeout(saveTimer);
    saveTimer = null;
    try { localStorage.setItem(KEY, JSON.stringify(state)); }
    catch (e) { toast('Could not save – is storage full?'); }
  }
  const save = () => { clearTimeout(saveTimer); saveTimer = setTimeout(saveNow, 120); };
  window.addEventListener('pagehide', saveNow);
  document.addEventListener('visibilitychange', () => { if (document.hidden && saveTimer) saveNow(); });

  const ui = {
    tab: 'today',
    date: todayKey(),
    draft: null,
    editSplitId: null,
    histMode: 'workouts',
    histQuery: '',
  };

  const activeSplit = () => state.splits.find((s) => s.id === state.activeSplitId) || null;
  const splitById = (id) => state.splits.find((s) => s.id === id) || null;
  const plannedDay = (k) => { const sp = activeSplit(); return sp ? sp.days.find((d) => d.weekdays.includes(weekdayOf(k))) || null : null; };
  const savedSession = (k) => state.sessions.find((s) => s.date === k) || null;

  function makeSplit(tpl) {
    return {
      id: uid(),
      name: tpl.id === 'blank' ? 'My split' : tpl.name,
      days: tpl.days.map((d) => ({ id: uid(), name: d.name, weekdays: d.weekdays.slice(), exercises: d.exercises.map((e) => ({ id: uid(), ...e })) })),
    };
  }

  function lastPerformance(name, beforeDate) {
    const n = name.trim().toLowerCase();
    let best = null;
    for (const s of state.sessions) {
      if (s.date >= beforeDate || (best && s.date <= best.date)) continue;
      const e = s.exercises.find((x) => x.name.trim().toLowerCase() === n);
      if (!e) continue;
      const done = e.sets.filter((st) => st.done);
      if (done.length) best = { date: s.date, sets: done };
    }
    return best;
  }

  function buildExercise(name, sets, reps, weight, date) {
    const last = lastPerformance(name, date);
    const count = Math.max(1, sets || (last ? last.sets.length : 3));
    const arr = [];
    for (let i = 0; i < count; i++) {
      const ls = last && (last.sets[i] || last.sets[last.sets.length - 1]);
      arr.push({ w: ls ? ls.w : weight || 0, r: reps || (ls ? ls.r : 8), done: false });
    }
    return { id: uid(), name, targetSets: sets || 0, targetReps: reps || 0, sets: arr };
  }

  function buildSession(date, day) {
    const sp = activeSplit();
    return {
      id: uid(),
      date,
      splitId: day && sp ? sp.id : null,
      dayId: day ? day.id : null,
      name: day ? day.name : 'Workout',
      exercises: day ? day.exercises.map((e) => buildExercise(e.name, e.sets, e.reps, e.weight, date)) : [],
    };
  }

  function currentSession() {
    const saved = savedSession(ui.date);
    if (saved) return saved;
    if (ui.draft && ui.draft.date === ui.date) return ui.draft;
    const day = plannedDay(ui.date);
    if (!day) return null;
    ui.draft = buildSession(ui.date, day);
    return ui.draft;
  }

  // Store a draft session the first time the user changes anything in it.
  function commit(sess) {
    if (!state.sessions.includes(sess)) {
      state.sessions.push(sess);
      state.sessions.sort((a, b) => (a.date < b.date ? -1 : 1));
      if (ui.draft === sess) ui.draft = null;
    }
    save();
  }

  function sessionStats(s) {
    let sets = 0, done = 0, reps = 0, vol = 0;
    for (const e of s.exercises) {
      for (const st of e.sets) {
        sets++;
        if (st.done) { done++; reps += st.r; vol += st.w * st.r; }
      }
    }
    return { sets, done, reps, vol };
  }

  function knownExerciseNames() {
    const seen = new Map();
    const add = (n) => { const k = n.trim().toLowerCase(); if (k && !seen.has(k)) seen.set(k, n.trim()); };
    state.splits.forEach((sp) => sp.days.forEach((d) => d.exercises.forEach((e) => add(e.name))));
    state.sessions.forEach((s) => s.exercises.forEach((e) => add(e.name)));
    LIBRARY.forEach(add);
    return Array.from(seen.values());
  }

  /* ================= rendering shell ================= */

  const view = $('#view');

  function setHeader(eyebrow, title, actions = '') {
    $('#eyebrow').textContent = eyebrow;
    $('#title').textContent = title;
    $('#topActions').innerHTML = actions;
  }

  function render(animate) {
    $$('#tabbar .tab').forEach((t) => t.classList.toggle('on', t.dataset.tab === ui.tab));
    if (ui.tab === 'today') renderToday();
    else if (ui.tab === 'week') renderWeek();
    else if (ui.tab === 'plan') renderPlan();
    else renderHistory();
    if (animate) {
      view.style.animation = 'none';
      void view.offsetWidth;
      view.style.animation = '';
    }
  }

  function goTab(tab) {
    if (ui.tab === tab && tab === 'plan' && ui.editSplitId) ui.editSplitId = null;
    ui.tab = tab;
    render(true);
    window.scrollTo(0, 0);
  }

  const settingsBtn = `<button class="icon-btn" data-act="settings" aria-label="Settings">${ICON.gear}</button>`;

  /* ================= workout (daily) view ================= */

  function renderToday() {
    const k = ui.date;
    const t = todayKey();
    const actions = (k !== t ? `<button class="icon-btn" data-act="go-today" aria-label="Go to today">${ICON.today}</button>` : '') + settingsBtn;
    setHeader(longDate(k), relDay(k), actions);

    let html = dateStrip(k);

    if (!state.splits.length) {
      html += `
        <div class="empty"><div class="big">💪</div><h3>Welcome to LiftLog</h3>
        <p>Pick a split to get started. You can rename, reorder and edit everything later.</p></div>
        ${templateChoices()}`;
      view.innerHTML = html;
      return;
    }

    const sess = currentSession();
    if (!sess) {
      const sp = activeSplit();
      html += `<div class="empty"><div class="big">😴</div><h3>Rest day</h3>
        <p>${sp ? 'Nothing planned for this day.' : 'No active split. Pick one in Splits.'} Want to train anyway?</p></div>
        <div class="choice-list">${startChoices()}</div>`;
      view.innerHTML = html;
      return;
    }

    html += `<div id="wHead">${workoutHead(sess)}</div>`;
    html += sess.exercises.map((e) => exCard(sess, e)).join('');
    html += `<button class="btn btn-ghost btn-block" data-act="ex-add-session">${ICON.plus} Add exercise</button>`;
    if (sess.exercises.length) {
      html += `<div style="height:12px"></div><button class="btn btn-primary btn-block" data-act="finish">${ICON.flag} ${sess.finished ? 'Workout finished' : 'Finish workout'}</button>`;
    }
    view.innerHTML = html;
  }

  function dateStrip(k) {
    const ws = weekStartOf(k);
    const t = todayKey();
    let pills = '';
    for (let i = 0; i < 7; i++) {
      const d = addDays(ws, i);
      const s = savedSession(d);
      const doneAny = s && sessionStats(s).done > 0;
      const dot = doneAny ? 'done' : plannedDay(d) ? 'planned' : '';
      pills += `<button class="day-pill${d === k ? ' sel' : ''}${d === t ? ' today' : ''}" data-act="pick-date" data-date="${d}">
        ${DOW[weekdayOf(d)].slice(0, 2)}<b>${fromKey(d).getDate()}</b><i class="dot ${dot}"></i></button>`;
    }
    return `<div class="date-nav">
      <button class="icon-btn" data-act="shift-date" data-n="-7" aria-label="Previous week">${ICON.left}</button>
      <div class="days-strip">${pills}</div>
      <button class="icon-btn" data-act="shift-date" data-n="7" aria-label="Next week">${ICON.right}</button>
    </div>`;
  }

  function templateChoices() {
    return `<div class="choice-list">${TEMPLATES.map((t) => `
      <button class="choice" data-act="use-template" data-tpl="${t.id}">
        <div class="grow"><b>${esc(t.name)}</b><span>${esc(t.desc)}</span></div>${ICON.right.replace('<svg', '<svg class="chev"')}
      </button>`).join('')}</div>`;
  }

  function startChoices() {
    const sp = activeSplit();
    const days = sp ? sp.days : [];
    return days.map((d) => `
      <button class="choice" data-act="start-day" data-day="${d.id}">
        <div class="grow"><b>${esc(d.name)}</b><span>${plural(d.exercises.length, 'exercise')}${d.weekdays.length ? ' · ' + weekOrder().filter((w) => d.weekdays.includes(w)).map((w) => DOW[w]).join(', ') : ''}</span></div>
        ${ICON.right.replace('<svg', '<svg class="chev"')}
      </button>`).join('') + `
      <button class="choice" data-act="start-day" data-day="">
        <div class="grow"><b>Empty workout</b><span>Add exercises as you go</span></div>${ICON.plus.replace('<svg', '<svg class="chev"')}
      </button>`;
  }

  function workoutHead(sess) {
    const st = sessionStats(sess);
    const pct = st.sets ? st.done / st.sets : 0;
    const C = 2 * Math.PI * 18;
    const sp = splitById(sess.splitId);
    return `
      <div class="workout-head">
        <svg class="progress-ring" viewBox="0 0 46 46">
          <circle class="bg" cx="23" cy="23" r="18"/>
          <circle class="fg" cx="23" cy="23" r="18" stroke-dasharray="${C}" stroke-dashoffset="${C * (1 - pct)}" transform="rotate(-90 23 23)" stroke-linecap="round"/>
          <text x="23" y="27" text-anchor="middle">${Math.round(pct * 100)}%</text>
        </svg>
        <div class="grow">
          <h2 class="ellipsis">${esc(sess.name)}${sess.finished ? ' <span class="ex-done-badge">DONE</span>' : ''}</h2>
          <div class="muted small ellipsis">${sp ? esc(sp.name) : 'Custom workout'}</div>
        </div>
        <button class="icon-btn" data-act="workout-menu" aria-label="Workout options">${ICON.dots}</button>
      </div>
      <div class="stats">
        <div class="stat"><b>${st.done}/${st.sets}</b><span>Sets</span></div>
        <div class="stat"><b>${st.reps}</b><span>Reps</span></div>
        <div class="stat"><b>${fmtVol(st.vol)}</b><span>Volume ${state.units}</span></div>
      </div>`;
  }

  function exCard(sess, e) {
    const last = lastPerformance(e.name, sess.date);
    const allDone = e.sets.length > 0 && e.sets.every((s) => s.done);
    const meta = [];
    if (e.targetSets && e.targetReps) meta.push(`Goal <b>${e.targetSets} × ${e.targetReps}</b>`);
    if (last) meta.push(`Last <b>${last.sets.map((s) => (s.w ? `${fmt(s.w)}×${s.r}` : `${s.r}`)).join(', ')}</b>`);
    else meta.push('First time 🎉');
    return `
      <div class="card ex-card" data-ex="${e.id}">
        <div class="ex-top">
          <div class="grow">
            <h3 class="ex-name">${esc(e.name)}${allDone ? '<span class="ex-done-badge">✓</span>' : ''}</h3>
            <div class="ex-meta">${meta.join(' · ')}</div>
          </div>
          <button class="ex-menu" data-act="ex-menu" aria-label="Exercise options">${ICON.dots}</button>
        </div>
        <div class="sets">
          <div class="set-head"><span></span><span>${state.units}</span><span>Reps</span><span></span></div>
          ${e.sets.map((s, i) => setRow(s, i)).join('')}
        </div>
        <div class="ex-actions">
          <button class="btn btn-sm" data-act="set-add">${ICON.plus} Add set</button>
          ${e.sets.length > 1 ? '<button class="btn btn-sm" data-act="set-remove-last">Remove set</button>' : ''}
        </div>
      </div>`;
  }

  function setRow(s, i) {
    return `
      <div class="set-row${s.done ? ' done' : ''}" data-set="${i}">
        <button class="set-num" data-act="set-menu" aria-label="Set ${i + 1} options">${i + 1}</button>
        <div class="stepper">
          <button data-act="step" data-f="w" data-d="-1" aria-label="Less weight">−</button>
          <input data-f="w" inputmode="decimal" enterkeyhint="done" value="${fmt(s.w)}" aria-label="Weight">
          <button data-act="step" data-f="w" data-d="1" aria-label="More weight">+</button>
        </div>
        <div class="stepper">
          <button data-act="step" data-f="r" data-d="-1" aria-label="Fewer reps">−</button>
          <input data-f="r" inputmode="numeric" pattern="[0-9]*" enterkeyhint="done" value="${s.r}" aria-label="Reps">
          <button data-act="step" data-f="r" data-d="1" aria-label="More reps">+</button>
        </div>
        <button class="check" data-act="set-done" aria-label="Mark set done">${ICON.check}</button>
      </div>`;
  }

  function refreshHead(sess) {
    const h = $('#wHead');
    if (h) h.innerHTML = workoutHead(sess);
  }

  function refreshCard(sess, e) {
    const card = view.querySelector(`[data-ex="${e.id}"]`);
    if (!card) return;
    card.outerHTML = exCard(sess, e);
  }

  function refreshBadge(e) {
    const card = view.querySelector(`[data-ex="${e.id}"]`);
    if (!card) return;
    const h = $('.ex-name', card);
    const allDone = e.sets.length > 0 && e.sets.every((s) => s.done);
    const badge = $('.ex-done-badge', h);
    if (allDone && !badge) h.insertAdjacentHTML('beforeend', '<span class="ex-done-badge">✓</span>');
    if (!allDone && badge) badge.remove();
  }

  function findEx(sess, el) {
    const card = el.closest('[data-ex]');
    return card ? sess.exercises.find((x) => x.id === card.dataset.ex) : null;
  }

  // Changing a set's weight also updates later, not-yet-done sets that had the same weight.
  function setValue(sess, e, idx, field, value, row) {
    const old = e.sets[idx][field];
    e.sets[idx][field] = value;
    if (field === 'w') {
      for (let j = idx + 1; j < e.sets.length; j++) {
        const s = e.sets[j];
        if (s.done || s.w !== old) break;
        s.w = value;
        const inp = view.querySelector(`[data-ex="${e.id}"] [data-set="${j}"] input[data-f="w"]`);
        if (inp) inp.value = fmt(value);
      }
    }
    if (row) {
      const inp = $(`input[data-f="${field}"]`, row);
      if (inp && document.activeElement !== inp) inp.value = field === 'w' ? fmt(value) : value;
    }
    commit(sess);
    refreshHead(sess);
  }

  function workoutAction(act, el) {
    const sess = currentSession();
    if (!sess) return;
    const e = findEx(sess, el);
    const rowEl = el.closest('[data-set]');
    const idx = rowEl ? +rowEl.dataset.set : -1;

    switch (act) {
      case 'step': {
        const f = el.dataset.f;
        const d = +el.dataset.d;
        const cur = e.sets[idx][f];
        const step = f === 'w' ? state.step : 1;
        let v = f === 'w' ? Math.round((cur + d * step) * 100) / 100 : cur + d;
        if (v < 0) v = 0;
        setValue(sess, e, idx, f, v, rowEl);
        vibrate(5);
        break;
      }
      case 'set-done': {
        const s = e.sets[idx];
        s.done = !s.done;
        rowEl.classList.toggle('done', s.done);
        commit(sess);
        refreshHead(sess);
        refreshBadge(e);
        if (s.done) {
          vibrate(15);
          if (state.rest > 0) startRest(state.rest);
          if (sessionStats(sess).done === sessionStats(sess).sets) toast('All sets done! 🔥');
        }
        break;
      }
      case 'set-add': {
        const lastSet = e.sets[e.sets.length - 1];
        e.sets.push(lastSet ? { w: lastSet.w, r: lastSet.r, done: false } : { w: 0, r: e.targetReps || 8, done: false });
        commit(sess);
        refreshCard(sess, e);
        refreshHead(sess);
        break;
      }
      case 'set-remove-last': {
        if (e.sets.length > 1) e.sets.pop();
        commit(sess);
        refreshCard(sess, e);
        refreshHead(sess);
        break;
      }
      case 'set-menu': openSetMenu(sess, e, idx); break;
      case 'ex-menu': openExMenu(sess, e); break;
      case 'workout-menu': openWorkoutMenu(sess); break;
      case 'ex-add-session': openExerciseForm({ mode: 'session' }); break;
      case 'finish': {
        const st = sessionStats(sess);
        if (!st.done) { toast('Tick off at least one set first'); break; }
        sess.finished = true;
        commit(sess);
        refreshHead(sess);
        stopRest();
        toast(`Nice work! ${st.done} sets · ${fmtVol(st.vol)} ${state.units} 💪`);
        vibrate([20, 60, 20]);
        render();
        break;
      }
    }
  }

  /* ================= week view ================= */

  function renderWeek() {
    const ws = weekStartOf(ui.date);
    const thisWs = weekStartOf(todayKey());
    const diff = Math.round((fromKey(ws) - fromKey(thisWs)) / 864e5 / 7);
    const title = diff === 0 ? 'This week' : diff === -1 ? 'Last week' : diff === 1 ? 'Next week' : `Week of ${shortDate(ws)}`;
    setHeader(`${shortDate(ws)} – ${shortDate(addDays(ws, 6))}`, title, settingsBtn);

    const t = todayKey();
    let planned = 0, trained = 0, sets = 0, vol = 0;
    let rows = '';
    for (let i = 0; i < 7; i++) {
      const d = addDays(ws, i);
      const day = plannedDay(d);
      const s = savedSession(d);
      const st = s ? sessionStats(s) : null;
      if (day) planned++;
      if (st && st.done) { trained++; sets += st.done; vol += st.vol; }
      let name, sub, status, mark = '';
      if (st && st.done) {
        name = s.name;
        sub = `${plural(s.exercises.length, 'exercise')} · ${plural(st.done, 'set')} · ${fmtVol(st.vol)} ${state.units}`;
        status = st.done === st.sets ? 'done' : 'partial';
        mark = status === 'done' ? ICON.check : `<span style="font-size:10px;font-weight:800">${Math.round((st.done / st.sets) * 100)}%</span>`;
      } else if (day) {
        name = day.name;
        sub = `${plural(day.exercises.length, 'exercise')} · ${plural(day.exercises.reduce((a, x) => a + (x.sets || 0), 0), 'set')} planned`;
        status = d < t ? 'missed' : '';
      } else {
        name = 'Rest';
        sub = 'Recover & grow';
        status = 'rest';
        mark = '–';
      }
      rows += `
        <button class="week-day${d === t ? ' is-today' : ''}" data-act="open-date" data-date="${d}">
          <div class="wd-date"><span>${DOW[weekdayOf(d)]}</span><b>${fromKey(d).getDate()}</b></div>
          <div class="wd-body grow"><b class="ellipsis">${esc(name)}</b><span class="ellipsis" style="display:block">${sub}</span></div>
          <div class="wd-status ${status}">${mark}</div>
        </button>`;
    }

    view.innerHTML = `
      <div class="date-nav" style="justify-content:space-between">
        <button class="icon-btn" data-act="shift-date" data-n="-7" aria-label="Previous week">${ICON.left}</button>
        ${diff !== 0 ? '<button class="link-btn" data-act="go-today">Back to this week</button>' : `<span class="muted small">${activeSplit() ? esc(activeSplit().name) : 'No active split'}</span>`}
        <button class="icon-btn" data-act="shift-date" data-n="7" aria-label="Next week">${ICON.right}</button>
      </div>
      <div class="week-summary">
        <div class="stat"><b>${trained}/${planned}</b><span>Workouts</span></div>
        <div class="stat"><b>${sets}</b><span>Sets done</span></div>
        <div class="stat"><b>${fmtVol(vol)}</b><span>Volume ${state.units}</span></div>
      </div>
      ${rows}`;
  }

  /* ================= plan (splits) view ================= */

  function renderPlan() {
    const sp = ui.editSplitId && splitById(ui.editSplitId);
    if (sp) return renderSplitEditor(sp);
    ui.editSplitId = null;
    setHeader('Your programs', 'Splits', settingsBtn);

    if (!state.splits.length) {
      view.innerHTML = `<div class="empty"><div class="big">🗂️</div><h3>No splits yet</h3><p>Start from a proven template or build your own.</p></div>${templateChoices()}`;
      return;
    }

    const cards = state.splits.map((sp) => {
      const active = sp.id === state.activeSplitId;
      const sched = weekOrder().map((w) => { const d = sp.days.find((x) => x.weekdays.includes(w)); return d ? `${DOW[w]}` : null; }).filter(Boolean).join(' · ');
      return `
        <button class="card split-card" data-act="edit-split" data-split="${sp.id}">
          <div class="grow">
            <div class="row" style="gap:8px"><b style="font-size:18px" class="ellipsis">${esc(sp.name)}</b>${active ? '<span class="badge">Active</span>' : ''}</div>
            <div class="muted small ellipsis">${sp.days.length} day${sp.days.length === 1 ? '' : 's'} · ${sp.days.map((d) => esc(d.name)).join(', ')}</div>
            <div class="muted tiny" style="margin-top:4px">${sched || 'No weekdays assigned'}</div>
          </div>
          ${ICON.right.replace('<svg', '<svg class="chev"')}
        </button>`;
    }).join('');

    view.innerHTML = `${cards}<button class="btn btn-ghost btn-block" data-act="new-split">${ICON.plus} New split</button>`;
  }

  function renderSplitEditor(sp) {
    const active = sp.id === state.activeSplitId;
    setHeader('Edit split', sp.name,
      `<button class="icon-btn" data-act="split-back" aria-label="Back">${ICON.left}</button>`);

    const days = sp.days.map((d, di) => `
      <div class="card day-card" data-day="${d.id}">
        <div class="day-card-top">
          <input class="day-name-input" data-f="day-name" value="${esc(d.name)}" aria-label="Day name" autocapitalize="words">
          <button class="ex-menu" data-act="day-menu" aria-label="Day options">${ICON.dots}</button>
        </div>
        <div class="weekdays">
          ${weekOrder().map((w) => `<button class="wk${d.weekdays.includes(w) ? ' on' : ''}" data-act="toggle-wd" data-wd="${w}">${DOW[w].slice(0, 2)}</button>`).join('')}
        </div>
        ${d.exercises.map((e, i) => `
          <button class="plan-ex" data-act="plan-ex-edit" data-exid="${e.id}">
            <span class="plan-ex-num">${i + 1}</span>
            <div class="grow"><b class="ellipsis">${esc(e.name)}</b><span>${e.sets} sets × ${e.reps} reps${e.weight ? ` · ${fmt(e.weight)} ${state.units}` : ''}</span></div>
            ${ICON.edit.replace('<svg', '<svg class="chev"')}
          </button>`).join('')}
        <button class="btn btn-sm btn-block" style="margin-top:8px" data-act="plan-ex-add">${ICON.plus} Add exercise</button>
      </div>`).join('');

    view.innerHTML = `
      <div class="field"><input class="text-input split-name-input" data-f="split-name" value="${esc(sp.name)}" aria-label="Split name" autocapitalize="words"></div>
      ${active
        ? '<div class="card row" style="padding:12px 14px"><span class="badge">Active</span><span class="muted small">Workouts &amp; the week plan use this split.</span></div>'
        : `<button class="btn btn-primary btn-block" style="margin-bottom:12px" data-act="activate-split">Use this split</button>`}
      <div class="section-title"><span>Days</span><span class="tiny" style="text-transform:none">Tap weekdays to schedule</span></div>
      ${days}
      <button class="btn btn-ghost btn-block" data-act="day-add">${ICON.plus} Add day</button>
      <div style="height:26px"></div>
      <button class="btn btn-danger btn-block" data-act="split-delete">${ICON.trash} Delete split</button>`;
  }

  function planAction(act, el) {
    const sp = splitById(ui.editSplitId);
    const dayEl = el.closest('[data-day]');
    const day = sp && dayEl ? sp.days.find((d) => d.id === dayEl.dataset.day) : null;
    switch (act) {
      case 'edit-split': ui.editSplitId = el.dataset.split; render(true); window.scrollTo(0, 0); break;
      case 'split-back': ui.editSplitId = null; render(true); break;
      case 'new-split': openTemplateSheet(); break;
      case 'activate-split':
        state.activeSplitId = sp.id; ui.draft = null; save(); render(); toast(`${sp.name} is now active`); break;
      case 'toggle-wd': {
        const w = +el.dataset.wd;
        if (day.weekdays.includes(w)) day.weekdays = day.weekdays.filter((x) => x !== w);
        else {
          sp.days.forEach((d) => { d.weekdays = d.weekdays.filter((x) => x !== w); });
          day.weekdays.push(w);
        }
        ui.draft = null; save();
        $$('.day-card', view).forEach((card) => {
          const d = sp.days.find((x) => x.id === card.dataset.day);
          $$('.wk', card).forEach((b) => b.classList.toggle('on', d.weekdays.includes(+b.dataset.wd)));
        });
        vibrate(5);
        break;
      }
      case 'plan-ex-add': openExerciseForm({ mode: 'plan', dayId: day.id }); break;
      case 'plan-ex-edit': openExerciseForm({ mode: 'plan', dayId: day.id, exId: el.dataset.exid }); break;
      case 'day-add': {
        sp.days.push({ id: uid(), name: `Day ${sp.days.length + 1}`, weekdays: [], exercises: [] });
        save(); render();
        const last = $$('.day-card', view).pop();
        if (last) last.scrollIntoView({ behavior: 'smooth', block: 'center' });
        break;
      }
      case 'day-menu': openDayMenu(sp, day); break;
      case 'split-delete':
        if (!confirm(`Delete "${sp.name}"? Your logged workouts stay in your history.`)) return;
        state.splits = state.splits.filter((s) => s.id !== sp.id);
        if (state.activeSplitId === sp.id) state.activeSplitId = state.splits[0] ? state.splits[0].id : null;
        ui.editSplitId = null; ui.draft = null; save(); render(true);
        break;
    }
  }

  /* ================= history / progress view ================= */

  function exerciseIndex() {
    const map = new Map();
    for (const s of state.sessions) {
      for (const e of s.exercises) {
        const done = e.sets.filter((x) => x.done);
        if (!done.length) continue;
        const k = e.name.trim().toLowerCase();
        if (!map.has(k)) map.set(k, { name: e.name.trim(), entries: [] });
        map.get(k).entries.push({ date: s.date, sets: done });
      }
    }
    for (const v of map.values()) {
      v.entries.sort((a, b) => (a.date < b.date ? 1 : -1));
      let best = null;
      for (const en of v.entries) for (const st of en.sets) {
        const est = e1rm(st.w, st.r);
        if (!best || est > best.est) best = { ...st, est, date: en.date };
      }
      v.best = best;
      v.last = v.entries[0].date;
    }
    return Array.from(map.values()).sort((a, b) => (a.last < b.last ? 1 : a.last > b.last ? -1 : a.name.localeCompare(b.name)));
  }

  function renderHistory() {
    setHeader('Your training', 'Progress', settingsBtn);
    const done = state.sessions.filter((s) => sessionStats(s).done > 0).slice().reverse();
    const monthKey = todayKey().slice(0, 7);
    const thisMonth = done.filter((s) => s.date.startsWith(monthKey)).length;
    const totalVol = done.reduce((a, s) => a + sessionStats(s).vol, 0);

    let html = `
      <div class="stats">
        <div class="stat"><b>${done.length}</b><span>Workouts</span></div>
        <div class="stat"><b>${thisMonth}</b><span>This month</span></div>
        <div class="stat"><b>${fmtVol(totalVol)}</b><span>Total ${state.units}</span></div>
      </div>
      <div class="seg">
        <button class="${ui.histMode === 'workouts' ? 'on' : ''}" data-act="hist-mode" data-mode="workouts">Workouts</button>
        <button class="${ui.histMode === 'exercises' ? 'on' : ''}" data-act="hist-mode" data-mode="exercises">Exercises</button>
      </div>`;

    if (ui.histMode === 'workouts') {
      if (!done.length) {
        html += `<div class="empty"><div class="big">📈</div><h3>No workouts yet</h3><p>Tick off sets in the Workout tab and they’ll show up here.</p></div>`;
      } else {
        let month = '';
        for (const s of done) {
          const m = s.date.slice(0, 7);
          if (m !== month) {
            month = m;
            const d = fromKey(s.date);
            html += `<div class="section-title">${MON_LONG[d.getMonth()]} ${d.getFullYear()}</div>`;
          }
          const st = sessionStats(s);
          const d = fromKey(s.date);
          html += `
            <button class="card hist-item" data-act="open-date" data-date="${s.date}">
              <div class="hist-date"><span>${DOW[d.getDay()]}</span><b>${d.getDate()}</b></div>
              <div class="grow"><b class="ellipsis" style="display:block">${esc(s.name)}</b>
                <span class="muted small">${plural(s.exercises.filter((e) => e.sets.some((x) => x.done)).length, 'exercise')} · ${plural(st.done, 'set')} · ${fmtVol(st.vol)} ${state.units}</span></div>
              ${ICON.right.replace('<svg', '<svg class="chev"')}
            </button>`;
        }
      }
    } else {
      const q = ui.histQuery.trim().toLowerCase();
      const list = exerciseIndex().filter((x) => !q || x.name.toLowerCase().includes(q));
      html += `<input class="text-input search" type="search" data-f="hist-q" placeholder="Search exercises" value="${esc(ui.histQuery)}" autocomplete="off">`;
      html += `<div id="exList">${exerciseListHtml(list)}</div>`;
    }
    view.innerHTML = html;
  }

  function exerciseListHtml(list) {
    if (!list.length) return `<div class="empty"><p>No logged exercises${ui.histQuery ? ' match your search' : ' yet'}.</p></div>`;
    return list.map((x) => `
      <button class="card hist-item" data-act="ex-history" data-name="${esc(x.name)}">
        <div class="grow"><b class="ellipsis" style="display:block">${esc(x.name)}</b>
          <span class="muted small">${x.entries.length} session${x.entries.length === 1 ? '' : 's'} · last ${shortDate(x.last)}</span></div>
        <div style="text-align:right">
          <b class="pr">${x.best.w ? `${fmt(x.best.w)} × ${x.best.r}` : `${x.best.r} reps`}</b>
          <div class="muted tiny">best set</div>
        </div>
      </button>`).join('');
  }

  function openExerciseHistory(name) {
    const x = exerciseIndex().find((i) => i.name.toLowerCase() === name.trim().toLowerCase());
    if (!x) { openSheet(`<h3>${esc(name)}</h3><p class="muted">No completed sets logged yet.</p>`); return; }
    const rows = x.entries.map((en) => {
      const top = en.sets.reduce((a, s) => (e1rm(s.w, s.r) > e1rm(a.w, a.r) ? s : a), en.sets[0]);
      const isPR = x.best && x.best.date === en.date && e1rm(top.w, top.r) === x.best.est;
      return `<tr><td><b>${shortDate(en.date)}</b>${isPR ? ' <span class="pr tiny">PR</span>' : ''}<div class="muted small">${en.sets.map((s) => (s.w ? `${fmt(s.w)}×${s.r}` : `${s.r}`)).join(', ')}</div></td>
        <td>${top.w ? `≈${fmt(Math.round(e1rm(top.w, top.r) * 10) / 10)} ${state.units}<div class="tiny">est. 1RM</div>` : ''}</td></tr>`;
    }).join('');
    openSheet(`
      <h3>${esc(x.name)}</h3>
      <div class="stats">
        <div class="stat"><b>${x.best.w ? fmt(x.best.w) : '–'}</b><span>Best ${state.units}</span></div>
        <div class="stat"><b>${x.best.w ? fmt(Math.round(x.best.est * 10) / 10) : x.best.r}</b><span>${x.best.w ? 'Est. 1RM' : 'Best reps'}</span></div>
        <div class="stat"><b>${x.entries.length}</b><span>Sessions</span></div>
      </div>
      <div class="card"><table class="hist-table">${rows}</table></div>`);
  }

  /* ================= sheets ================= */

  const sheetRoot = $('#sheetRoot');
  const sheetBody = $('#sheetBody');
  let sheetCtx = null;
  let sheetOpen = false;

  function openSheet(html, ctx = {}) {
    sheetCtx = ctx;
    sheetBody.innerHTML = html;
    sheetRoot.classList.remove('closing');
    sheetRoot.hidden = false;
    $('.sheet', sheetRoot).scrollTop = 0;
    if (!sheetOpen) {
      sheetOpen = true;
      try { history.pushState({ sheet: true }, ''); } catch (e) { /* ignore */ }
    }
    if (ctx.focus) setTimeout(() => { const f = $(ctx.focus, sheetBody); if (f) f.focus(); }, 280);
  }

  function hideSheet() {
    if (!sheetOpen) return;
    sheetOpen = false;
    sheetCtx = null;
    if (document.activeElement && sheetRoot.contains(document.activeElement)) document.activeElement.blur();
    sheetRoot.classList.add('closing');
    setTimeout(() => { if (!sheetOpen) { sheetRoot.hidden = true; sheetBody.innerHTML = ''; } }, 190);
  }

  function closeSheet() {
    if (!sheetOpen) return;
    if (history.state && history.state.sheet) history.back();
    else hideSheet();
  }

  window.addEventListener('popstate', () => { if (sheetOpen) hideSheet(); });

  const menuItem = (act, icon, label, cls = '') => `<button class="menu-item ${cls}" data-act="${act}">${icon}<span>${label}</span></button>`;

  function sheetStepper(id, value, step, mode) {
    return `<div class="stepper">
      <button data-act="fstep" data-target="${id}" data-d="-1" data-step="${step}">−</button>
      <input id="${id}" inputmode="${mode}" value="${value}">
      <button data-act="fstep" data-target="${id}" data-d="1" data-step="${step}">+</button>
    </div>`;
  }

  function optGroup(name, options, current) {
    return `<div class="opt-group">${options.map(([v, label]) => `<button class="opt${String(v) === String(current) ? ' on' : ''}" data-act="set-opt" data-name="${name}" data-v="${v}">${label}</button>`).join('')}</div>`;
  }

  function openTemplateSheet() {
    openSheet(`<h3>New split</h3>${templateChoices()}`, {
      onAct(act, el) {
        if (act === 'use-template') { useTemplate(el.dataset.tpl, true); return true; }
      },
    });
  }

  function useTemplate(tplId, openEditor) {
    const tpl = TEMPLATES.find((t) => t.id === tplId);
    const sp = makeSplit(tpl);
    state.splits.push(sp);
    if (!state.activeSplitId || !activeSplit()) state.activeSplitId = sp.id;
    ui.draft = null;
    save();
    if (openEditor) { ui.tab = 'plan'; ui.editSplitId = sp.id; }
    closeSheet();
    render(true);
    window.scrollTo(0, 0);
    toast(openEditor ? 'Split created – make it yours' : `${sp.name} is ready 💪`);
  }

  function openExerciseForm({ mode, dayId, exId }) {
    let current = null;
    let day = null;
    if (mode === 'plan') {
      const sp = splitById(ui.editSplitId);
      day = sp.days.find((d) => d.id === dayId);
      current = exId ? day.exercises.find((e) => e.id === exId) : null;
    }
    const v = current || { name: '', sets: 3, reps: 10, weight: 0 };
    const names = knownExerciseNames();
    const suggest = (q) => {
      const s = q.trim().toLowerCase();
      const list = s ? names.filter((n) => n.toLowerCase().includes(s) && n.toLowerCase() !== s) : names;
      return list.slice(0, 10).map((n) => `<button class="chip" data-act="pick-name" data-name="${esc(n)}">${esc(n)}</button>`).join('');
    };
    const idx = current ? day.exercises.indexOf(current) : -1;

    openSheet(`
      <h3>${current ? 'Edit exercise' : 'Add exercise'}</h3>
      <div class="field">
        <label for="fName">Exercise</label>
        <input class="text-input" id="fName" value="${esc(v.name)}" placeholder="e.g. Bench Press" autocomplete="off" autocapitalize="words" enterkeyhint="done">
        <div class="suggestions" id="fSugg">${suggest(v.name)}</div>
      </div>
      <div class="field-grid">
        <div class="field"><span class="label">Sets</span>${sheetStepper('fSets', v.sets, 1, 'numeric')}</div>
        <div class="field"><span class="label">Reps</span>${sheetStepper('fReps', v.reps, 1, 'numeric')}</div>
        <div class="field"><span class="label">Start ${state.units}</span>${sheetStepper('fW', fmt(v.weight || 0), state.step, 'decimal')}</div>
      </div>
      <div class="sheet-actions">
        <button class="btn btn-primary btn-block" data-act="form-save">${current ? 'Save' : 'Add exercise'}</button>
        ${current ? `<div class="row">
          <button class="btn grow" data-act="form-move" data-d="-1" ${idx === 0 ? 'disabled style="opacity:.4"' : ''}>${ICON.up} Up</button>
          <button class="btn grow" data-act="form-move" data-d="1" ${idx === day.exercises.length - 1 ? 'disabled style="opacity:.4"' : ''}>${ICON.down} Down</button>
        </div>
        <button class="btn btn-danger btn-block" data-act="form-delete">${ICON.trash} Remove from day</button>` : ''}
      </div>`, {
      focus: current ? null : '#fName',
      onInput(e) {
        if (e.target.id === 'fName') $('#fSugg').innerHTML = suggest(e.target.value);
      },
      onAct(act, el) {
        if (act === 'pick-name') {
          $('#fName').value = el.dataset.name;
          $('#fSugg').innerHTML = '';
          return true;
        }
        if (act === 'form-save') {
          const name = $('#fName').value.trim();
          if (!name) { $('#fName').focus(); toast('Give the exercise a name'); return true; }
          const sets = Math.max(1, Math.round(num($('#fSets').value)) || 1);
          const reps = Math.max(1, Math.round(num($('#fReps').value)) || 1);
          const weight = num($('#fW').value);
          if (mode === 'plan') {
            if (current) Object.assign(current, { name, sets, reps, weight });
            else day.exercises.push({ id: uid(), name, sets, reps, weight });
            ui.draft = null;
          } else {
            const sess = currentSession() || (ui.draft = buildSession(ui.date, null));
            sess.exercises.push(buildExercise(name, sets, reps, weight, sess.date));
            commit(sess);
          }
          save();
          closeSheet();
          render();
          if (mode === 'session') setTimeout(() => { const cards = $$('.ex-card', view); const c = cards[cards.length - 1]; if (c) c.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 60);
          return true;
        }
        if (act === 'form-move') {
          const d = +el.dataset.d;
          const i = day.exercises.indexOf(current);
          const j = i + d;
          if (j < 0 || j >= day.exercises.length) return true;
          [day.exercises[i], day.exercises[j]] = [day.exercises[j], day.exercises[i]];
          ui.draft = null; save(); closeSheet(); render();
          return true;
        }
        if (act === 'form-delete') {
          day.exercises = day.exercises.filter((e) => e !== current);
          ui.draft = null; save(); closeSheet(); render();
          return true;
        }
      },
    });
  }

  function openDayMenu(sp, day) {
    const i = sp.days.indexOf(day);
    openSheet(`<h3>${esc(day.name)}</h3><div class="menu-list">
      ${i > 0 ? menuItem('d-up', ICON.up, 'Move up') : ''}
      ${i < sp.days.length - 1 ? menuItem('d-down', ICON.down, 'Move down') : ''}
      ${menuItem('d-dup', ICON.copy, 'Duplicate day')}
      ${menuItem('d-del', ICON.trash, 'Delete day', 'danger')}
    </div>`, {
      onAct(act) {
        if (act === 'd-up' || act === 'd-down') {
          const j = i + (act === 'd-up' ? -1 : 1);
          [sp.days[i], sp.days[j]] = [sp.days[j], sp.days[i]];
        } else if (act === 'd-dup') {
          const copy = clone(day);
          copy.id = uid(); copy.name = `${day.name} (copy)`; copy.weekdays = [];
          copy.exercises.forEach((e) => { e.id = uid(); });
          sp.days.splice(i + 1, 0, copy);
        } else if (act === 'd-del') {
          if (!confirm(`Delete "${day.name}"?`)) return true;
          sp.days.splice(i, 1);
        } else return false;
        ui.draft = null; save(); closeSheet(); render();
        return true;
      },
    });
  }

  function openSetMenu(sess, e, idx) {
    openSheet(`<h3>${esc(e.name)} · Set ${idx + 1}</h3><div class="menu-list">
      ${menuItem('s-dup', ICON.copy, 'Duplicate set')}
      ${e.sets.length > 1 ? menuItem('s-del', ICON.trash, 'Delete set', 'danger') : ''}
    </div>`, {
      onAct(act) {
        if (act === 's-dup') e.sets.splice(idx + 1, 0, { ...e.sets[idx], done: false });
        else if (act === 's-del') e.sets.splice(idx, 1);
        else return false;
        commit(sess); closeSheet(); refreshCard(sess, e); refreshHead(sess);
        return true;
      },
    });
  }

  function openExMenu(sess, e) {
    const i = sess.exercises.indexOf(e);
    openSheet(`<h3>${esc(e.name)}</h3><div class="menu-list">
      ${menuItem('e-hist', ICON.chart, 'History & best sets')}
      ${menuItem('e-all', ICON.check, 'Mark all sets done')}
      ${menuItem('e-rename', ICON.edit, 'Rename')}
      ${i > 0 ? menuItem('e-up', ICON.up, 'Move up') : ''}
      ${i < sess.exercises.length - 1 ? menuItem('e-down', ICON.down, 'Move down') : ''}
      ${menuItem('e-del', ICON.trash, 'Remove from this workout', 'danger')}
    </div>`, {
      onAct(act) {
        if (act === 'e-hist') { openExerciseHistory(e.name); return true; }
        if (act === 'e-all') e.sets.forEach((s) => { s.done = true; });
        else if (act === 'e-rename') {
          const n = prompt('Exercise name', e.name);
          if (!n || !n.trim()) return true;
          e.name = n.trim();
        } else if (act === 'e-up' || act === 'e-down') {
          const j = i + (act === 'e-up' ? -1 : 1);
          [sess.exercises[i], sess.exercises[j]] = [sess.exercises[j], sess.exercises[i]];
        } else if (act === 'e-del') {
          if (e.sets.some((s) => s.done) && !confirm(`Remove ${e.name} and its logged sets from this workout?`)) return true;
          sess.exercises.splice(i, 1);
        } else return false;
        commit(sess); closeSheet(); render();
        return true;
      },
    });
  }

  function openWorkoutMenu(sess) {
    const isSaved = state.sessions.includes(sess);
    openSheet(`<h3>${esc(sess.name)}</h3><div class="menu-list">
      ${menuItem('w-add', ICON.plus, 'Add exercise')}
      ${menuItem('w-switch', ICON.swap, 'Switch workout')}
      ${menuItem('w-rename', ICON.edit, 'Rename workout')}
      ${menuItem('w-all', ICON.check, 'Mark every set done')}
      ${isSaved ? menuItem('w-del', ICON.trash, 'Delete this log', 'danger') : ''}
    </div>`, {
      onAct(act) {
        if (act === 'w-add') { openExerciseForm({ mode: 'session' }); return true; }
        if (act === 'w-switch') {
          openSheet(`<h3>Switch workout</h3><div class="choice-list">${startChoices()}</div>`, {
            onAct(a, el) { if (a === 'start-day') { startDay(el.dataset.day); return true; } },
          });
          return true;
        }
        if (act === 'w-rename') {
          const n = prompt('Workout name', sess.name);
          if (!n || !n.trim()) return true;
          sess.name = n.trim();
        } else if (act === 'w-all') {
          sess.exercises.forEach((e) => e.sets.forEach((s) => { s.done = true; }));
        } else if (act === 'w-del') {
          if (!confirm('Delete this workout log? This can’t be undone.')) return true;
          state.sessions = state.sessions.filter((s) => s !== sess);
          ui.draft = null; save(); closeSheet(); render();
          return true;
        } else return false;
        commit(sess); closeSheet(); render();
        return true;
      },
    });
  }

  function startDay(dayId) {
    const existing = savedSession(ui.date);
    if (existing && sessionStats(existing).done > 0 && !confirm('Replace the sets already logged for this day?')) return;
    if (existing) state.sessions = state.sessions.filter((s) => s !== existing);
    const sp = activeSplit();
    const day = dayId && sp ? sp.days.find((d) => d.id === dayId) : null;
    ui.draft = buildSession(ui.date, day);
    if (!day) commit(ui.draft);
    save();
    render();
    // Swap the open sheet's content instead of closing it, so the back-navigation doesn't close the form.
    if (day) closeSheet();
    else openExerciseForm({ mode: 'session' });
  }

  /* ================= settings ================= */

  let installPrompt = null;
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); installPrompt = e; });

  function openSettings() {
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const stepOpts = state.units === 'kg' ? [[1, '1'], [1.25, '1.25'], [2.5, '2.5'], [5, '5']] : [[1, '1'], [2.5, '2.5'], [5, '5'], [10, '10']];
    let install = '';
    if (!isStandalone) {
      install = installPrompt
        ? `<button class="btn btn-primary btn-block" data-act="install">Install app</button>`
        : `<div class="card small"><b>Install on your phone</b><div class="muted" style="margin-top:4px">${isIOS
          ? 'In Safari tap <b>Share</b> → <b>Add to Home Screen</b>. It then opens full-screen and works offline.'
          : 'In Chrome open the <b>⋮</b> menu → <b>Install app</b> (or <b>Add to Home screen</b>).'}</div></div>`;
    }
    openSheet(`
      <h3>Settings</h3>
      ${install}
      <div class="field"><span class="label">Weight unit</span>${optGroup('units', [['kg', 'kg'], ['lb', 'lb']], state.units)}</div>
      <div class="field"><span class="label">+ / − weight step</span>${optGroup('step', stepOpts, state.step)}</div>
      <div class="field"><span class="label">Rest timer after each set</span>${optGroup('rest', [[0, 'Off'], [60, '1:00'], [90, '1:30'], [120, '2:00'], [180, '3:00']], state.rest)}</div>
      <div class="field"><span class="label">Week starts on</span>${optGroup('weekStart', [[1, 'Monday'], [0, 'Sunday']], state.weekStart)}</div>
      <div class="field"><span class="label">Appearance</span>${optGroup('theme', [['auto', 'Auto'], ['light', 'Light'], ['dark', 'Dark']], state.theme)}</div>
      <div class="section-title">Your data</div>
      <p class="muted small" style="margin:-4px 2px 10px">Everything is stored on this device only. Export a backup now and then, and import it to move to a new phone.</p>
      <div class="menu-list">
        ${menuItem('export', ICON.down, 'Export backup')}
        ${menuItem('import', ICON.up, 'Import backup')}
        ${menuItem('reset', ICON.trash, 'Erase all data', 'danger')}
      </div>
      <p class="muted tiny center" style="margin-top:18px">LiftLog · works offline</p>`, {
      onAct(act, el) {
        if (act === 'set-opt') {
          const name = el.dataset.name;
          let v = el.dataset.v;
          if (name !== 'units' && name !== 'theme') v = +v;
          state[name] = v;
          if (name === 'units') state.step = v === 'kg' ? 2.5 : 5;
          if (name === 'theme') applyTheme();
          save();
          render();
          const sc = $('.sheet', sheetRoot).scrollTop;
          openSettings();
          $('.sheet', sheetRoot).scrollTop = sc;
          return true;
        }
        if (act === 'install' && installPrompt) {
          installPrompt.prompt();
          installPrompt.userChoice.finally(() => { installPrompt = null; closeSheet(); });
          return true;
        }
        if (act === 'export') { exportData(); return true; }
        if (act === 'import') { importData(); return true; }
        if (act === 'reset') {
          if (!confirm('Erase all splits and workout logs from this device?')) return true;
          if (!confirm('Really? Export a backup first if you want to keep anything.')) return true;
          state = defaults(); ui.draft = null; ui.editSplitId = null; saveNow(); applyTheme(); closeSheet(); goTab('today');
          return true;
        }
      },
    });
  }

  async function exportData() {
    saveNow();
    const json = JSON.stringify({ app: 'liftlog', exported: new Date().toISOString(), data: state }, null, 1);
    const name = `liftlog-backup-${todayKey()}.json`;
    try {
      const file = new File([json], name, { type: 'application/json' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], title: 'LiftLog backup' });
        return;
      }
    } catch (e) {
      if (e && e.name === 'AbortError') return;
    }
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([json], { type: 'application/json' }));
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    toast('Backup downloaded');
  }

  function importData() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'application/json,.json';
    input.onchange = () => {
      const f = input.files && input.files[0];
      if (!f) return;
      const reader = new FileReader();
      reader.onload = () => {
        try {
          const parsed = JSON.parse(reader.result);
          const data = parsed.data || parsed;
          if (!Array.isArray(data.splits) || !Array.isArray(data.sessions)) throw new Error('bad file');
          if (!confirm(`Import ${data.splits.length} split(s) and ${data.sessions.length} workout(s)? This replaces what’s on this device.`)) return;
          state = Object.assign(defaults(), data);
          ui.draft = null; ui.editSplitId = null;
          saveNow(); applyTheme(); closeSheet(); render(true);
          toast('Backup imported');
        } catch (e) {
          toast('That file isn’t a LiftLog backup');
        }
      };
      reader.readAsText(f);
    };
    input.click();
  }

  function applyTheme() {
    const root = document.documentElement;
    if (state.theme === 'auto') delete root.dataset.theme;
    else root.dataset.theme = state.theme;
    const dark = state.theme === 'dark' || (state.theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    $$('meta[name="theme-color"]').forEach((m) => {
      if (state.theme === 'auto') m.content = m.media.includes('dark') ? '#0e0f12' : '#f4f4f1';
      else m.content = dark ? '#0e0f12' : '#f4f4f1';
    });
  }

  /* ================= rest timer ================= */

  const restEl = $('#restTimer');
  const rest = { end: 0, total: 0, tick: null, hideAt: 0 };

  function startRest(sec) {
    rest.total = sec;
    rest.end = Date.now() + sec * 1000;
    rest.hideAt = 0;
    restEl.classList.remove('finished');
    restEl.hidden = false;
    clearInterval(rest.tick);
    rest.tick = setInterval(tickRest, 250);
    tickRest();
  }

  function stopRest() {
    clearInterval(rest.tick);
    rest.tick = null;
    restEl.hidden = true;
  }

  function tickRest() {
    const left = Math.ceil((rest.end - Date.now()) / 1000);
    if (left <= 0) {
      if (!restEl.classList.contains('finished')) {
        restEl.classList.add('finished');
        vibrate([200, 100, 200]);
        rest.hideAt = Date.now() + 8000;
      }
      $('#restTime').textContent = 'Go!';
      $('#restBar').style.width = '0%';
      if (Date.now() > rest.hideAt) stopRest();
      return;
    }
    $('#restTime').textContent = `${Math.floor(left / 60)}:${pad(left % 60)}`;
    $('#restBar').style.width = `${Math.max(0, Math.min(100, (left / rest.total) * 100))}%`;
  }

  /* ================= toast ================= */

  let toastTimer = null;
  function toast(msg) {
    const t = $('#toast');
    t.textContent = msg;
    t.hidden = false;
    t.style.animation = 'none';
    void t.offsetWidth;
    t.style.animation = '';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.hidden = true; }, 2400);
  }

  /* ================= events ================= */

  $('#tabbar').addEventListener('click', (e) => {
    const b = e.target.closest('[data-tab]');
    if (b) { vibrate(5); goTab(b.dataset.tab); }
  });

  function commonAction(act, el) {
    switch (act) {
      case 'settings': openSettings(); return true;
      case 'go-today': ui.date = todayKey(); ui.draft = null; render(); return true;
      case 'pick-date': ui.date = el.dataset.date; ui.draft = null; render(); return true;
      case 'shift-date': ui.date = addDays(ui.date, +el.dataset.n); ui.draft = null; render(); return true;
      case 'open-date': ui.date = el.dataset.date; ui.draft = null; goTab('today'); return true;
      case 'use-template': useTemplate(el.dataset.tpl, false); return true;
      case 'start-day': startDay(el.dataset.day); return true;
      case 'hist-mode': ui.histMode = el.dataset.mode; render(); return true;
      case 'ex-history': openExerciseHistory(el.dataset.name); return true;
      case 'rest-add':
        rest.end += +el.dataset.sec * 1000;
        rest.total = Math.max(rest.total, Math.ceil((rest.end - Date.now()) / 1000));
        restEl.classList.remove('finished');
        tickRest();
        return true;
      case 'rest-stop': stopRest(); return true;
    }
    return false;
  }

  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-act]');
    if (!el || el.disabled) return;
    const act = el.dataset.act;

    if (sheetRoot.contains(el)) {
      if (act === 'sheet-close') { closeSheet(); return; }
      if (act === 'fstep') {
        const input = $('#' + el.dataset.target, sheetBody);
        const step = +el.dataset.step;
        const v = Math.max(0, Math.round((num(input.value) + +el.dataset.d * step) * 100) / 100);
        input.value = fmt(v);
        vibrate(5);
        return;
      }
      if (sheetCtx && sheetCtx.onAct && sheetCtx.onAct(act, el)) return;
      commonAction(act, el);
      return;
    }

    if (commonAction(act, el)) return;
    if (ui.tab === 'today') workoutAction(act, el);
    else if (ui.tab === 'plan') planAction(act, el);
  });

  // Typing into weight / reps / names.
  view.addEventListener('input', (e) => {
    const t = e.target;
    const f = t.dataset.f;
    if (!f) return;
    if (f === 'w' || f === 'r') {
      const sess = currentSession();
      const ex = sess && findEx(sess, t);
      const row = t.closest('[data-set]');
      if (!ex || !row) return;
      const v = f === 'w' ? num(t.value) : Math.round(num(t.value));
      setValue(sess, ex, +row.dataset.set, f, v, null);
    } else if (f === 'split-name') {
      const sp = splitById(ui.editSplitId);
      sp.name = t.value.trim() || 'Untitled split';
      $('#title').textContent = sp.name;
      save();
    } else if (f === 'day-name') {
      const sp = splitById(ui.editSplitId);
      const day = sp.days.find((d) => d.id === t.closest('[data-day]').dataset.day);
      day.name = t.value.trim() || 'Untitled day';
      ui.draft = null;
      save();
    } else if (f === 'hist-q') {
      ui.histQuery = t.value;
      const q = t.value.trim().toLowerCase();
      $('#exList').innerHTML = exerciseListHtml(exerciseIndex().filter((x) => !q || x.name.toLowerCase().includes(q)));
    }
  });

  // Tidy up the value shown after typing (e.g. "60," -> "60").
  view.addEventListener('change', (e) => {
    const t = e.target;
    if (t.dataset.f !== 'w' && t.dataset.f !== 'r') return;
    const sess = currentSession();
    const ex = sess && findEx(sess, t);
    const row = t.closest('[data-set]');
    if (ex && row) {
      const s = ex.sets[+row.dataset.set];
      t.value = t.dataset.f === 'w' ? fmt(s.w) : s.r;
    }
  });

  // Select the whole number on focus so you can just type the new one.
  document.addEventListener('focusin', (e) => {
    const t = e.target;
    if (t.tagName === 'INPUT' && (t.dataset.f === 'w' || t.dataset.f === 'r' || t.closest('.stepper'))) {
      setTimeout(() => { try { t.select(); } catch (err) { /* ignore */ } }, 0);
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
      if (sheetOpen && e.target.id === 'fName') {
        const saveBtn = $('[data-act="form-save"]', sheetBody);
        if (saveBtn) { saveBtn.click(); return; }
      }
      e.target.blur();
    }
    if (e.key === 'Escape' && sheetOpen) closeSheet();
  });

  sheetBody.addEventListener('input', (e) => { if (sheetCtx && sheetCtx.onInput) sheetCtx.onInput(e); });

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);

  // A new day may have started while the app sat in the background.
  let lastSeenDay = todayKey();
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) return;
    const t = todayKey();
    if (t !== lastSeenDay) {
      if (ui.date === lastSeenDay) { ui.date = t; ui.draft = null; }
      lastSeenDay = t;
      render();
    }
    if (rest.tick) tickRest();
  });

  /* ================= boot ================= */

  applyTheme();
  render();

  if ('serviceWorker' in navigator && /^https?:$/.test(location.protocol)) {
    window.addEventListener('load', () => { navigator.serviceWorker.register('sw.js').catch(() => {}); });
  }
  if (navigator.storage && navigator.storage.persist) {
    navigator.storage.persisted().then((p) => { if (!p) navigator.storage.persist(); }).catch(() => {});
  }
})();
