// Usage : node tests/sw_test.js .   (depuis le dossier de KotKot)
// Runs public/sw.js in a fake service-worker world and fires a « gazette » push.
const fs = require('fs'), path = require('path'), vm = require('vm');
const root = process.argv[2];
const shown = [];
const listeners = {};
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Paris', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const state = { auth: true, today, settings: { timezone: 'Europe/Paris' }, hens: [{ id: 1, name: 'Hei Hei', species: 'poule', lays: 'oui', layingSince: today }],
  eggs: { today: 4, last7: 20, todayBySpecies: { poule: 4 }, quality: { recent: [] } }, door: { history: [] }, tasks: [] };
let fail = false;
const self = {
  addEventListener: (t, f) => { listeners[t] = f; },
  importScripts: (u) => vm.runInContext(fs.readFileSync(path.join(root, 'public', u.split('?')[0]), 'utf8'), ctx),
  registration: { showNotification: async (title, o) => shown.push({ title, ...o }), scope: 'https://x/' },
  clients: {},
  location: { origin: 'https://x' },
};
const ctx = vm.createContext({ self, caches: {}, fetch: async (u, o) => {
  if (fail) throw new Error('offline');
  if (u.includes('a=state')) return { json: async () => ({ ok: true, state }) };
  if (u.includes('a=gazette')) { if (o.headers['X-KotKot'] !== '1') throw new Error('header'); return { json: async () => ({ ok: true, gazette: { total: 50 } }) }; }
}, Intl, Date, Math, Promise, URL, console });
// in a real service worker, « self » IS the global object
Object.assign(ctx, self);
ctx.self = ctx;
self.registration = ctx.registration;
vm.runInContext(fs.readFileSync(path.join(root, 'public/sw.js'), 'utf8'), ctx);
const push = async (payload) => { let p; listeners.push({ data: { json: () => payload, text: () => '' }, waitUntil: (x) => { p = x; } }); await p; };
(async () => {
  await push({ type: 'gazette', title: 'La Gazette du poulailler', body: 'Du nouveau', url: './#/gazette', tag: 'kotkot-gazette' });
  const ok = (w, c) => console.log((c ? '  ok  ' : '  FAIL ') + w);
  console.log('  →', shown[0].title, '|', shown[0].body);
  ok('a real headline written by the phone', shown[0].title.startsWith('La Gazette · ') && shown[0].body.length > 5 && shown[0].data.url === './#/gazette');
  const seen = new Set();
  for (let i = 0; i < 25; i++) { await push({ type: 'gazette' }); seen.add(shown.at(-1).body); }
  console.log('  → 25 notifications, ' + seen.size + ' titres différents');
  ok('varied', seen.size >= 6);
  fail = true;
  await push({ type: 'gazette', title: 'La Gazette du poulailler', body: 'Du nouveau au poulailler ! Touchez pour lire.' });
  ok('no network: plain text instead', shown.at(-1).body === 'Du nouveau au poulailler ! Touchez pour lire.');
  await push({ title: 'Pensez à fermer la porte', body: 'x', url: './#/porte' });
  ok('door reminders unchanged', shown.at(-1).title === 'Pensez à fermer la porte');
})();
