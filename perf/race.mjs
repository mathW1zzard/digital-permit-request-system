// Week 16, open item H2: truly parallel duplicate requests against a multi-worker server.
//
//   LOAD_PASSWORD=... node perf/race.mjs --base https://localhost:8444/api --ca certs/dev-ca.pem \
//        --trials 30 --out evidence/week16/H2-parallel
//
// R1 (Week 15 F4): two endorsements of the same permit, sent at the same moment on two connections.
//     Correct: exactly one 200 and one 409 per permit, and one "endorsed" event (checked in the
//     database afterwards by the caller).
// R2 (Week 15 F5): the same submission with the same Idempotency-Key, sent at the same moment.
//     Correct: one 201 and one 200 with Idempotent-Replayed: true, the same reference, one permit.
// Each trial uses two fresh TLS connections that are opened first and then released together,
// so both requests reach nginx within a millisecond or two of each other.
import https from 'node:https';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { performance } from 'node:perf_hooks';

const args = Object.fromEntries(process.argv.slice(2).reduce((a, x, i, all) => (x.startsWith('--') && a.push([x.slice(2), all[i + 1]]), a), []));
const base = new URL(args.base);
const ca = fs.readFileSync(args.ca);
const trials = Number(args.trials ?? 30);
const out = args.out;
const password = process.env.LOAD_PASSWORD ?? '';
fs.mkdirSync(out, { recursive: true });

function call(method, p, token, body, headers = {}) {
  const payload = body ? JSON.stringify(body) : null;
  const agent = new https.Agent({ keepAlive: false });
  return new Promise((resolve) => {
    const t = performance.now();
    const req = https.request({ method, host: base.hostname, port: base.port, path: base.pathname + p, ca, agent,
      headers: { Accept: 'application/json', ...(payload ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: 'Bearer ' + token } : {}), ...headers } }, (res) => {
      let d = ''; res.on('data', (c) => (d += c));
      res.on('end', () => { let j = null; try { j = JSON.parse(d); } catch {} resolve({ status: res.statusCode, ms: performance.now() - t, json: j, headers: res.headers, at: t }); });
    });
    req.on('error', (e) => resolve({ status: 'neterr:' + e.code, ms: performance.now() - t, json: null, headers: {}, at: t }));
    if (payload) req.write(payload);
    req.end();
  });
}

const login = async (email) => (await call('POST', '/login', null, { email, password })).json?.data?.token;
const body = (i) => ({ permit_type_id: 1, purpose: `Parallel race trial ${i} (fictional)`, location: 'Race Hall',
  requested_start: new Date(Date.now() + 5 * 86400000).toISOString().slice(0, 10) });

const lines = [];
const say = (s) => { lines.push(s); console.log(s); };

async function main() {
  // Ten requesters in turn: one user may make 60 requests a minute, and 30 trials send 60 submissions.
  const requesters = [];
  for (let n = 31; n <= 40; n++) requesters.push(await login(`load-req-${n}@load.test`));
  const reviewerTokens = {};
  for (const email of ['reviewer1@permit.test', 'reviewer2@permit.test', 'load-rev-1@load.test', 'load-rev-2@load.test', 'load-rev-3@load.test', 'load-rev-4@load.test']) {
    reviewerTokens[email] = await login(email);
  }

  // ---- R2: same submission, same key, in parallel -------------------------------------
  say(`R2 parallel duplicate submission, ${trials} trials (same user, same body, same Idempotency-Key)`);
  const r2 = [];
  for (let i = 1; i <= trials; i++) {
    const key = 'race-' + crypto.randomUUID();
    const b = body(i);
    const [a, c] = await Promise.all([
      call('POST', '/permits', requesters[i % requesters.length], b, { 'Idempotency-Key': key }),
      call('POST', '/permits', requesters[i % requesters.length], b, { 'Idempotency-Key': key }),
    ]);
    const refs = [a.json?.data?.reference, c.json?.data?.reference];
    const statuses = [a.status, c.status].sort();
    const replayed = [a.headers['idempotent-replayed'], c.headers['idempotent-replayed']].sort();
    const ok = statuses.join() === '200,201' && refs[0] && refs[0] === refs[1] && replayed.join() === 'false,true';
    r2.push({ trial: i, key, statuses, replayed, refs, gap_ms: Math.round(Math.abs(a.at - c.at) * 10) / 10,
      ms: [Math.round(a.ms), Math.round(c.ms)], ok, errors: [a, c].filter((x) => x.status >= 500).map((x) => x.json?.error?.code) });
  }
  const r2ok = r2.filter((x) => x.ok).length;
  const r2tally = {};
  for (const x of r2) r2tally[x.statuses.join('+')] = (r2tally[x.statuses.join('+')] ?? 0) + 1;
  say(`  correct: ${r2ok}/${trials}; status pairs ${JSON.stringify(r2tally)}`);

  // ---- R1: two endorsements of one permit, in parallel ---------------------------------
  say(`R1 parallel double endorsement, ${trials} trials (assigned reviewer, two connections)`);
  const r1 = [];
  const refs = [...new Set(r2.map((x) => x.refs[0]).filter(Boolean))];
  // Each reviewer's own list shows the permits assigned to them (TD6: the detail view has no reviewer).
  const tokenByRef = {};
  for (const tok of Object.values(reviewerTokens)) {
    for (const p of (await call('GET', '/permits', tok)).json?.data ?? []) tokenByRef[p.reference] = tok;
  }
  for (let i = 0; i < Math.min(trials, refs.length); i++) {
    const ref = refs[i];
    const tok = tokenByRef[ref];
    if (!tok) { r1.push({ ref, ok: false, note: 'assigned reviewer not found in any reviewer list' }); continue; }
    const [a, c] = await Promise.all([call('POST', `/permits/${ref}/endorse`, tok), call('POST', `/permits/${ref}/endorse`, tok)]);
    const statuses = [a.status, c.status].sort();
    r1.push({ ref, statuses, gap_ms: Math.round(Math.abs(a.at - c.at) * 10) / 10, ms: [Math.round(a.ms), Math.round(c.ms)],
      ok: statuses.join() === '200,409', codes: [a.json?.error?.code, c.json?.error?.code].filter(Boolean) });
  }
  const r1ok = r1.filter((x) => x.ok).length;
  const r1tally = {};
  for (const x of r1) r1tally[(x.statuses ?? ['?']).join('+')] = (r1tally[(x.statuses ?? ['?']).join('+')] ?? 0) + 1;
  say(`  correct: ${r1ok}/${r1.length}; status pairs ${JSON.stringify(r1tally)}`);

  fs.writeFileSync(path.join(out, 'r2-submissions.json'), JSON.stringify(r2, null, 1));
  fs.writeFileSync(path.join(out, 'r1-endorsements.json'), JSON.stringify(r1, null, 1));
  fs.writeFileSync(path.join(out, 'refs.txt'), refs.join('\n') + '\n');
  fs.writeFileSync(path.join(out, 'summary.txt'), lines.join('\n') + '\n');
}
main();
