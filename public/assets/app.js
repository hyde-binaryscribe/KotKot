/* KotKot — application du poulailler familial. JavaScript simple, sans dépendance. */
'use strict';

(() => {
  const API = 'api.php';
  const app = document.getElementById('app');
  const toastEl = document.getElementById('toast');

  // ------------------------------------------------------------------ petites aides
  const h = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const uid = () => (crypto.randomUUID ? crypto.randomUUID() : 'id-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10));
  const store = {
    get(key, fallback) { try { const v = localStorage.getItem(key); return v === null ? fallback : JSON.parse(v); } catch { return fallback; } },
    set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* stockage indisponible */ } },
  };
  const plural = (n, one, many) => `${n} ${n > 1 ? many : one}`;
  const WEEKDAYS = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

  const I = {
    egg: '<svg viewBox="0 0 24 24" fill="#fff" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.5C8.2 2.5 5 9.2 5 14.2a7 7 0 0 0 14 0c0-5-3.2-11.7-7-11.7z"/></svg>',
    eggPlus: '<svg viewBox="0 0 24 24" fill="#fff" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.5C8.2 2.5 5 9.2 5 14.2a7 7 0 0 0 14 0c0-5-3.2-11.7-7-11.7z"/><path d="M12 10.5v6M9 13.5h6"/></svg>',
    tasks: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 6h10M10 12h10M10 18h10"/><path d="M3 6l1.5 1.5L7 5M3 12l1.5 1.5L7 11"/><rect x="3" y="16.5" width="4" height="3" rx=".5"/></svg>',
    hen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 10.5c0 4.3 2.9 7.5 6.8 7.5 3.6 0 6.2-2.6 6.2-5.6 0-1.9-1.1-3.1-2.7-3.1-1.4 0-2.3.9-2.8 1.9"/><path d="M6 10.5C6 7.4 7 5 9 5c1.1 0 1.9.8 1.9 1.9L13 8l-2.2.9"/><path d="M8 5.2c.1-1.2.8-2 1.6-2 .6 0 .9.5.8 1.2"/><path d="M10 18l-.6 3M14 18l.6 3M8 21h3M13 21h3"/><circle cx="8.8" cy="7.4" r=".6" fill="currentColor"/></svg>',
    door: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17"/><path d="M3 21h18"/><path d="M15 11v2"/></svg>',
    gear: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    back: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>',
    chev: '<svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
    plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
    camera: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
    share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>',
    user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>',
    heart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/><path d="M8 11h2l1-2 2 4 1-2h2"/></svg>',
    google: '<svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>',
    apple: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.4 12.6c0-2.4 2-3.6 2.1-3.7-1.1-1.7-2.9-1.9-3.5-1.9-1.5-.2-2.9.9-3.7.9-.8 0-1.9-.9-3.2-.8-1.6 0-3.1 1-4 2.4-1.7 3-.4 7.4 1.2 9.8.8 1.2 1.8 2.5 3 2.4 1.2 0 1.7-.8 3.1-.8 1.5 0 1.9.8 3.2.8 1.3 0 2.2-1.2 3-2.4.9-1.4 1.3-2.7 1.3-2.8-.1 0-2.5-1-2.5-3.9zM14 5.5c.7-.8 1.1-1.9 1-3-1 0-2.1.7-2.8 1.5-.6.7-1.2 1.8-1 2.9 1.1.1 2.1-.6 2.8-1.4z"/></svg>',
    calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
  };

  // ------------------------------------------------------------------ dates (fuseau du foyer)
  const tz = () => S.settings?.timezone || 'Europe/Paris';
  const dayKey = (ts) => new Intl.DateTimeFormat('en-CA', { timeZone: tz(), year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(ts * 1000));
  const fmtTime = (ts) => {
    const p = new Intl.DateTimeFormat('fr-FR', { timeZone: tz(), hour: 'numeric', minute: '2-digit' }).formatToParts(new Date(ts * 1000));
    const get = (t) => p.find((x) => x.type === t)?.value ?? '';
    return `${Number(get('hour'))} h ${get('minute')}`;
  };
  const fmtWhen = (ts) => {
    const now = Date.now() / 1000;
    if (dayKey(ts) === dayKey(now)) return `aujourd'hui ${fmtTime(ts)}`;
    if (dayKey(ts) === dayKey(now - 86400)) return `hier ${fmtTime(ts)}`;
    const d = new Intl.DateTimeFormat('fr-FR', { timeZone: tz(), weekday: 'short', day: 'numeric', month: 'short' }).format(new Date(ts * 1000));
    return `${d} ${fmtTime(ts)}`;
  };
  const fmtDay = (key) => new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(key + 'T12:00:00Z'));

  // ------------------------------------------------------------------ état et serveur
  let S = store.get('kk-state', null) || { setup: true, auth: false, people: [] };
  let outbox = store.get('kk-outbox', []);
  let online = navigator.onLine;
  let loaded = false;

  class ApiError extends Error {
    constructor(message, status) { super(message); this.status = status; }
  }

  async function post(action, body, isForm = false) {
    let res;
    try {
      res = await fetch(`${API}?a=${encodeURIComponent(action)}${isForm ? '&id=' + encodeURIComponent(body.id) : ''}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: isForm ? { 'X-KotKot': '1' } : { 'Content-Type': 'application/json', 'X-KotKot': '1' },
        body: isForm ? body.form : JSON.stringify(body),
      });
    } catch {
      setOnline(false);
      throw new ApiError('Pas de connexion internet.', 0);
    }
    setOnline(true);
    let data = null;
    try { data = await res.json(); } catch { /* réponse vide */ }
    if (!res.ok || !data?.ok) {
      if (res.status === 401) { S = { ...S, auth: false }; saveState(); }
      throw new ApiError(data?.error || 'Petit souci technique, réessayez.', res.status);
    }
    return data;
  }

  function saveState() { store.set('kk-state', S); }
  function setState(next) { S = next; saveState(); }
  function setOnline(v) {
    if (online === v) return;
    online = v;
    backgroundRender();
    if (v) flush();
  }

  /*
   * Screens are redrawn from scratch. Two safeguards so nobody loses what they typed:
   *  - automatic redraws (refresh every minute, back from another app, sync) wait while
   *    someone is typing in a field;
   *  - every form keeps a draft per screen: going to another screen and back, or an error
   *    message, gives the text back. Passwords and codes stay in memory only.
   */
  const FIELD = 'input:not([type=file]):not([type=button]):not([type=submit]), textarea, select';
  let pendingRender = false;
  const typing = () => { const a = document.activeElement; return !!(a && app.contains(a) && a.matches(FIELD)); };
  function backgroundRender() {
    if (typing()) { pendingRender = true; return; }
    pendingRender = false;
    render();
  }
  document.addEventListener('focusout', () => {
    setTimeout(() => { if (pendingRender && !typing()) { pendingRender = false; render(); } }, 0);
  });

  const SECRET = (el) => el.type === 'password' || /^(pin|pin2|oldPin|newPin|password|oldPassword|newPassword)$/.test(el.name);
  let drafts = {};
  try { drafts = JSON.parse(sessionStorage.getItem('kk-drafts') || '{}') || {}; } catch { drafts = {}; }
  const routeKey = () => location.hash || '#/';
  function fieldKey(el) {
    if (el.id) return el.id;
    const scope = el.form || app;
    return `${el.form?.dataset.form || ''}:${el.name}:${Array.from(scope.querySelectorAll(`[name="${CSS.escape(el.name)}"]`)).indexOf(el)}`;
  }
  function saveDrafts() {
    const safe = {};
    for (const [route, fields] of Object.entries(drafts)) {
      const kept = Object.fromEntries(Object.entries(fields).filter(([, f]) => !f.secret));
      if (Object.keys(kept).length) safe[route] = kept;
    }
    try { sessionStorage.setItem('kk-drafts', JSON.stringify(safe)); } catch { /* stockage indisponible */ }
  }
  function rememberField(el) {
    if (!app.contains(el) || !el.matches(FIELD) || !(el.name || el.id)) return;
    const route = routeKey();
    drafts[route] = drafts[route] || {};
    drafts[route][fieldKey(el)] = { v: el.value, secret: SECRET(el) };
    saveDrafts();
  }
  function restoreDrafts() {
    const fields = drafts[routeKey()];
    if (!fields) return;
    app.querySelectorAll(FIELD).forEach((el) => {
      const d = fields[fieldKey(el)];
      if (d && el.value !== d.v) el.value = d.v;
    });
  }
  function takeFormDraft(form) {
    const route = routeKey();
    const taken = {};
    form.querySelectorAll(FIELD).forEach((el) => {
      const k = fieldKey(el);
      if (drafts[route]?.[k]) { taken[k] = drafts[route][k]; delete drafts[route][k]; }
    });
    saveDrafts();
    return { route, taken };
  }
  function giveFormDraftBack({ route, taken }) {
    drafts[route] = { ...taken, ...(drafts[route] || {}) };
    saveDrafts();
  }
  document.addEventListener('input', (ev) => rememberField(ev.target));

  async function refresh() {
    try {
      const res = await fetch(`${API}?a=state`, { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      online = true;
      if (data.ok && outbox.length === 0) setState(data.state);
    } catch {
      online = false;
    }
    loaded = true;
    backgroundRender();
  }

  /** Changes that must never be lost: kept on the phone until the server has them. */
  function enqueue(action, body) {
    body.clientId = body.clientId || uid();
    body.at = body.at || Date.now();
    outbox.push({ action, body });
    store.set('kk-outbox', outbox);
    saveState();
    render();
    flush();
    return body.clientId;
  }

  let flushing = false;
  async function flush() {
    if (flushing) return;
    flushing = true;
    while (outbox.length) {
      try {
        const data = await post(outbox[0].action, outbox[0].body);
        outbox.shift();
        store.set('kk-outbox', outbox);
        if (outbox.length === 0) setState(data.state);
      } catch (e) {
        if (e.status === 0) break; // hors ligne : on réessaiera
        outbox.shift(); // refusé par le serveur : on l'abandonne
        store.set('kk-outbox', outbox);
        toast(e.message);
        if (e.status === 401) break;
      }
    }
    flushing = false;
    backgroundRender();
  }

  /** Actions that need the server right now (réglages, fiches...). */
  async function call(action, body = {}) {
    try {
      const data = await post(action, body);
      setState(data.state);
      return data;
    } catch (e) {
      showError(e.status === 0 ? 'Pas de connexion internet. Réessayez quand le téléphone capte.' : e.message);
      throw e;
    }
  }

  // ------------------------------------------------------------------ toast avec « Annuler »
  let toastTimer = null;
  let toastUndo = null;
  function toast(text, undo) {
    toastUndo = undo || null;
    toastEl.innerHTML = `<span>${h(text)}</span>${undo ? '<button type="button" data-act="toastUndo">Annuler</button>' : ''}`;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, undo ? 8000 : 4000);
  }
  function hideToast() {
    toastEl.classList.remove('show');
    toastUndo = null;
    setTimeout(() => { if (!toastEl.classList.contains('show')) toastEl.innerHTML = ''; }, 250);
  }

  let errorText = '';
  function showError(text) { errorText = text; render(); }

  /** Re-render without losing what was typed in the form (for in-form toggles). */
  function keepForm(fn) {
    const values = {};
    app.querySelectorAll('input[id], select[id], textarea[id]').forEach((el) => { values[el.id] = el.value; });
    fn();
    Object.entries(values).forEach(([id, v]) => { const el = document.getElementById(id); if (el) el.value = v; });
  }

  // ------------------------------------------------------------------ navigation
  const go = (hash) => { if (location.hash !== hash) location.hash = hash; else render(); };
  window.addEventListener('hashchange', () => { errorText = ''; render(); window.scrollTo(0, 0); });

  function route() {
    const parts = location.hash.replace(/^#\/?/, '').split('/').filter(Boolean);
    return { name: parts[0] || 'accueil', id: parts[1], sub: parts[2] };
  }

  const back = (hash, label) => `<a class="back" href="${hash}">${I.back}${h(label)}</a>`;
  const err = () => (errorText ? `<div class="error" role="alert">${h(errorText)}</div>` : '');
  const offlineBanner = () => {
    if (online && outbox.length === 0) return '';
    const n = outbox.length;
    return `<div class="offline">${online ? 'Envoi en cours…' : 'Pas de connexion'}${n ? ` · ${plural(n, 'changement', 'changements')} en attente` : ''}</div>`;
  };
  const me = () => S.me || null;
  const avatar = (p, cls = '') => `<span class="avatar ${cls}" style="background:${h(p.color)}" aria-hidden="true">${h((p.name || '?')[0].toUpperCase())}</span>`;
  const henAvatar = (hen, cls = '') => hen.photo
    ? `<span class="avatar ${cls}" aria-hidden="true"><img src="${photoUrl(hen.photo)}" alt=""></span>`
    : `<span class="avatar ${cls}" style="background:${henColor(hen)}" aria-hidden="true">${h(hen.name[0].toUpperCase())}</span>`;
  const photoUrl = (photoId) => `${API}?a=photo&id=${photoId}`;
  const henColor = (hen) => ['#B5470B', '#1E3A5F', '#1F6B3A', '#6B3A8A', '#8A5A12', '#5B6B57', '#9B1C4A'][hen.id % 7];

  // ------------------------------------------------------------------ écrans
  function render() {
    if (!loaded && !S.auth) { app.innerHTML = '<p class="loading">Chargement…</p>'; return; }
    let html;
    const r = route();
    if (!S.auth) {
      const open = { creer: viewSignup, connexion: viewAccountLogin, inscription: viewRegister, rejoindre: viewJoin, 'mot-de-passe-oublie': viewForgot, 'nouveau-mot-de-passe': viewReset, foyer: viewHouseholdCreate };
      if (open[r.name]) html = open[r.name](r);
      else html = S.account ? viewMyHouseholds() : viewWelcome();
    } else {
      const views = {
        accueil: viewHome, oeufs: (x) => (x.id === 'calendrier' ? viewEggCalendar() : x.id === 'jour' ? viewEggDay(x) : viewEggs()), porte: viewDoor, taches: r.id === 'gerer' ? viewTasksManage : viewTasks,
        poules: viewHens, poule: viewHen, reglages: viewSettings, qui: viewWho, sante: viewHealth, race: viewBreed,
        compte: viewAccount, premium: viewPremium, foyer: viewHouseholdCreate, connexion: viewAccountLogin, inscription: viewRegister,
        'nouveau-mot-de-passe': viewReset, 'mot-de-passe-oublie': viewForgot, rejoindre: viewJoin,
      };
      html = (views[r.name] || viewHome)(r);
    }
    app.innerHTML = html;
    restoreDrafts();
    afterRender();
  }

  function afterRender() {
    // PIN pad keeps its own state so focus is not lost between taps
    if (app.querySelector('.pad')) updateDots();
    if (app.querySelector('[data-push-status]')) refreshPushStatus();
  }

  // ---------- bienvenue (personne de connecté)
  function viewWelcome() {
    const last = store.get('kk-join', null);
    return `
      <div class="brand" style="justify-content:center;margin-top:1rem">${I.egg} KotKot</div>
      <h1 style="text-align:center">Le poulailler de la famille</h1>
      <p class="muted" style="text-align:center;margin:0">Les œufs, les tâches, les poules et la porte, sur le téléphone de chacun.</p>
      ${err()}
      ${last ? `<a class="btn primary big" href="#/rejoindre/${h(last.code)}">${I.hen} Revenir dans « ${h(last.name)} »</a>` : ''}
      <a class="btn ${last ? '' : 'primary'} big" href="#/rejoindre">${I.door} Rejoindre ma famille</a>
      <p class="muted" style="margin:-.4rem 0 0;text-align:center">Avec le code d'invitation donné par un proche.</p>
      ${S.registration ? `<a class="btn big" href="#/creer">${I.plus} Créer mon poulailler</a>` : ''}
      ${socialButtons()}
      <a class="btn" href="#/connexion">J'ai déjà un compte</a>`;
  }

  /** « Continuer avec Google / Apple », shown only when the site has their keys. */
  function socialButtons() {
    const o = S.oauth || {};
    if (!o.google && !o.apple) return '';
    return `<div class="stack" style="gap:.6rem">
      ${o.google ? `<a class="btn social google" href="${API}?a=oauth_start&provider=google">${I.google} Continuer avec Google</a>` : ''}
      ${o.apple ? `<a class="btn social apple" href="${API}?a=oauth_start&provider=apple">${I.apple} Continuer avec Apple</a>` : ''}
    </div>`;
  }

  const OAUTH_ERRORS = {
    annule: 'Connexion annulée.',
    expire: 'La connexion a pris trop de temps. Recommencez.',
    email: 'Votre compte Google ou Apple ne donne pas d\'adresse e-mail vérifiée. Utilisez plutôt un e-mail et un mot de passe.',
    ferme: 'Les inscriptions sont fermées sur ce site.',
    indisponible: 'Cette façon de se connecter n\'est pas disponible ici.',
    erreur: 'La connexion n\'a pas marché. Réessayez dans un instant.',
  };

  /** A family member's own account (no household needed). */
  function viewRegister() {
    return `
      ${back('#/connexion', 'Connexion')}
      <h1>Créer mon compte</h1>
      <p class="muted" style="margin:0">${S.auth ? `Pour retrouver « ${h(S.household?.name || '')} » sur tous vos appareils, sans code.` : 'Ensuite, rejoignez votre famille avec son code d\'invitation.'}</p>
      ${err()}
      ${socialButtons()}
      <form class="stack" data-form="register">
        <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="190"></div>
        <div class="field"><label for="pw">Mot de passe (8 caractères ou plus)</label><input id="pw" name="password" type="password" autocomplete="new-password" required minlength="8"></div>
        <button class="btn primary big block" type="submit">Créer mon compte</button>
      </form>`;
  }

  // ---------- créer un compte + un poulailler
  function householdFields() {
    return `
      <div class="field"><label for="hname">Nom du poulailler</label>
        <input id="hname" name="householdName" maxlength="60" required placeholder="Ex. Les poules de la famille Martin"></div>
      <div class="field"><span class="lab">Qui s'occupe des poules ?</span>
        ${[1, 2, 3, 4].map((i) => `<input name="person" autocomplete="off" placeholder="Prénom ${i}" aria-label="Prénom ${i}" maxlength="40">`).join('')}
        <span class="muted">Vous pourrez en ajouter d'autres plus tard.</span></div>
      <div class="field"><label for="pin1">Code de la famille (4 chiffres)</label>
        <input id="pin1" name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" required>
        <span class="muted">Chacun le tapera une fois sur son téléphone.</span></div>
      <div class="field"><label for="pin2">Retapez le code</label>
        <input id="pin2" name="pin2" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" required></div>
      ${locationFields({})}`;
  }

  function locationFields(s) {
    return `
      <div class="field"><label for="city">Ville du poulailler</label>
        <input id="city" name="city" value="${h(s.city || '')}" maxlength="80" placeholder="Ex. Rennes"></div>
      <div class="grid2">
        <div class="field"><label for="lat">Latitude</label><input id="lat" name="lat" inputmode="decimal" value="${h(s.lat ?? '')}" placeholder="48.11"></div>
        <div class="field"><label for="lon">Longitude</label><input id="lon" name="lon" inputmode="decimal" value="${h(s.lon ?? '')}" placeholder="-1.68"></div>
      </div>
      <button class="btn block" type="button" data-act="locate">${I.pin} Utiliser ma position</button>
      <p class="muted" style="margin:0">Sert seulement à calculer le lever et le coucher du soleil.</p>`;
  }

  function viewSignup() {
    return `
      ${back('#/', 'Retour')}
      <h1>Créer mon poulailler</h1>
      ${S.billing ? '<p class="muted" style="margin:0">Gratuit, avec 30 jours de Premium offerts. Sans carte bancaire.</p>' : ''}
      ${err()}
      ${S.oauth?.google || S.oauth?.apple ? `<div class="card stack"><b>Le plus rapide</b>${socialButtons()}
        <p class="muted" style="margin:0">Puis vous créerez votre poulailler.</p></div><h2>Ou avec un e-mail</h2>` : ''}
      <form class="stack" data-form="signup">
        <h2>Votre compte</h2>
        <p class="muted" style="margin:0">Pour retrouver votre poulailler sur un autre téléphone. Seul le responsable du foyer en a besoin.</p>
        <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="190"></div>
        <div class="field"><label for="pw">Mot de passe (8 caractères ou plus)</label><input id="pw" name="password" type="password" autocomplete="new-password" required minlength="8"></div>
        <h2>Votre poulailler</h2>
        ${householdFields()}
        <button class="btn primary big block" type="submit">C'est parti</button>
      </form>`;
  }

  function viewHouseholdCreate() {
    if (!S.account) return viewAccountLogin();
    return `
      ${back(S.auth ? '#/compte' : '#/', 'Retour')}
      <h1>Nouveau poulailler</h1>
      ${err()}
      <form class="stack" data-form="householdCreate">${householdFields()}
        <button class="btn primary big block" type="submit">Créer</button></form>`;
  }

  function signupPayload(fd) {
    const pin = fd.get('pin');
    if (!/^\d{4}$/.test(pin)) throw new Error('Le code de la famille doit faire exactement 4 chiffres.');
    if (pin !== fd.get('pin2')) throw new Error('Les deux codes ne sont pas pareils.');
    const people = fd.getAll('person').map((s) => s.trim()).filter(Boolean);
    if (!people.length) throw new Error('Indiquez au moins un prénom.');
    return { householdName: fd.get('householdName'), people, pin, city: fd.get('city'), lat: fd.get('lat'), lon: fd.get('lon') };
  }

  // ---------- compte : connexion, mot de passe
  function viewAccountLogin(r = {}) {
    return `
      ${back(S.auth ? '#/reglages' : '#/', 'Retour')}
      <h1>${S.auth ? 'Mon compte' : 'Connexion'}</h1>
      <p class="muted" style="margin:0">${S.auth ? 'Avec un compte, vous retrouvez le poulailler sur tous vos appareils, sans code.' : 'Avec votre compte KotKot.'}</p>
      ${r.id && OAUTH_ERRORS[r.id] ? `<div class="error" role="alert">${h(OAUTH_ERRORS[r.id])}</div>` : ''}
      ${err()}
      ${socialButtons()}
      <form class="stack" data-form="accountLogin">
        <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="email" required></div>
        <div class="field"><label for="pw">Mot de passe</label><input id="pw" name="password" type="password" autocomplete="current-password" required></div>
        <button class="btn primary big block" type="submit">Se connecter</button>
      </form>
      <a class="btn" href="#/mot-de-passe-oublie">Mot de passe oublié ?</a>
      ${S.auth || S.registration ? '<a class="btn ghost" href="#/inscription">Pas de compte ? En créer un</a>' : ''}`;
  }

  let forgotMessage = '';
  function viewForgot() {
    return `
      ${back('#/connexion', 'Connexion')}
      <h1>Mot de passe oublié</h1>
      ${err()}
      ${forgotMessage ? `<div class="card" role="status"><b>${h(forgotMessage)}</b></div>` : `
      <p class="muted" style="margin:0">Indiquez votre e-mail : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
      <form class="stack" data-form="forgot">
        <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="email" required></div>
        <button class="btn primary big block" type="submit">Envoyer le lien</button>
      </form>`}`;
  }

  function viewReset(r) {
    return `
      <h1>Nouveau mot de passe</h1>
      ${err()}
      <form class="stack" data-form="reset" data-token="${h(r.id || '')}">
        <div class="field"><label for="pw">Nouveau mot de passe (8 caractères ou plus)</label><input id="pw" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
        <button class="btn primary big block" type="submit">Enregistrer</button>
      </form>`;
  }

  /** Account connected but this phone is in no household: choose one. */
  function viewMyHouseholds() {
    const list = S.account.households;
    return `
      <div class="topbar"><div class="brand">${I.egg} KotKot</div>
        <button class="btn" type="button" data-act="accountLogout">Déconnexion</button></div>
      <h1>Mes poulaillers</h1>
      <p class="muted" style="margin:0">${h(S.account.email)}</p>
      ${err()}
      <div class="stack">
        ${list.map((x) => `<button class="tile" type="button" data-act="enterHousehold" data-id="${x.id}">${I.hen}<span><span class="t-title" style="display:block">${h(x.name)}</span>
          <span class="t-sub">${x.role === 'owner' ? 'Vous en êtes responsable' : 'Membre de la famille'}</span></span></button>`).join('') || '<p class="muted">Aucun poulailler pour l\'instant.</p>'}
      </div>
      <a class="btn primary" href="#/rejoindre">${I.door} Rejoindre ma famille</a>
      ${S.registration ? `<a class="btn" href="#/foyer/nouveau">${I.plus} Créer un poulailler</a>` : ''}`;
  }

  // ---------- rejoindre un poulailler : code d'invitation, puis qui + code familial
  let joinInfo = null;
  let pinBuffer = '';
  let loginWho = store.get('kk-last-person', null);

  function viewJoin(r) {
    const code = (r.id || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (code && joinInfo?.code !== code) {
      lookupJoin(code);
      return '<p class="loading">Recherche du poulailler…</p>';
    }
    if (!code || !joinInfo) {
      return `
        ${back('#/', 'Retour')}
        <h1>Rejoindre ma famille</h1>
        <p class="muted" style="margin:0">Demandez le <b>code d'invitation</b> à la personne qui a créé le poulailler (dans ses Réglages).</p>
        ${err()}
        <form class="stack" data-form="joinCode">
          <div class="field"><label for="code">Code d'invitation</label>
            <input id="code" name="code" autocomplete="off" autocapitalize="characters" maxlength="12" required placeholder="Ex. KR7P-2XM9" style="font-size:1.6rem;letter-spacing:.15em;text-transform:uppercase"></div>
          <button class="btn primary big block" type="submit">Continuer</button>
        </form>`;
    }
    if (!joinInfo.people.some((p) => p.id === loginWho)) loginWho = joinInfo.people[0]?.id ?? null;
    return `
      ${back('#/', 'Retour')}
      <h1 style="text-align:center;font-size:1.45rem">${h(joinInfo.name)}</h1>
      <p style="text-align:center;margin:0">Qui êtes-vous ?</p>
      <div class="people-pick">
        ${joinInfo.people.map((p) => `<button type="button" class="person" data-act="loginWho" data-id="${p.id}" aria-pressed="${p.id === loginWho}">${avatar(p)}<span>${h(p.name)}</span></button>`).join('')}
      </div>
      ${err()}
      ${pinPad('Code de la famille')}`;
  }

  async function lookupJoin(code) {
    try {
      const data = await post('join_lookup', { code });
      joinInfo = data.join;
      errorText = '';
    } catch (e) {
      joinInfo = null;
      errorText = e.status === 0 ? 'Pas de connexion internet.' : e.message;
      if (store.get('kk-join', null)?.code === code) store.set('kk-join', null);
      location.hash = '#/rejoindre';
    }
    render();
  }

  function pinPad(label) {
    return `
      <div class="stack" style="align-items:center"><span>${h(label)}</span>
        <div class="dots" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        <span class="sr" id="pin-status" aria-live="polite"></span></div>
      <div class="pad">
        ${[1, 2, 3, 4, 5, 6, 7, 8, 9].map((d) => `<button type="button" data-act="pinKey" data-key="${d}">${d}</button>`).join('')}
        <button type="button" class="flat" data-act="pinClear">Effacer</button>
        <button type="button" data-act="pinKey" data-key="0">0</button>
        <button type="button" class="flat" data-act="pinBack" aria-label="Effacer le dernier chiffre">←</button>
      </div>`;
  }

  function updateDots() {
    app.querySelectorAll('.dots span').forEach((el, i) => el.classList.toggle('on', i < pinBuffer.length));
    const st = document.getElementById('pin-status');
    if (st) st.textContent = `${pinBuffer.length} chiffre${pinBuffer.length > 1 ? 's' : ''} sur 4`;
  }

  async function submitPin() {
    const pin = pinBuffer;
    pinBuffer = '';
    try {
      const data = await post('join_login', { code: joinInfo.code, pin, personId: loginWho });
      store.set('kk-last-person', loginWho);
      store.set('kk-join', { code: joinInfo.code, name: joinInfo.name });
      errorText = '';
      setState(data.state);
      go('#/');
      flush();
    } catch (e) {
      showError(e.status === 0 ? 'Pas de connexion internet.' : e.message);
    }
  }

  // ---------- changer de personne
  function viewWho() {
    return `
      ${back('#/', 'Accueil')}
      <h1>Qui êtes-vous ?</h1>
      <div class="people-pick">
        ${S.people.map((p) => `<button type="button" class="person" data-act="switchPerson" data-id="${p.id}" aria-pressed="${p.id === me()?.id}">${avatar(p)}<span>${h(p.name)}</span></button>`).join('')}
      </div>
      ${err()}`;
  }
  // ---------- accueil
  function doorInfo() {
    const d = S.door || {};
    const now = Date.now() / 1000;
    const last = d.history?.[0];
    const by = last?.who ? ` par ${last.who}` : '';
    if (d.state === 'open') {
      if (d.shouldClose) return { cls: 'alert', title: 'Pensez à fermer la porte !', sub: `Le soleil s'est couché à ${fmtTime(d.sunset)}` };
      return { cls: 'open', title: 'Porte ouverte', sub: d.sunset && now < d.sunset ? `À fermer après ${fmtTime(d.sunset)}` : `Ouverte ${fmtWhen(last.at)}${by}` };
    }
    if (d.state === 'closed') {
      if (d.shouldOpen) return { cls: '', title: 'Porte fermée', sub: 'Il fait jour : on peut ouvrir' };
      if (d.sunrise && now < d.sunrise) return { cls: '', title: 'Porte fermée', sub: `À ouvrir après ${fmtTime(d.sunrise)}` };
      return { cls: '', title: 'Porte fermée', sub: `Fermée ${fmtWhen(last.at)}${by}` };
    }
    return { cls: '', title: 'La porte', sub: 'Touchez ici pour noter si elle est ouverte' };
  }

  function taskCounts() {
    const due = (S.tasks || []).filter((t) => t.dueToday);
    return { done: due.filter((t) => t.done).length, total: due.length };
  }

  function healthBanner() {
    const now = (S.health?.due || []).filter((d) => d.overdue || d.today);
    if (!now.length) return '';
    const d = now[0];
    const more = now.length > 1 ? ` (+${now.length - 1})` : '';
    return `<a class="door-banner alert" href="#/sante"><span class="badge">${I.heart}</span>
      <span class="grow"><span class="d-title" style="display:block">${h(HEALTH[d.kind]?.label || 'Soin')} à faire${more}</span>
      <span class="d-sub">${h(d.hen || 'Tout le poulailler')} · ${dueText(d)}</span></span>${I.chev}</a>`;
  }

  function viewHome() {
    const d = doorInfo();
    const tc = taskCounts();
    const hens = S.hens || [];
    const watch = hens.filter((x) => x.status === 'watch').length;
    return `
      <div class="topbar">
        <div class="stack" style="gap:.1rem">
          <div class="brand">${I.egg} KotKot</div>
          <a class="who-link" href="#/qui">Bonjour ${h(me()?.name || '')} · changer</a>
        </div>
        <a class="icon-btn" href="#/reglages" aria-label="Réglages">${I.gear}</a>
      </div>
      ${offlineBanner()}
      <a class="door-banner ${d.cls}" href="#/porte">
        <span class="badge">${I.door}</span>
        <span class="grow"><span class="d-title" style="display:block">${h(d.title)}</span><span class="d-sub">${h(d.sub)}</span></span>
        ${I.chev}
      </a>
      ${healthBanner()}
      <nav class="home-tiles" aria-label="Menu principal">
        <a class="tile yolk" href="#/oeufs">${I.egg}<span><span class="t-title" style="display:block">Œufs</span><span class="t-sub">${S.eggs?.today ?? 0} aujourd'hui</span></span></a>
        <a class="tile" href="#/taches">${I.tasks}<span><span class="t-title" style="display:block">Tâches</span><span class="t-sub">${tc.done} faite${tc.done > 1 ? 's' : ''} sur ${tc.total}</span></span></a>
        <a class="tile green" href="#/poules">${I.hen}<span><span class="t-title" style="display:block">Poules</span><span class="t-sub">${plural(hens.length, 'poule', 'poules')}${watch ? ` · ${watch} à surveiller` : ''}</span></span></a>
      </nav>`;
  }

  // ---------- œufs
  function viewEggs() {
    const e = S.eggs || { today: 0, last7: 0, last30: 0, days: [] };
    const max = Math.max(1, ...e.days.map((d) => d.count));
    return `
      ${back('#/', 'Accueil')}
      <h1>Les œufs</h1>
      ${offlineBanner()}
      <div class="card big-count"><span class="muted">Aujourd'hui</span><span class="num">${e.today}</span><span class="muted">${e.today > 1 ? 'œufs ramassés' : 'œuf ramassé'}</span></div>
      <button class="btn primary huge" type="button" data-act="egg" data-delta="1">${I.eggPlus} +1 œuf</button>
      <button class="btn" type="button" data-act="egg" data-delta="-1" ${e.today ? '' : 'disabled'}>−1 (je me suis trompé)</button>
      <a class="btn" href="#/oeufs/calendrier">${I.calendar} Calendrier · un jour oublié ?</a>
      <div class="grid2">
        <div class="card stat"><span class="label">7 derniers jours</span><span class="value">${e.last7}</span></div>
        <div class="card stat"><span class="label">30 derniers jours</span><span class="value">${e.last30}</span></div>
      </div>
      ${e.days.length ? `
      <div class="card stack">
        <h2 style="margin:0">Les 14 derniers jours</h2>
        <div class="bars" role="img" aria-label="${h(e.days.map((d) => `${fmtDay(d.day)} : ${d.count}`).join(', '))}">
          ${e.days.map((d, i) => `<span class="${i === e.days.length - 1 ? 'today' : ''}" style="height:${Math.round((d.count / max) * 100)}%"></span>`).join('')}
        </div>
        <div class="bars-axis"><span>${fmtDay(e.days[0].day)}</span><span>aujourd'hui</span></div>
      </div>` : ''}`;
  }

  function eggChange(delta) {
    const e = S.eggs;
    if (delta < 0 && e.today <= 0) return;
    const apply = (d) => {
      e.today = Math.max(0, e.today + d); e.last7 = Math.max(0, e.last7 + d); e.last30 = Math.max(0, e.last30 + d);
      const last = e.days[e.days.length - 1]; if (last) last.count = Math.max(0, last.count + d);
    };
    apply(delta);
    const clientId = enqueue('egg', { delta });
    if (navigator.vibrate) navigator.vibrate(30);
    toast(delta > 0 ? `+1 œuf · ${e.today} aujourd'hui` : '1 œuf retiré', () => {
      apply(-delta);
      enqueue('egg_undo', { clientId });
    });
  }

  // ---------- calendrier des œufs : on touche un jour, on indique le nombre ramassé
  const monthCache = {};
  let calMonth = null;
  const monthOf = (day) => day.slice(0, 7);
  const shiftMonth = (m, n) => {
    const [y, mo] = m.split('-').map(Number);
    return new Date(Date.UTC(y, mo - 1 + n, 1)).toISOString().slice(0, 7);
  };
  const fmtMonth = (m) => new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${m}-15T12:00:00Z`));
  const fmtDayLong = (day) => {
    const opts = { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' };
    if (day.slice(0, 4) !== S.today.slice(0, 4)) opts.year = 'numeric';
    const t = new Intl.DateTimeFormat('fr-FR', opts).format(new Date(`${day}T12:00:00Z`));
    return t.charAt(0).toUpperCase() + t.slice(1);
  };
  const oldestMonth = () => shiftMonth(monthOf(S.today), -24);

  async function loadMonth(m) {
    monthCache[m] = { ...(monthCache[m] || { month: m, days: {}, total: 0 }), loading: true };
    try {
      const data = await post('egg_month', { month: m });
      monthCache[m] = { ...data.month, fetchedAt: Date.now() };
    } catch {
      monthCache[m] = { ...monthCache[m], loading: false, fetchedAt: Date.now() };
    }
    if (route().name === 'oeufs') backgroundRender();
  }

  /** Cached month, refreshed when older than 15 s (someone else may have counted eggs). */
  function month(m) {
    const c = monthCache[m];
    if (!c || (!c.loading && Date.now() - (c.fetchedAt || 0) > 15000)) loadMonth(m);
    return monthCache[m];
  }

  function viewEggCalendar() {
    const today = S.today;
    calMonth = calMonth || monthOf(today);
    const m = calMonth;
    const c = month(m);
    const ready = !!c.fetchedAt;
    const [y, mo] = m.split('-').map(Number);
    const offset = (new Date(Date.UTC(y, mo - 1, 1)).getUTCDay() + 6) % 7;
    const count = new Date(Date.UTC(y, mo, 0)).getUTCDate();
    const cells = [];
    for (let i = 0; i < offset; i++) cells.push('<span aria-hidden="true"></span>');
    for (let d = 1; d <= count; d++) {
      const day = `${m}-${String(d).padStart(2, '0')}`;
      const n = c.days[day] || 0;
      if (day > today) {
        cells.push(`<span class="cal-day future" aria-hidden="true"><b>${d}</b></span>`);
      } else {
        cells.push(`<a class="cal-day ${n ? 'has' : ''} ${day === today ? 'today' : ''}" href="#/oeufs/jour/${day}"
          aria-label="${h(fmtDayLong(day))} : ${ready ? plural(n, 'œuf', 'œufs') : 'chargement'}"><b>${d}</b><span>${ready && n ? n : ''}</span></a>`);
      }
    }
    return `
      ${back('#/oeufs', 'Les œufs')}
      <h1>Calendrier</h1>
      <p class="muted" style="margin:0">Touchez un jour pour indiquer les œufs ramassés ce jour-là.</p>
      ${offlineBanner()}
      <div class="cal-head">
        <button class="btn" type="button" data-act="calMove" data-v="-1" aria-label="Mois précédent" ${m > oldestMonth() ? '' : 'disabled'}>‹</button>
        <b>${h(fmtMonth(m))}</b>
        <button class="btn" type="button" data-act="calMove" data-v="1" aria-label="Mois suivant" ${m < monthOf(today) ? '' : 'disabled'}>›</button>
      </div>
      <div class="cal" role="grid">
        ${['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'].map((d) => `<span class="cal-dow">${d}</span>`).join('')}
        ${cells.join('')}
      </div>
      <div class="card stat"><span class="label">Total de ${h(fmtMonth(m))}</span><span class="value">${ready ? plural(c.total, 'œuf', 'œufs') : '…'}</span></div>`;
  }

  function viewEggDay(r) {
    const day = /^\d{4}-\d{2}-\d{2}$/.test(r.sub || '') ? r.sub : '';
    if (!day || day > S.today || monthOf(day) < oldestMonth()) return viewEggCalendar();
    calMonth = monthOf(day);
    const c = month(calMonth);
    const n = c.fetchedAt ? (c.days[day] || 0) : null;
    const prev = addDays(day, -1);
    const next = addDays(day, 1);
    return `
      ${back('#/oeufs/calendrier', 'Calendrier')}
      <h1>${h(fmtDayLong(day))}</h1>
      ${offlineBanner()}
      <div class="card big-count"><span class="muted">Œufs ramassés ce jour-là</span><span class="num">${n === null ? '…' : n}</span></div>
      <div class="grid2">
        <button class="btn big" type="button" data-act="eggDay" data-day="${day}" data-delta="-1" ${n ? '' : 'disabled'}>−1</button>
        <button class="btn primary big" type="button" data-act="eggDay" data-day="${day}" data-delta="1" ${n === null ? 'disabled' : ''}>${I.eggPlus} +1</button>
      </div>
      <form class="card stack" data-form="eggDay" data-day="${day}">
        <div class="field"><label for="eggn">Ou tapez le nombre d'œufs</label>
          <input id="eggn" name="count" type="number" inputmode="numeric" min="0" max="500" required placeholder="${n ?? ''}"></div>
        <button class="btn" type="submit">Enregistrer</button>
      </form>
      <div class="grid2">
        ${prev >= `${oldestMonth()}-01` ? `<a class="btn" href="#/oeufs/jour/${prev}">‹ La veille</a>` : '<span></span>'}
        ${next <= S.today ? `<a class="btn" href="#/oeufs/jour/${next}">Le lendemain ›</a>` : ''}
      </div>`;
  }

  /** Sets the count of a day: right away on screen, sent when possible, with Annuler. */
  function eggSetDay(day, count) {
    const m = monthOf(day);
    const c = monthCache[m] || (monthCache[m] = { month: m, days: {}, total: 0, fetchedAt: Date.now() });
    const before = c.days[day] || 0;
    count = Math.max(0, Math.min(500, Math.round(count)));
    if (count === before) return;
    const apply = (v) => {
      const diff = v - (c.days[day] || 0);
      if (v) c.days[day] = v; else delete c.days[day];
      c.total += diff;
      const e = S.eggs;
      const ago = daysBetween(day, S.today);
      if (ago === 0) e.today = Math.max(0, e.today + diff);
      if (ago < 7) e.last7 = Math.max(0, e.last7 + diff);
      if (ago < 30) e.last30 = Math.max(0, e.last30 + diff);
      const bar = e.days.find((x) => x.day === day);
      if (bar) bar.count = Math.max(0, bar.count + diff);
    };
    apply(count);
    const clientId = enqueue('egg_set', { day, count });
    if (navigator.vibrate) navigator.vibrate(30);
    toast(`${fmtDayLong(day)} : ${plural(count, 'œuf', 'œufs')}`, () => {
      apply(before);
      enqueue('egg_undo', { clientId });
    });
  }

  // ---------- porte
  function viewDoor() {
    const d = S.door || {};
    const info = doorInfo();
    const isOpen = d.state === 'open';
    const s = S.settings || {};
    return `
      ${back('#/', 'Accueil')}
      <h1>La porte</h1>
      ${offlineBanner()}
      <div class="status-card ${info.cls}">
        <span>En ce moment</span>
        <span class="s-title">${h(d.state === 'unknown' ? 'Pas encore notée' : info.title)}</span>
        <span>${h(info.sub)}</span>
      </div>
      <button class="btn primary big" type="button" data-act="door" data-action="${isOpen ? 'close' : 'open'}">${isOpen ? "J'ai fermé la porte" : "J'ai ouvert la porte"}</button>
      ${d.sunrise ? `
      <div class="grid2">
        <div class="card stat"><span class="label">Lever du soleil</span><span class="value">${fmtTime(d.sunrise)}</span></div>
        <div class="card stat"><span class="label">Coucher du soleil</span><span class="value">${fmtTime(d.sunset)}</span></div>
      </div>
      <p class="hint">${I.bell}<span>Rappel à ${fmtTime(d.remindAt)} si la porte est encore ouverte (${s.reminderDelay} min après le coucher du soleil).</span></p>`
      : `<p class="hint">${I.pin}<span>Indiquez la ville dans les <a href="#/reglages">Réglages</a> pour avoir les heures du soleil et le rappel.</span></p>`}
      <h2>Dernières fois</h2>
      <div class="card list">
        ${(d.history || []).length ? d.history.map((e) => `<div class="kv"><b style="text-align:left">${e.action === 'open' ? 'Ouverte' : 'Fermée'}${e.who ? ` par ${h(e.who)}` : ''}</b><span class="muted">${h(fmtWhen(e.at))}</span></div>`).join('') : '<p class="muted" style="margin:0">Rien de noté pour l\'instant.</p>'}
      </div>`;
  }

  function doorChange(action) {
    const d = S.door;
    const prev = { state: d.state, shouldClose: d.shouldClose, shouldOpen: d.shouldOpen };
    const clientId = uid();
    d.history = [{ action, at: Math.floor(Date.now() / 1000), who: me()?.name, clientId }, ...(d.history || [])].slice(0, 10);
    d.state = action === 'open' ? 'open' : 'closed';
    d.shouldClose = false; d.shouldOpen = false;
    enqueue('door', { action, clientId });
    toast(action === 'open' ? 'Porte ouverte, c\'est noté !' : 'Porte fermée, merci !', () => {
      d.history = d.history.filter((x) => x.clientId !== clientId);
      Object.assign(d, prev);
      enqueue('door_undo', { clientId });
    });
  }

  // ---------- tâches
  function taskSub(t) {
    if (t.done) return `Fait par ${h(t.doneBy || '?')} à ${fmtTime(t.doneAt)}`;
    if (t.frequency === 'daily') return 'Tous les jours';
    const day = `Le ${WEEKDAYS[t.weekday] || 'week-end'}`;
    return t.lateDays ? `${day} · <span class="late">en retard de ${plural(t.lateDays, 'jour', 'jours')}</span>` : day;
  }

  function viewTasks() {
    const tc = taskCounts();
    const tasks = S.tasks || [];
    const daily = tasks.filter((t) => t.frequency === 'daily');
    const weekly = tasks.filter((t) => t.frequency === 'weekly');
    const item = (t) => `<button type="button" class="task ${t.done ? 'done' : ''}" data-act="task" data-id="${t.id}" aria-pressed="${t.done}">
        <span class="box">${t.done ? I.check : ''}</span>
        <span><span class="label">${h(t.label)}</span><span class="sub">${taskSub(t)}</span></span></button>`;
    return `
      ${back('#/', 'Accueil')}
      <div class="row between"><h1>Aujourd'hui</h1><b style="font-size:1.15rem">${tc.done} sur ${tc.total}</b></div>
      ${offlineBanner()}
      <div class="stack">${daily.map(item).join('') || '<p class="muted">Aucune tâche quotidienne.</p>'}</div>
      ${weekly.length ? `<h2>Cette semaine</h2><div class="stack">${weekly.map(item).join('')}</div>` : ''}
      <a class="btn ghost" href="#/taches/gerer">${I.plus} Ajouter ou retirer des tâches</a>`;
  }

  function taskToggle(id) {
    const t = S.tasks.find((x) => x.id === id);
    if (!t) return;
    t.done = !t.done;
    t.doneBy = t.done ? me()?.name : null;
    t.doneAt = t.done ? Math.floor(Date.now() / 1000) : null;
    if (t.done) t.lateDays = 0;
    enqueue('task_set', { taskId: id, done: t.done });
    if (t.done && navigator.vibrate) navigator.vibrate(30);
  }

  let newTaskFreq = 'daily';
  function viewTasksManage() {
    return `
      ${back('#/taches', 'Tâches')}
      <h1>Gérer les tâches</h1>
      ${err()}
      <div class="card list">
        ${(S.tasks || []).map((t) => `<div class="row between"><span><b>${h(t.label)}</b><br><span class="muted">${t.frequency === 'daily' ? 'Tous les jours' : 'Le ' + WEEKDAYS[t.weekday]}</span></span>
          <button class="btn danger" type="button" data-act="taskRemove" data-id="${t.id}">Retirer</button></div>`).join('') || '<p class="muted">Aucune tâche.</p>'}
      </div>
      <h2>Nouvelle tâche</h2>
      <form class="stack" data-form="task">
        <div class="field"><label for="tlabel">Quoi ?</label><input id="tlabel" name="label" maxlength="80" required placeholder="Ex. Changer la paille"></div>
        <div class="field"><span class="lab">Quand ?</span>
          <div class="seg">
            <button type="button" data-act="taskFreq" data-v="daily" aria-pressed="${newTaskFreq === 'daily'}">Tous les jours</button>
            <button type="button" data-act="taskFreq" data-v="weekly" aria-pressed="${newTaskFreq === 'weekly'}">Une fois par semaine</button>
          </div></div>
        ${newTaskFreq === 'weekly' ? `<div class="field"><label for="twd">Quel jour ?</label><select id="twd" name="weekday">
          ${WEEKDAYS.slice(1).map((d, i) => `<option value="${i + 1}" ${i + 1 === 6 ? 'selected' : ''}>${d}</option>`).join('')}</select></div>` : ''}
        <button class="btn primary block" type="submit">${I.plus} Ajouter</button>
      </form>`;
  }

  // ---------- poules
  function viewHens() {
    const hens = S.hens || [];
    return `
      ${back('#/', 'Accueil')}
      <div class="row between"><h1>Mes poules</h1><b style="font-size:1.15rem">${plural(hens.length, 'poule', 'poules')}</b></div>
      <a class="btn primary" href="#/poule/nouvelle">${I.plus} Ajouter une poule</a>
      <div class="grid2">
        <a class="btn" href="#/sante">${I.heart} Carnet santé${S.health?.due.length ? ` (${S.health.due.length})` : ''}</a>
        <a class="btn" href="#/race">Quelle race ?</a>
      </div>
      <div class="grid2">
        ${hens.map((x) => `<a class="hen-card" href="#/poule/${x.id}">${henAvatar(x, 'xl')}<span class="name">${h(x.name)}</span>
          ${x.breed ? `<span class="muted">${h(x.breed)}</span>` : ''}
          <span class="pill ${x.status === 'watch' ? 'watch' : ''}">${x.status === 'watch' ? 'À surveiller' : 'En forme'}</span></a>`).join('')}
      </div>
      ${hens.length ? '' : '<p class="muted">Ajoutez vos poules une par une : un nom suffit, le reste est facultatif.</p>'}`;
  }

  let notesFor = null;
  let notes = [];
  let photos = [];
  let photoDate = '';
  async function loadNotes(id) {
    notesFor = id; notes = []; photos = [];
    try {
      const [n, p] = await Promise.all([post('hen_notes', { id }), post('hen_photos', { id })]);
      notes = n.notes; photos = p.photos;
    } catch { /* hors ligne */ }
    if (route().name === 'poule') render();
  }

  /** "3 mois", "1 an et 2 mois": the age difference between two photos. */
  function span(fromTs, toTs) {
    const days = Math.round((toTs - fromTs) / 86400);
    if (days < 14) return plural(Math.max(days, 0), 'jour', 'jours');
    if (days < 60) return plural(Math.round(days / 7), 'semaine', 'semaines');
    const months = Math.round(days / 30.44);
    if (months < 12) return `${months} mois`;
    const y = Math.floor(months / 12), m = months % 12;
    return plural(y, 'an', 'ans') + (m ? ` et ${m} mois` : '');
  }
  const fmtDate = (ts) => new Intl.DateTimeFormat('fr-FR', { timeZone: tz(), day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(ts * 1000));

  function albumSection(hen) {
    const first = photos[0];
    const last = photos[photos.length - 1];
    const today = new Date().toISOString().slice(0, 10);
    return `
      <h2>Album photo</h2>
      ${photos.length >= 2 ? `
      <div class="card stack">
        <b>Elle a bien changé en ${span(first.at, last.at)} !</b>
        <div class="grid2">
          <figure class="photo-fig"><img src="${photoUrl(first.id)}" alt="${h(hen.name)} le ${h(fmtDate(first.at))}"><figcaption>Avant · ${h(fmtDate(first.at))}</figcaption></figure>
          <figure class="photo-fig"><img src="${photoUrl(last.id)}" alt="${h(hen.name)} le ${h(fmtDate(last.at))}"><figcaption>Maintenant · ${h(fmtDate(last.at))}</figcaption></figure>
        </div>
      </div>` : ''}
      ${photos.length ? `
      <div class="album" role="list">
        ${photos.map((p) => `<figure class="photo-fig" role="listitem">
          <a href="${photoUrl(p.id)}" target="_blank" rel="noopener"><img src="${photoUrl(p.id)}" alt="${h(hen.name)} le ${h(fmtDate(p.at))}" loading="lazy"></a>
          <figcaption>${h(fmtDate(p.at))}${first && p.id !== first.id ? `<br><span class="muted">+ ${span(first.at, p.at)}</span>` : ''}</figcaption>
          <button class="btn" type="button" data-act="photoRemove" data-id="${hen.id}" data-photo-id="${p.id}" style="min-height:2.8rem;padding:.2rem .6rem">Suppr.</button>
        </figure>`).join('')}
      </div>` : '<p class="muted" style="margin:0">Prenez-la en photo de temps en temps (par exemple chaque mois) pour voir comment elle grandit.</p>'}
      ${!S.household?.premium && photos.length >= S.household.freePhotos ? `
      <div class="card stack"><b>L'album gratuit garde ${S.household.freePhotos} photos par poule.</b>
        <a class="btn primary" href="#/premium">Album illimité avec Premium</a></div>` : `
      <div class="card stack">
        <div class="field"><label for="pdate">Date de la photo</label>
          <input id="pdate" type="date" max="${today}" value="${h(photoDate)}" data-photo-date>
          <span class="muted">Laissez vide pour la date d'aujourd'hui (ou celle de l'appareil photo).</span></div>
        <label class="btn primary">${I.camera} Ajouter une photo<input class="sr" type="file" accept="image/*" data-photo="${hen.id}"></label>
      </div>`}`;
  }

  function henHealth(hen) {
    const hs = S.health || { due: [], history: [] };
    const due = hs.due.filter((d) => d.henId === hen.id || d.henId === null);
    const hist = hs.history.filter((x) => x.henId === hen.id).slice(0, 3);
    return `
      <h2>Santé</h2>
      ${due.map((d) => `<div class="card ${d.overdue ? 'alert-card' : ''}"><b>${h(HEALTH[d.kind]?.label || d.kind)}${d.henId ? '' : ' (tout le poulailler)'}</b> · ${dueText(d)}</div>`).join('')}
      ${hist.map((x) => `<div class="kv"><span>${h(HEALTH[x.kind]?.label || x.kind)}</span><span class="muted">${h(fmtLongDay(x.day))}</span></div>`).join('')}
      <a class="btn" href="#/sante/${hen.id}">${I.heart} Noter un soin</a>`;
  }

  function viewHen(r) {
    if (r.id === 'nouvelle') return viewHenForm(null);
    const hen = (S.hens || []).find((x) => String(x.id) === r.id);
    if (!hen) return `${back('#/poules', 'Mes poules')}<p>Cette poule n'est plus dans la liste.</p>`;
    if (r.sub === 'modifier') return viewHenForm(hen);
    if (notesFor !== hen.id) loadNotes(hen.id);
    const rows = [['Arrivée', hen.arrived], ['Signe distinctif', hen.mark], ['Couleur des œufs', hen.eggColor]].filter(([, v]) => v);
    return `
      ${back('#/poules', 'Mes poules')}
      ${err()}
      <div class="row">
        ${henAvatar(hen, 'xl')}
        <div class="stack" style="gap:.3rem"><h1>${h(hen.name)}</h1>${hen.breed ? `<span class="muted">${h(hen.breed)}</span>` : ''}
          <span><span class="pill ${hen.status === 'watch' ? 'watch' : ''}">${hen.status === 'watch' ? 'À surveiller' : 'En forme'}</span></span></div>
      </div>
      ${rows.length ? `<div class="card list">${rows.map(([k, v]) => `<div class="kv"><span class="muted">${k}</span><b>${h(v)}</b></div>`).join('')}</div>` : ''}
      <button class="btn big" type="button" data-act="henStatus" data-id="${hen.id}" data-v="${hen.status === 'watch' ? 'ok' : 'watch'}">${hen.status === 'watch' ? 'Elle va mieux' : 'Elle ne va pas bien'}</button>
      <h2>Carnet</h2>
      <form class="stack" data-form="note" data-id="${hen.id}">
        <div class="field"><label class="sr" for="note">Nouvelle note</label><textarea id="note" name="text" maxlength="1000" placeholder="Ce que vous avez remarqué…"></textarea></div>
        <button class="btn primary" type="submit">Ajouter la note</button>
      </form>
      <div class="stack">
        ${notes.map((n) => `<div class="card"><div class="row between"><b>${h(n.who || '?')} · ${h(fmtWhen(n.at))}</b>
          <button class="btn" type="button" data-act="noteRemove" data-id="${hen.id}" data-note="${n.id}" aria-label="Supprimer cette note" style="min-height:2.8rem;padding:.2rem .8rem">Suppr.</button></div>
          <p style="margin:.4rem 0 0;white-space:pre-line">${h(n.text)}</p></div>`).join('')}
      </div>
      ${albumSection(hen)}
      ${henHealth(hen)}
      ${hen.breed ? '' : `<a class="btn" href="#/race/${hen.id}">Trouver sa race</a>`}
      <a class="btn" href="#/poule/${hen.id}/modifier">Modifier la fiche</a>
      <button class="btn danger" type="button" data-act="henRemove" data-id="${hen.id}">Retirer du poulailler</button>`;
  }

  function viewHenForm(hen) {
    const v = hen || { name: '', breed: '', arrived: '', mark: '', eggColor: '' };
    const field = (name, label, ph, max = 60) => `<div class="field"><label for="f-${name}">${label}</label><input id="f-${name}" name="${name}" value="${h(v[name])}" maxlength="${max}" placeholder="${ph}"></div>`;
    return `
      ${back(hen ? `#/poule/${hen.id}` : '#/poules', hen ? hen.name : 'Mes poules')}
      <h1>${hen ? 'Modifier la fiche' : 'Nouvelle poule'}</h1>
      ${err()}
      <form class="stack" data-form="hen" data-id="${hen ? hen.id : ''}">
        <div class="field"><label for="f-name">Son nom</label><input id="f-name" name="name" value="${h(v.name)}" maxlength="40" required></div>
        ${field('breed', 'Race ou couleur', 'Ex. Rousse')}
        ${hen ? `<a href="#/race/${hen.id}" style="margin-top:-.5rem">Je ne sais pas : m'aider à trouver sa race</a>` : ''}
        ${field('arrived', 'Arrivée', 'Ex. mars 2025', 30)}
        ${field('mark', 'Signe distinctif', 'Ex. bague bleue', 80)}
        ${field('eggColor', 'Couleur des œufs', 'Ex. brun clair', 30)}
        <button class="btn primary big block" type="submit">Enregistrer</button>
      </form>`;
  }

  async function uploadPhoto(id, file) {
    const form = new FormData();
    form.append('photo', file);
    if (photoDate) form.append('date', photoDate);
    toast('Envoi de la photo…');
    try {
      const data = await post('hen_photo', { id, form }, true);
      setState(data.state);
      photos = data.photos;
      photoDate = '';
      toast('Photo ajoutée à l\'album');
      render();
    } catch (e) {
      showError(e.status === 0 ? 'Pas de connexion internet.' : e.message);
    }
  }

  // ---------- réglages
  function viewSettings() {
    const s = S.settings || {};
    const hh = S.household || {};
    const size = String(store.get('kk-size', 1));
    return `
      ${back('#/', 'Accueil')}
      <h1>Réglages</h1>
      ${err()}
      <div class="card stack">
        <h2 style="margin:0">Inviter la famille</h2>
        <p style="margin:0">Sur le téléphone d'un proche : ouvrir KotKot, « Rejoindre ma famille », puis taper ce code.</p>
        <div class="invite" aria-label="Code d'invitation">${h(fmtCode(hh.inviteCode))}</div>
        <button class="btn primary" type="button" data-act="shareInvite">${I.share} Envoyer l'invitation</button>
        <button class="btn" type="button" data-act="regenInvite">Changer le code</button>
        <p class="muted" style="margin:0">Changer le code empêche de nouveaux téléphones d'entrer avec l'ancien. Les téléphones déjà connectés le restent.</p>
      </div>
      <div class="card stack">
        <h2 style="margin:0">Taille du texte</h2>
        <div class="seg">${[['1', 'Normal'], ['2', 'Grand'], ['3', 'Très grand']].map(([v, l]) => `<button type="button" data-act="size" data-v="${v}" aria-pressed="${size === v}">${l}</button>`).join('')}</div>
        <p class="muted" style="margin:0">Seulement sur ce téléphone.</p>
      </div>
      <div class="card stack">
        <h2 style="margin:0">Rappels sur ce téléphone</h2>
        <p style="margin:0">Pour la porte restée ouverte le soir et les soins à faire, même quand KotKot est fermé.</p>
        <div class="stack" data-push-status><p class="muted" style="margin:0">Vérification…</p></div>
        <span class="lab">Rappel pour la porte, après le coucher du soleil :</span>
        <div class="seg">${[15, 30, 60].map((m) => `<button type="button" data-act="delay" data-v="${m}" aria-pressed="${s.reminderDelay === m}">${m === 60 ? '1 h' : m + ' min'}</button>`).join('')}</div>
      </div>
      <form class="card stack" data-form="location">
        <h2 style="margin:0">Le poulailler</h2>
        <div class="field"><label for="hn">Nom</label><input id="hn" name="householdName" value="${h(hh.name || '')}" maxlength="60" required></div>
        ${locationFields(s)}
        <button class="btn primary" type="submit">Enregistrer</button>
      </form>
      <div class="card stack">
        <h2 style="margin:0">Personnes du foyer</h2>
        <div class="list">${S.people.map((p) => `<div class="row">${avatar(p)}<b class="grow">${h(p.name)}</b>
          ${S.people.length > 1 ? `<button class="btn danger" type="button" data-act="personRemove" data-id="${p.id}">Retirer</button>` : ''}</div>`).join('')}</div>
        <form class="row" data-form="person"><label class="sr" for="pname">Prénom</label>
          <input id="pname" name="name" maxlength="40" required placeholder="Prénom" class="grow" style="min-height:3.4rem;padding:.6rem .9rem;border-radius:.9rem;border:2px solid var(--ink-soft)">
          <button class="btn primary" type="submit">Ajouter</button></form>
      </div>
      <form class="card stack" data-form="pin">
        <h2 style="margin:0">Code de la famille</h2>
        <div class="grid2">
          <div class="field"><label for="op">Code actuel</label><input id="op" name="oldPin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autocomplete="off"></div>
          <div class="field"><label for="np">Nouveau code</label><input id="np" name="newPin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autocomplete="off"></div>
        </div>
        <button class="btn" type="submit">Changer le code</button>
      </form>
      ${responsibleSection()}
      <a class="btn" href="#/taches/gerer">${I.tasks} Gérer les tâches</a>
      <button class="btn danger" type="button" data-act="logout">Déconnecter ce téléphone</button>
      <p class="muted" style="text-align:center">KotKot ${h(S.version || '')}</p>`;
  }

  /** Account area: link to it, an invitation to create one, or "become responsible" for a V1 household. */
  function responsibleSection() {
    const hh = S.household || {};
    if (S.account) {
      return `<a class="btn" href="#/compte">${I.user} ${hh.isOwner ? 'Mon compte et mon abonnement' : 'Mon compte'}</a>`;
    }
    if (!hh.hasOwner) {
      return `
        <form class="card stack" data-form="claim">
          <h2 style="margin:0">Devenir responsable du poulailler</h2>
          <p style="margin:0">Un e-mail et un mot de passe pour retrouver le poulailler sur un nouveau téléphone, exporter les données ou gérer l'abonnement.</p>
          <div class="field"><label for="cemail">E-mail</label><input id="cemail" name="email" type="email" autocomplete="email" required></div>
          <div class="field"><label for="cpw">Mot de passe (8 caractères ou plus)</label><input id="cpw" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
          <div class="field"><label for="cpin">Code de la famille</label><input id="cpin" name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autocomplete="off"></div>
          <button class="btn primary" type="submit">Créer mon accès responsable</button>
        </form>`;
    }
    return `
      <div class="card stack">
        <h2 style="margin:0">Retrouver le poulailler partout</h2>
        <p style="margin:0">Avec votre propre compte, vous ouvrez « ${h(hh.name || '')} » sur une tablette ou un nouveau téléphone sans aucun code.</p>
        ${socialButtons()}
        <a class="btn" href="#/inscription">${I.user} Créer mon compte avec un e-mail</a>
        <a class="btn ghost" href="#/connexion">J'ai déjà un compte</a>
      </div>`;
  }

  const fmtCode = (c) => (c ? `${c.slice(0, 4)}-${c.slice(4)}` : '—');
  const inviteUrl = () => new URL(`#/rejoindre/${S.household.inviteCode}`, location.href.split('#')[0]).href;

  /** Deleting asks for the password, or the e-mail for an account opened with Google/Apple. */
  const confirmField = (id) => (S.account.hasPassword
    ? `<div class="field"><label for="${id}">Votre mot de passe pour confirmer</label><input id="${id}" name="password" type="password" autocomplete="current-password" required></div>`
    : `<div class="field"><label for="${id}">Tapez votre e-mail pour confirmer</label><input id="${id}" name="password" type="email" autocomplete="off" required></div>`);

  // ---------- mon compte
  function viewAccount() {
    if (!S.account) return viewAccountLogin();
    const hh = S.household || {};
    return `
      ${back('#/reglages', 'Réglages')}
      <h1>Mon compte</h1>
      <p class="muted" style="margin:0">${h(S.account.email)}</p>
      ${err()}
      ${hh.isOwner ? premiumCard() : ''}
      <div class="card stack">
        <h2 style="margin:0">Mes poulaillers</h2>
        <div class="list">${S.account.households.map((x) => `<div class="stack" style="gap:.5rem">
          <b>${h(x.name)}${x.id === hh.id ? ' <span class="pill">ce téléphone</span>' : ''}</b>
          <span class="muted">${x.role === 'owner' ? 'Vous en êtes responsable' : 'Membre de la famille'}</span>
          <div class="grid2">
            ${x.id === hh.id ? '' : `<button class="btn" type="button" data-act="enterHousehold" data-id="${x.id}">Ouvrir ici</button>`}
            ${x.role === 'owner' ? `<a class="btn" href="${API}?a=export&id=${x.id}" download>Exporter</a>`
              : `<button class="btn" type="button" data-act="leaveHousehold" data-id="${x.id}">Ne plus suivre</button>`}
          </div>
          ${x.role !== 'owner' ? '' : `<details><summary class="muted" style="cursor:pointer;min-height:2.8rem;padding:.6rem 0">Supprimer ce poulailler…</summary>
            <form class="stack" data-form="householdDelete" data-id="${x.id}">
              <p style="margin:0">Tout sera effacé pour toute la famille : œufs, poules, photos, carnet. <b>C'est définitif.</b> Pensez à exporter avant.</p>
              ${confirmField(`dpw${x.id}`)}
              <button class="btn danger" type="submit">Supprimer « ${h(x.name)} »</button>
            </form></details>`}
        </div>`).join('')}</div>
        <a class="btn" href="#/rejoindre">${I.door} Rejoindre un autre poulailler</a>
        ${S.registration || S.account.households.some((x) => x.role === 'owner') ? `<a class="btn" href="#/foyer/nouveau">${I.plus} Créer un poulailler</a>` : ''}
      </div>
      ${hh.isOwner ? `<form class="card stack" data-form="pinReset">
        <h2 style="margin:0">Code de la famille oublié ?</h2>
        <div class="field"><label for="rp">Nouveau code (4 chiffres)</label><input id="rp" name="newPin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autocomplete="off"></div>
        <button class="btn" type="submit">Enregistrer le nouveau code</button>
      </form>` : ''}
      <form class="card stack" data-form="passwordChange">
        <h2 style="margin:0">${S.account.hasPassword ? 'Mot de passe' : 'Ajouter un mot de passe'}</h2>
        ${S.account.hasPassword ? '<div class="field"><label for="opw">Mot de passe actuel</label><input id="opw" name="oldPassword" type="password" autocomplete="current-password" required></div>'
          : '<p class="muted" style="margin:0">Vous vous connectez avec Google ou Apple. Un mot de passe permet aussi de vous connecter avec votre e-mail.</p>'}
        <div class="field"><label for="npw">Nouveau mot de passe</label><input id="npw" name="newPassword" type="password" autocomplete="new-password" minlength="8" required></div>
        <button class="btn" type="submit">Changer le mot de passe</button>
      </form>
      <button class="btn" type="button" data-act="accountLogout">Se déconnecter du compte</button>
      <details class="card"><summary style="cursor:pointer;font-weight:700;min-height:2.8rem">Supprimer mon compte…</summary>
        <form class="stack" data-form="accountDelete" style="margin-top:.6rem">
          <p style="margin:0">Votre compte sera effacé, ainsi que <b>les poulaillers dont vous êtes responsable</b>. C'est définitif.</p>
          ${confirmField('apw')}
          <button class="btn danger" type="submit">Supprimer mon compte</button>
        </form></details>`;
  }

  // ---------- Premium
  const PREMIUM_FEATURES = [
    'Rappels sur le téléphone, même appli fermée (porte, soins)',
    'Album photo illimité pour suivre la croissance',
    'Toutes les nouveautés à venir (statistiques, mode vacances…)',
  ];

  function premiumCard() {
    const hh = S.household || {};
    if (!S.billing) return '<div class="card"><b>Tout est inclus</b><p class="muted" style="margin:.3rem 0 0">Ce site n\'a pas d\'abonnement : toutes les fonctions sont disponibles.</p></div>';
    const until = hh.premiumUntil ? new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(hh.premiumUntil * 1000)) : '';
    if (hh.premium) {
      return `<div class="card stack"><b>${hh.trial ? 'Essai Premium' : 'Premium'} jusqu'au ${h(until)}</b>
        <a class="btn" href="#/premium">Voir Premium</a></div>`;
    }
    return `<div class="card stack"><b>Version gratuite</b><a class="btn primary" href="#/premium">Découvrir Premium</a></div>`;
  }

  function viewPremium() {
    return `
      ${back('#/reglages', 'Réglages')}
      <h1>KotKot Premium</h1>
      ${premiumCard()}
      <div class="card list">${PREMIUM_FEATURES.map((f) => `<div class="row">${I.check}<span>${h(f)}</span></div>`).join('')}</div>
      <p class="muted">Toujours gratuit : œufs, tâches, porte, poules sans limite, carnet santé, aide à la race.</p>
      ${S.billing && !S.household?.premium ? '<div class="card"><b>Le paiement en ligne arrive très bientôt.</b><p class="muted" style="margin:.3rem 0 0">Vous serez prévenu par e-mail.</p></div>' : ''}`;
  }

  // ---------- carnet santé
  const HEALTH = {
    vermifuge: { label: 'Vermifuge', next: 180 },
    parasites: { label: 'Anti-poux / acariens', next: 30 },
    vaccin: { label: 'Vaccin', next: 0 },
    soin: { label: 'Soin / blessure', next: 0 },
    veto: { label: 'Vétérinaire', next: 0 },
    autre: { label: 'Autre', next: 0 },
  };
  const NEXT_CHOICES = [[0, 'Pas de rappel'], [30, 'Dans 1 mois'], [90, 'Dans 3 mois'], [180, 'Dans 6 mois']];
  const addDays = (iso, days) => { const d = new Date(iso + 'T12:00:00Z'); d.setUTCDate(d.getUTCDate() + days); return d.toISOString().slice(0, 10); };
  const daysBetween = (a, b) => Math.round((new Date(b + 'T12:00:00Z') - new Date(a + 'T12:00:00Z')) / 86400000);
  const fmtLongDay = (iso) => new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(iso + 'T12:00:00Z'));

  function dueText(d) {
    const late = daysBetween(d.due, S.today);
    if (late > 0) return `en retard de ${plural(late, 'jour', 'jours')}`;
    if (late === 0) return "c'est aujourd'hui";
    return `dans ${plural(-late, 'jour', 'jours')}`;
  }

  let healthKind = 'vermifuge';
  let healthNext = 180;

  function viewHealth(r) {
    const hens = S.hens || [];
    const preHen = r.id ? Number(r.id) : 0;
    const hs = S.health || { due: [], history: [] };
    const today = S.today;
    return `
      ${back(preHen ? `#/poule/${preHen}` : '#/poules', preHen ? 'La poule' : 'Mes poules')}
      <h1>Carnet santé</h1>
      ${err()}
      ${hs.due.length ? `<h2>À faire</h2><div class="stack">${hs.due.map((d) => `
        <div class="card stack ${d.overdue ? 'alert-card' : ''}">
          <b>${h(HEALTH[d.kind]?.label || d.kind)} · ${h(d.hen || 'tout le poulailler')}</b>
          <span>${dueText(d)} (dernier : ${h(fmtLongDay(d.lastDay))})</span>
          <button class="btn primary" type="button" data-act="healthDone" data-kind="${h(d.kind)}" data-hen="${d.henId || ''}" data-every="${daysBetween(d.lastDay, d.due)}">${I.check} C'est fait aujourd'hui</button>
        </div>`).join('')}</div>` : '<div class="card"><b>Rien à faire pour l\'instant.</b></div>'}
      <h2>Noter un soin</h2>
      <form class="stack card" data-form="health">
        <div class="field"><label for="hhen">Pour qui ?</label>
          <select id="hhen" name="henId"><option value="">Tout le poulailler</option>
          ${hens.map((x) => `<option value="${x.id}" ${x.id === preHen ? 'selected' : ''}>${h(x.name)}</option>`).join('')}</select></div>
        <div class="field"><span class="lab">Quoi ?</span>
          <div class="seg wrap">${Object.entries(HEALTH).map(([k, v]) => `<button type="button" data-act="healthKind" data-v="${k}" aria-pressed="${healthKind === k}">${v.label}</button>`).join('')}</div></div>
        <div class="field"><label for="hday">Quand ?</label><input id="hday" name="day" type="date" value="${today}" max="${today}"></div>
        <div class="field"><span class="lab">Prochain rappel</span>
          <div class="seg wrap">${NEXT_CHOICES.map(([d, l]) => `<button type="button" data-act="healthNext" data-v="${d}" aria-pressed="${healthNext === d}">${l}</button>`).join('')}</div></div>
        <div class="field"><label for="hnote">Note (produit, dose…)</label><input id="hnote" name="note" maxlength="500"></div>
        <button class="btn primary big" type="submit">Enregistrer</button>
      </form>
      <h2>Historique</h2>
      <div class="card list">${hs.history.filter((x) => !preHen || x.henId === preHen || x.henId === null).map((x) => `
        <div class="stack" style="gap:.2rem"><div class="row between"><b>${h(HEALTH[x.kind]?.label || x.kind)} · ${h(x.hen || 'tout le poulailler')}</b>
          <button class="btn" type="button" data-act="healthRemove" data-id="${x.id}" style="min-height:2.8rem;padding:.2rem .8rem" aria-label="Supprimer">Suppr.</button></div>
          <span class="muted">${h(fmtLongDay(x.day))}${x.who ? ` · ${h(x.who)}` : ''}${x.nextDue ? ` · prochain le ${h(fmtLongDay(x.nextDue))}` : ''}</span>
          ${x.note ? `<span>${h(x.note)}</span>` : ''}</div>`).join('') || '<p class="muted" style="margin:0">Rien de noté pour l\'instant.</p>'}</div>`;
  }

  // ---------- aide à la race
  let quiz = { step: 0, answers: {}, henId: null };

  function viewBreed(r) {
    const henId = r.id ? Number(r.id) : null;
    if (quiz.henId !== henId) quiz = { step: 0, answers: {}, henId };
    const hen = henId ? (S.hens || []).find((x) => x.id === henId) : null;
    const backLink = hen ? back(`#/poule/${hen.id}`, hen.name) : back('#/poules', 'Mes poules');
    const Q = window.KOTKOT_BREEDS.questions;
    if (quiz.step < Q.length) {
      const q = Q[quiz.step];
      return `
        ${backLink}
        <h1>Quelle race ?</h1>
        <p class="muted" style="margin:0">Question ${quiz.step + 1} sur ${Q.length}${hen ? ` · ${h(hen.name)}` : ''}</p>
        <h2 style="font-size:1.45rem">${h(q.text)}</h2>
        <div class="stack">${q.options.map(([v, label, hint]) => `<button class="btn big quiz-opt" type="button" data-act="quizAnswer" data-v="${v}">
          ${q.swatches ? `<span class="swatch" style="background:${q.swatches[v]}" aria-hidden="true"></span>` : ''}
          <span style="text-align:left;flex:1">${h(label)}${hint ? `<span class="muted" style="display:block;font-size:.95rem;font-weight:400">${h(hint)}</span>` : ''}</span></button>`).join('')}
          <button class="btn ghost" type="button" data-act="quizAnswer" data-v="">Je ne sais pas</button>
          ${quiz.step ? '<button class="btn" type="button" data-act="quizBack">← Question précédente</button>' : ''}
        </div>`;
    }
    const results = window.KOTKOT_BREEDS.match(quiz.answers).slice(0, 3);
    return `
      ${backLink}
      <h1>Elle ressemble à…</h1>
      <p class="muted" style="margin:0">Les poules de jardin sont souvent des croisements : c'est une piste, pas une certitude.</p>
      <div class="stack">${results.map((b, i) => `
        <div class="card stack">
          <div class="row between"><b style="font-size:1.3rem">${i + 1}. ${h(b.name)}</b><span class="pill ${b.level === 'Peu probable' ? 'watch' : ''}">${h(b.level)}</span></div>
          <span>${h(b.desc)}</span>
          ${hen ? `<button class="btn primary" type="button" data-act="quizPick" data-name="${h(b.name)}">C'est elle !</button>` : ''}
        </div>`).join('')}</div>
      <button class="btn" type="button" data-act="quizRestart">Recommencer</button>`;
  }

  // ---------- rappels push sur ce téléphone
  const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  const isIos = () => /iPad|iPhone|iPod/.test(navigator.userAgent);
  const b64uToBytes = (s) => { const b = atob(s.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((s.length + 3) % 4)); return Uint8Array.from(b, (c) => c.charCodeAt(0)); };

  async function currentPushSub() {
    if (!pushSupported()) return null;
    const reg = await navigator.serviceWorker.ready;
    return reg.pushManager.getSubscription();
  }

  async function refreshPushStatus() {
    const box = app.querySelector('[data-push-status]');
    if (!box) return;
    let html;
    if (!S.household?.premium) {
      html = `<p style="margin:0">Les rappels font partie de Premium.</p><a class="btn primary" href="#/premium">Découvrir Premium</a>`;
    } else if (!pushSupported()) {
      html = isIos()
        ? '<p style="margin:0">Sur iPhone : ajoutez d\'abord KotKot à l\'écran d\'accueil (bouton Partager › « Sur l\'écran d\'accueil »), puis ouvrez-le depuis l\'icône.</p>'
        : '<p style="margin:0">Ce navigateur ne sait pas recevoir de rappels. Essayez avec Chrome, Firefox ou Safari à jour.</p>';
    } else if (Notification.permission === 'denied') {
      html = '<p style="margin:0">Les notifications sont bloquées pour KotKot. Autorisez-les dans les réglages du téléphone (Notifications), puis revenez ici.</p>';
    } else {
      const sub = await currentPushSub().catch(() => null);
      html = sub
        ? `<p class="hint" style="margin:0">${I.check}<span><b>Activés sur ce téléphone.</b></span></p>
           <div class="grid2"><button class="btn" type="button" data-act="pushTest">Essayer</button><button class="btn" type="button" data-act="pushOff">Désactiver</button></div>`
        : `<button class="btn primary" type="button" data-act="pushOn">${I.bell} Activer les rappels</button>`;
    }
    if (box.isConnected) box.innerHTML = html;
  }

  async function pushOn() {
    if (await Notification.requestPermission() !== 'granted') return showError('Sans votre accord, le téléphone ne peut pas afficher les rappels.');
    try {
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64uToBytes(S.push.publicKey) });
      const j = sub.toJSON();
      await call('push_subscribe', { endpoint: j.endpoint, keys: j.keys });
      toast('Rappels activés');
    } catch (e) {
      if (!errorText) showError('Les rappels n\'ont pas pu être activés sur ce téléphone.');
    }
    render();
  }

  async function pushOff() {
    const sub = await currentPushSub();
    if (sub) {
      await call('push_unsubscribe', { endpoint: sub.endpoint }).catch(() => {});
      await sub.unsubscribe();
    }
    toast('Rappels désactivés');
    render();
  }

  function applySize() { document.documentElement.dataset.size = String(store.get('kk-size', 1)); }
  // ------------------------------------------------------------------ clics
  const actions = {
    toastUndo() { const u = toastUndo; hideToast(); if (u) { u(); render(); } },
    egg: (el) => eggChange(Number(el.dataset.delta)),
    door: (el) => doorChange(el.dataset.action),
    task: (el) => taskToggle(Number(el.dataset.id)),
    taskFreq(el) { newTaskFreq = el.dataset.v; const label = app.querySelector('#tlabel')?.value; render(); if (label) app.querySelector('#tlabel').value = label; },
    async taskRemove(el) {
      const id = Number(el.dataset.id);
      await call('task_remove', { id });
      toast('Tâche retirée', () => call('task_remove', { id, restore: true }).then(render));
      render();
    },
    loginWho(el) { loginWho = Number(el.dataset.id); app.querySelectorAll('.person').forEach((b) => b.setAttribute('aria-pressed', String(b === el))); },
    pinKey(el) {
      if (pinBuffer.length >= 4) return;
      pinBuffer += el.dataset.key;
      if (errorText) { errorText = ''; app.querySelector('.error')?.remove(); }
      updateDots();
      if (pinBuffer.length === 4) submitPin();
    },
    pinClear() { pinBuffer = ''; updateDots(); },
    pinBack() { pinBuffer = pinBuffer.slice(0, -1); updateDots(); },
    async switchPerson(el) { await call('switch_person', { personId: Number(el.dataset.id) }); go('#/'); },
    async henStatus(el) { await call('hen_status', { id: Number(el.dataset.id), status: el.dataset.v }); render(); },
    async henRemove(el) {
      const id = Number(el.dataset.id);
      const hen = S.hens.find((x) => x.id === id);
      await call('hen_remove', { id });
      go('#/poules');
      toast(`${hen?.name || 'La poule'} a été retirée`, () => call('hen_remove', { id, restore: true }).then(render));
    },
    async photoRemove(el) {
      if (!confirm('Supprimer cette photo pour de bon ?')) return;
      const data = await call('hen_photo_remove', { id: Number(el.dataset.id), photoId: Number(el.dataset.photoId) });
      photos = data.photos; render();
    },
    async noteRemove(el) {
      if (!confirm('Supprimer cette note pour de bon ?')) return;
      const data = await call('hen_note_remove', { id: Number(el.dataset.id), noteId: Number(el.dataset.note) });
      notes = data.notes; render();
    },
    size(el) { store.set('kk-size', Number(el.dataset.v)); applySize(); render(); },
    async delay(el) { await call('settings_save', { reminderDelay: Number(el.dataset.v) }); toast('Rappel enregistré'); render(); },
    async personRemove(el) {
      const id = Number(el.dataset.id);
      await call('person_remove', { id });
      toast('Personne retirée', () => call('person_remove', { id, restore: true }).then(render));
      render();
    },
    async logout() {
      hideToast();
      try { await post('logout', {}); } catch { /* tant pis */ }
      setState({ ...S, auth: false });
      go('#/');
    },
    async accountLogout() { await call('account_logout', {}); toast('Déconnecté du compte'); go(S.auth ? '#/reglages' : '#/'); },
    calMove(el) { calMonth = shiftMonth(calMonth || monthOf(S.today), Number(el.dataset.v)); render(); },
    eggDay(el) {
      const c = monthCache[monthOf(el.dataset.day)];
      eggSetDay(el.dataset.day, (c?.days[el.dataset.day] || 0) + Number(el.dataset.delta));
      render();
    },
    async leaveHousehold(el) {
      if (!confirm('Ne plus suivre ce poulailler avec votre compte ? Il continue d\'exister pour la famille.')) return;
      await call('membership_leave', { householdId: Number(el.dataset.id) }); toast('C\'est fait'); render();
    },
    async enterHousehold(el) { await call('household_enter', { householdId: Number(el.dataset.id) }); notesFor = null; go('#/'); },
    async shareInvite() {
      const text = `Rejoins notre poulailler « ${S.household.name} » sur KotKot ! Code d'invitation : ${fmtCode(S.household.inviteCode)}`;
      if (navigator.share) {
        try { await navigator.share({ title: 'KotKot', text, url: inviteUrl() }); } catch { /* annulé */ }
        return;
      }
      try { await navigator.clipboard.writeText(`${text}\n${inviteUrl()}`); toast('Invitation copiée : collez-la dans un SMS ou un e-mail'); } catch { toast(fmtCode(S.household.inviteCode)); }
    },
    async regenInvite() {
      if (!confirm('Créer un nouveau code ? L\'ancien ne marchera plus pour les nouveaux téléphones.')) return;
      await call('invite_regenerate', {}); toast('Nouveau code créé'); render();
    },
    healthKind(el) {
      healthKind = el.dataset.v;
      healthNext = HEALTH[healthKind].next;
      keepForm(() => render());
    },
    healthNext(el) { healthNext = Number(el.dataset.v); keepForm(() => render()); },
    async healthDone(el) {
      const every = Number(el.dataset.every) || 0;
      await call('health_add', { kind: el.dataset.kind, henId: el.dataset.hen ? Number(el.dataset.hen) : null, day: S.today, nextDue: every > 0 ? addDays(S.today, every) : null });
      toast('Noté, merci !'); render();
    },
    async healthRemove(el) {
      if (!confirm('Supprimer cette ligne du carnet ?')) return;
      await call('health_remove', { id: Number(el.dataset.id) }); render();
    },
    quizAnswer(el) {
      const q = window.KOTKOT_BREEDS.questions[quiz.step];
      if (el.dataset.v) quiz.answers[q.id] = el.dataset.v; else delete quiz.answers[q.id];
      quiz.step++; render(); window.scrollTo(0, 0);
    },
    quizBack() { quiz.step = Math.max(0, quiz.step - 1); render(); },
    quizRestart() { quiz = { step: 0, answers: {}, henId: quiz.henId }; render(); },
    async quizPick(el) {
      const hen = S.hens.find((x) => x.id === quiz.henId);
      if (!hen) return;
      await call('hen_save', { id: hen.id, name: hen.name, breed: el.dataset.name, arrived: hen.arrived, mark: hen.mark, eggColor: hen.eggColor });
      toast(`${hen.name} : ${el.dataset.name}`);
      quiz = { step: 0, answers: {}, henId: null };
      go(`#/poule/${hen.id}`);
    },
    pushOn: () => pushOn(),
    pushOff: () => pushOff(),
    async pushTest() {
      const sub = await currentPushSub();
      if (sub) { await call('push_test', { endpoint: sub.endpoint }); toast('Rappel d\'essai envoyé'); }
    },
    locate(el) {
      if (!navigator.geolocation) return showError('Ce téléphone ne donne pas sa position. Tapez la latitude et la longitude.');
      el.disabled = true; el.textContent = 'Recherche…';
      navigator.geolocation.getCurrentPosition((pos) => {
        const form = el.closest('form') || app;
        form.querySelector('[name=lat]').value = pos.coords.latitude.toFixed(4);
        form.querySelector('[name=lon]').value = pos.coords.longitude.toFixed(4);
        el.disabled = false; el.innerHTML = `${I.pin} Position trouvée`;
      }, () => { el.disabled = false; el.innerHTML = `${I.pin} Utiliser ma position`; showError('Position introuvable. Vous pouvez la taper à la main.'); }, { timeout: 15000 });
    },
  };

  document.addEventListener('click', (ev) => {
    const el = ev.target.closest('[data-act]');
    if (!el || !actions[el.dataset.act]) return;
    ev.preventDefault();
    Promise.resolve(actions[el.dataset.act](el)).catch(() => { /* erreur déjà affichée */ }).finally(() => {
      if (['egg', 'door', 'task'].includes(el.dataset.act)) render();
    });
  });

  document.addEventListener('change', (ev) => {
    rememberField(ev.target);
    if (ev.target.matches('[data-photo-date]')) photoDate = ev.target.value;
    const input = ev.target.closest('[data-photo]');
    if (input?.files?.[0]) uploadPhoto(Number(input.dataset.photo), input.files[0]);
  });

  // ------------------------------------------------------------------ formulaires
  const forms = {
    eggDay(f, fd) {
      const n = Number(fd.get('count'));
      if (!Number.isFinite(n) || n < 0 || n > 500) return showError('Indiquez un nombre entre 0 et 500.');
      eggSetDay(f.dataset.day, n);
      f.reset();
      render();
    },
    async register(f, fd) {
      try {
        const data = await post('account_register', { email: fd.get('email'), password: fd.get('password') });
        setState(data.state); toast('Compte créé'); go(S.auth ? '#/compte' : '#/');
      } catch (e) { showError(e.status === 0 ? 'Pas de connexion internet.' : e.message); }
    },
    async signup(f, fd) {
      let body;
      try { body = signupPayload(fd); } catch (e) { return showError(e.message); }
      try {
        const data = await post('account_create', { email: fd.get('email'), password: fd.get('password'), ...body });
        errorText = ''; setState(data.state); go('#/'); toast('Bienvenue dans KotKot !');
      } catch (e) { showError(e.status === 0 ? 'Pas de connexion internet.' : e.message); }
    },
    async householdCreate(f, fd) {
      let body;
      try { body = signupPayload(fd); } catch (e) { return showError(e.message); }
      await call('household_create', body);
      errorText = ''; notesFor = null; go('#/'); toast('Nouveau poulailler créé');
    },
    async accountLogin(f, fd) {
      try {
        const data = await post('account_login', { email: fd.get('email'), password: fd.get('password') });
        errorText = ''; setState(data.state); go('#/');
      } catch (e) { showError(e.status === 0 ? 'Pas de connexion internet.' : e.message); }
    },
    async forgot(f, fd) {
      try {
        const data = await post('password_forgot', { email: fd.get('email') });
        forgotMessage = data.message; errorText = ''; render();
      } catch (e) { showError(e.status === 0 ? 'Pas de connexion internet.' : e.message); }
    },
    async reset(f, fd) {
      try {
        const data = await post('password_reset', { token: f.dataset.token, password: fd.get('password') });
        errorText = ''; setState(data.state); toast('Nouveau mot de passe enregistré'); go('#/');
      } catch (e) { showError(e.status === 0 ? 'Pas de connexion internet.' : e.message); }
    },
    async joinCode(f, fd) {
      const code = String(fd.get('code') || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
      if (code.length !== 8) return showError('Le code d\'invitation a 8 lettres et chiffres.');
      errorText = ''; joinInfo = null; go(`#/rejoindre/${code}`);
    },
    async claim(f, fd) {
      await call('household_claim', { email: fd.get('email'), password: fd.get('password'), pin: fd.get('pin') });
      errorText = ''; toast('Vous êtes maintenant responsable du poulailler'); go('#/compte');
    },
    async pinReset(f, fd) {
      await call('pin_reset', { newPin: fd.get('newPin') });
      errorText = ''; f.reset(); toast('Nouveau code enregistré. Prévenez la famille !');
    },
    async passwordChange(f, fd) {
      await call('password_change', { oldPassword: fd.get('oldPassword'), newPassword: fd.get('newPassword') });
      errorText = ''; f.reset(); toast('Mot de passe changé');
    },
    async householdDelete(f, fd) {
      const id = Number(f.dataset.id);
      await call('household_delete', { householdId: id, password: fd.get('password') });
      errorText = ''; toast('Poulailler supprimé'); go('#/');
    },
    async accountDelete(f, fd) {
      await call('account_delete', { password: fd.get('password') });
      errorText = ''; store.set('kk-join', null); toast('Compte supprimé'); go('#/');
    },
    async health(f, fd) {
      const day = fd.get('day') || S.today;
      await call('health_add', { kind: healthKind, henId: fd.get('henId') ? Number(fd.get('henId')) : null, day, nextDue: healthNext ? addDays(day, healthNext) : null, note: fd.get('note') });
      errorText = ''; toast('Soin noté'); render(); window.scrollTo(0, 0);
    },
    async task(f, fd) {
      await call('task_save', { label: fd.get('label'), frequency: newTaskFreq, weekday: Number(fd.get('weekday') || 6) });
      errorText = ''; toast('Tâche ajoutée'); render();
    },
    async hen(f, fd) {
      const id = Number(f.dataset.id || 0);
      const data = await call('hen_save', { id, name: fd.get('name'), breed: fd.get('breed'), arrived: fd.get('arrived'), mark: fd.get('mark'), eggColor: fd.get('eggColor') });
      errorText = ''; toast(id ? 'Fiche enregistrée' : 'Bienvenue à elle !'); go(`#/poule/${data.id}`);
    },
    async note(f, fd) {
      const text = String(fd.get('text') || '').trim();
      if (!text) return;
      const data = await call('hen_note_add', { id: Number(f.dataset.id), text });
      notes = data.notes; render();
    },
    async location(f, fd) {
      if (fd.get('householdName') && fd.get('householdName') !== S.household.name) await call('household_rename', { name: fd.get('householdName') });
      await call('settings_save', { city: fd.get('city'), lat: fd.get('lat'), lon: fd.get('lon') });
      errorText = ''; toast('Enregistré'); render();
    },
    async person(f, fd) { await call('person_save', { name: fd.get('name') }); errorText = ''; render(); },
    async pin(f, fd) {
      await call('pin_change', { oldPin: fd.get('oldPin'), newPin: fd.get('newPin') });
      errorText = ''; toast('Nouveau code enregistré. Prévenez la famille !'); render();
    },
  };

  document.addEventListener('submit', (ev) => {
    const f = ev.target.closest('[data-form]');
    if (!f || !forms[f.dataset.form]) return;
    ev.preventDefault();
    const data = new FormData(f);
    const draft = takeFormDraft(f);
    errorText = '';
    Promise.resolve(forms[f.dataset.form](f, data)).catch(() => { /* erreur déjà affichée */ }).finally(() => {
      if (errorText) { giveFormDraftBack(draft); render(); }
    });
  });

  // ------------------------------------------------------------------ démarrage
  applySize();
  window.addEventListener('online', () => { online = true; flush(); refresh(); });
  window.addEventListener('offline', () => setOnline(false));
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { flush(); refresh(); } });
  setInterval(() => { if (!document.hidden) refresh(); }, 60000);

  // Live sync: every few seconds, a tiny question « anything new? »; the full update only if so.
  setInterval(async () => {
    if (document.hidden || !S.auth || !online || outbox.length || flushing) return;
    try {
      const res = await fetch(`${API}?a=rev`, { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      if (data.ok && data.rev !== S.household?.rev) refresh();
      else if (res.status === 401) refresh();
    } catch { /* hors ligne : on réessaiera */ }
  }, 5000);
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
  render();
  refresh().then(flush);
})();
