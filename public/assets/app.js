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
    render();
    if (v) flush();
  }

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
    render();
    checkDoorReminder();
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
    render();
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
    if (!S.setup) html = viewSetup();
    else if (!S.auth) html = viewLogin();
    else {
      const views = { accueil: viewHome, oeufs: viewEggs, porte: viewDoor, taches: r.id === 'gerer' ? viewTasksManage : viewTasks, poules: viewHens, poule: viewHen, reglages: viewSettings, qui: viewWho };
      html = (views[r.name] || viewHome)(r);
    }
    app.innerHTML = html;
    afterRender();
  }

  function afterRender() {
    // PIN pad keeps its own state so focus is not lost between taps
    if (app.querySelector('.pad')) updateDots();
  }

  // ---------- première installation
  function viewSetup() {
    return `
      <div class="brand">${I.egg} KotKot</div>
      <h1>Bienvenue !</h1>
      <p class="muted">Quelques réglages, une seule fois, pour tout le foyer.</p>
      ${err()}
      <form class="stack" data-form="setup">
        <div class="field"><span class="lab">Qui s'occupe des poules ?</span>
          ${[1, 2, 3, 4].map((i) => `<input name="person" autocomplete="off" placeholder="Prénom ${i}" aria-label="Prénom ${i}" maxlength="40">`).join('')}
          <span class="muted">Vous pourrez en ajouter d'autres plus tard.</span>
        </div>
        <div class="field"><label for="pin1">Code de la famille (4 chiffres)</label>
          <input id="pin1" name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" required></div>
        <div class="field"><label for="pin2">Retapez le code</label>
          <input id="pin2" name="pin2" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" required></div>
        ${locationFields({})}
        <button class="btn primary big block" type="submit">C'est parti</button>
      </form>`;
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

  // ---------- code familial
  let pinBuffer = '';
  let loginWho = store.get('kk-last-person', null);

  function viewLogin() {
    if (!S.people.some((p) => p.id === loginWho)) loginWho = S.people[0]?.id ?? null;
    return `
      <div class="brand" style="justify-content:center">${I.egg} KotKot</div>
      <h1 style="text-align:center;font-size:1.45rem">Qui êtes-vous ?</h1>
      <div class="people-pick">
        ${S.people.map((p) => `<button type="button" class="person" data-act="loginWho" data-id="${p.id}" aria-pressed="${p.id === loginWho}">${avatar(p)}<span>${h(p.name)}</span></button>`).join('')}
      </div>
      ${err()}
      ${pinPad('Code de la famille')}`;
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
      const data = await post('login', { pin, personId: loginWho });
      store.set('kk-last-person', loginWho);
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
      <div class="card stack">
        <div class="field"><label for="pdate">Date de la photo</label>
          <input id="pdate" type="date" max="${today}" value="${h(photoDate)}" data-photo-date>
          <span class="muted">Laissez vide pour la date d'aujourd'hui (ou celle de l'appareil photo).</span></div>
        <label class="btn primary">${I.camera} Ajouter une photo<input class="sr" type="file" accept="image/*" data-photo="${hen.id}"></label>
      </div>`;
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
    const size = String(store.get('kk-size', 1));
    const notif = 'Notification' in window ? Notification.permission : 'unsupported';
    return `
      ${back('#/', 'Accueil')}
      <h1>Réglages</h1>
      ${err()}
      <div class="card stack">
        <h2 style="margin:0">Taille du texte</h2>
        <div class="seg">${[['1', 'Normal'], ['2', 'Grand'], ['3', 'Très grand']].map(([v, l]) => `<button type="button" data-act="size" data-v="${v}" aria-pressed="${size === v}">${l}</button>`).join('')}</div>
        <p class="muted" style="margin:0">Seulement sur ce téléphone.</p>
      </div>
      <div class="card stack">
        <h2 style="margin:0">Rappel pour fermer la porte</h2>
        <div class="seg">${[15, 30, 60].map((m) => `<button type="button" data-act="delay" data-v="${m}" aria-pressed="${s.reminderDelay === m}">${m === 60 ? '1 h' : m + ' min'}</button>`).join('')}</div>
        <p class="muted" style="margin:0">après le coucher du soleil.</p>
        ${notif === 'unsupported' ? '' : notif === 'granted'
          ? '<p class="hint">' + I.bell + '<span>Les rappels s\'affichent sur ce téléphone quand KotKot est ouvert ou en arrière-plan.</span></p>'
          : `<button class="btn" type="button" data-act="notify">${I.bell} Autoriser les rappels sur ce téléphone</button>`}
      </div>
      <form class="card stack" data-form="location">
        <h2 style="margin:0">Le poulailler</h2>
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
      <a class="btn" href="#/taches/gerer">${I.tasks} Gérer les tâches</a>
      <button class="btn danger" type="button" data-act="logout">Déconnecter ce téléphone</button>
      <p class="muted" style="text-align:center">KotKot ${h(S.version || '')}</p>`;
  }

  function applySize() { document.documentElement.dataset.size = String(store.get('kk-size', 1)); }

  // ------------------------------------------------------------------ rappels (porte)
  async function checkDoorReminder() {
    const d = S.door;
    if (!d?.shouldClose || !('Notification' in window) || Notification.permission !== 'granted') return;
    const key = `door-${d.remindAt}`;
    if (store.get('kk-notified', '') === key) return;
    store.set('kk-notified', key);
    try {
      const reg = await navigator.serviceWorker?.ready;
      const opts = { body: 'Pensez à fermer la porte du poulailler !', tag: 'kotkot-door', icon: 'assets/icon-192.png', badge: 'assets/icon-192.png' };
      if (reg) reg.showNotification('KotKot', opts); else new Notification('KotKot', opts);
    } catch { /* notifications indisponibles */ }
  }

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
    async notify() { if ('Notification' in window) { await Notification.requestPermission(); render(); checkDoorReminder(); } },
    async personRemove(el) {
      const id = Number(el.dataset.id);
      await call('person_remove', { id });
      toast('Personne retirée', () => call('person_remove', { id, restore: true }).then(render));
      render();
    },
    async logout() { hideToast(); try { await post('logout', {}); } catch { /* tant pis */ } setState({ ...S, auth: false }); go('#/'); },
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
    if (ev.target.matches('[data-photo-date]')) photoDate = ev.target.value;
    const input = ev.target.closest('[data-photo]');
    if (input?.files?.[0]) uploadPhoto(Number(input.dataset.photo), input.files[0]);
  });

  // ------------------------------------------------------------------ formulaires
  const forms = {
    async setup(f, fd) {
      const pin = fd.get('pin');
      if (!/^\d{4}$/.test(pin)) return showError('Le code doit faire exactement 4 chiffres.');
      if (pin !== fd.get('pin2')) return showError('Les deux codes ne sont pas pareils.');
      const people = fd.getAll('person').map((s) => s.trim()).filter(Boolean);
      if (!people.length) return showError('Indiquez au moins un prénom.');
      try {
        const data = await post('setup', { pin, people, city: fd.get('city'), lat: fd.get('lat'), lon: fd.get('lon') });
        errorText = ''; setState(data.state); go('#/');
      } catch (e) { showError(e.message); }
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
    Promise.resolve(forms[f.dataset.form](f, new FormData(f))).catch(() => { /* erreur déjà affichée */ });
  });

  // ------------------------------------------------------------------ démarrage
  applySize();
  window.addEventListener('online', () => { online = true; flush(); refresh(); });
  window.addEventListener('offline', () => setOnline(false));
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { flush(); refresh(); } });
  setInterval(() => { if (!document.hidden) refresh(); }, 60000);
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
  render();
  refresh().then(flush);
})();
