/* KotKot service worker: the app opens even without network, and shows reminders. */
const CACHE = 'kotkot-2.9.0';
// The Gazette's writer, to compose a real headline when a « Gazette » notification arrives.
self.window = self;
try { importScripts('assets/gazette.js?v=2.9.0'); } catch { /* the notification will use its plain text */ }
const SHELL = [
  './',
  'assets/app.css?v=2.9.0',
  'assets/breeds.js?v=2.9.0',
  'assets/gazette.js?v=2.9.0',
  'assets/app.js?v=2.9.0',
  'assets/icon.svg',
  'assets/icon-192.png',
  'assets/icon-512.png',
  'assets/fonts/atkinson-regular.woff2',
  'assets/fonts/atkinson-bold.woff2',
  'manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;
  if (url.pathname.endsWith('/api.php')) return; // always live data

  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((res) => { caches.open(CACHE).then((c) => c.put('./', res.clone())); return res; })
        .catch(() => caches.match('./'))
    );
    return;
  }
  event.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
});

// Reminders sent by the server (door left open, treatment to do).
self.addEventListener('push', (event) => {
  let msg = {};
  try { msg = event.data ? event.data.json() : {}; } catch { msg = { body: event.data && event.data.text() }; }
  if (msg.type === 'gazette') {
    event.waitUntil(gazetteNotice(msg));
    return;
  }
  event.waitUntil(self.registration.showNotification(msg.title || 'KotKot', {
    body: msg.body || '',
    tag: msg.tag || 'kotkot',
    renotify: true,
    icon: 'assets/icon-192.png',
    badge: 'assets/icon-192.png',
    data: { url: msg.url || './#/' },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = new URL((event.notification.data && event.notification.data.url) || './#/', self.registration.scope).href;
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      const open = list.find((c) => 'focus' in c);
      return open ? open.focus().then((c) => (c.navigate ? c.navigate(url) : c)) : self.clients.openWindow(url);
    })
  );
});

// ---------------------------------------------------------------- « La Gazette » notifications
const CRITERIA = { fele: 'fêlé', casse: 'cassé', sale: 'sale', molle: 'à coquille molle', sans: 'sans coquille', deforme: 'déformé', sang: 'avec une trace de sang', petit: 'très petit', gros: 'très gros', double: 'à double jaune' };

/** Writes today's headline with the household's own data, like the app; plain text if anything fails. */
async function gazetteNotice(msg) {
  let title = msg.title || 'La Gazette du poulailler';
  let body = msg.body || 'Du nouveau au poulailler !';
  try {
    const opts = { credentials: 'same-origin', cache: 'no-store' };
    const [st, gz] = await Promise.all([
      fetch('api.php?a=state', opts).then((r) => r.json()),
      fetch('api.php?a=gazette', { ...opts, method: 'POST', headers: { 'X-KotKot': '1', 'Content-Type': 'application/json' }, body: '{}' }).then((r) => r.json()),
    ]);
    const S = st.state;
    if (S && S.auth && self.KOTKOT_GAZETTE) {
      const tz = (S.settings && S.settings.timezone) || 'Europe/Paris';
      const dayKey = (ts) => new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(ts * 1000));
      const daysBetween = (a, b) => Math.round((Date.parse(`${b}T12:00:00Z`) - Date.parse(`${a}T12:00:00Z`)) / 864e5);
      const longDay = (iso) => new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${iso}T12:00:00Z`));
      const time = (ts) => {
        const p = new Intl.DateTimeFormat('fr-FR', { timeZone: tz, hour: 'numeric', minute: '2-digit' }).formatToParts(new Date(ts * 1000));
        const g = (t) => (p.find((x) => x.type === t) || {}).value || '';
        return `${Number(g('hour'))} h ${g('minute')}`;
      };
      const hourOf = (ts) => Number(new Intl.DateTimeFormat('fr-FR', { timeZone: tz, hour: 'numeric', hourCycle: 'h23' }).format(new Date(ts * 1000)));
      const salt = Math.floor(Math.random() * 1000);
      const stories = self.KOTKOT_GAZETTE.stories({
        today: S.today, hour: hourOf(Date.now() / 1000), eggs: S.eggs, hens: S.hens, door: S.door, tasks: S.tasks,
        g: (gz && gz.gazette) || {}, species: (x) => x.species || 'poule', avatarOf: () => null, photoUrl: () => null,
        longDay, dayWord: (iso) => (iso === S.today ? 'aujourd\'hui' : daysBetween(iso, S.today) === 1 ? 'hier' : `le ${longDay(iso)}`),
        time, hourOf, plural: (n, a, b) => `${n} ${n > 1 ? b : a}`, daysBetween, dayKey,
        criteria: (list) => list.map((d) => CRITERIA[d] || d).join(', '), salt: () => salt,
      });
      // mostly real news, sometimes a daily column (horoscope, small ads…)
      const news = stories.filter((x) => !x.game);
      const pool = news.length && Math.random() < 0.75 ? news.slice(0, 4) : stories;
      const story = pool[Math.floor(Math.random() * pool.length)];
      if (story) {
        const fr = (t) => t.replace(/ ([!?:;»])/g, '\u00a0$1').replace(/« /g, '«\u00a0');
        title = `La Gazette · ${story.kicker}`;
        body = fr(story.title);
      }
    }
  } catch { /* plain text */ }
  return self.registration.showNotification(title, {
    body,
    tag: msg.tag || 'kotkot-gazette',
    icon: 'assets/icon-192.png',
    badge: 'assets/icon-192.png',
    data: { url: msg.url || './#/gazette' },
  });
}
