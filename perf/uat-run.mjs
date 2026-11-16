// Week 17 simulated UAT: plays each role on the scenario cards (docs/week17/41-uat-scenario-cards.md)
// against staging through the real API, and checks each card's "Pass when" conditions.
//
//   LOAD_PASSWORD=... node perf/uat-run.mjs --base https://localhost:8444/api --ca certs/dev-ca.pem \
//        --out evidence/week17/UAT-round1 [--only UAT-04,UAT-06] [--hook <command for UAT-10/11/12 steps>]
//
// Some cards need an operator action outside the API (expire the session, prepare an exception,
// fix an email address). Those steps call perf/uat-operator.sh with a step name, as the observer would.
// Results: results.json (every request and check) and results.md (one row per card).
// This is a simulation: it checks behaviour, not how easy the screens are for people.
import https from 'node:https';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const args = Object.fromEntries(process.argv.slice(2).reduce((a, x, i, all) => (x.startsWith('--') && a.push([x.slice(2), all[i + 1]]), a), []));
const base = new URL(args.base);
const ca = fs.readFileSync(args.ca);
const out = args.out;
const only = args.only ? new Set(args.only.split(',')) : null;
const password = process.env.LOAD_PASSWORD ?? '';
fs.mkdirSync(out, { recursive: true });
const here = path.dirname(fileURLToPath(import.meta.url));
const operator = (...step) => execFileSync('bash', [path.join(here, 'uat-operator.sh'), ...step], { encoding: 'utf8' }).trim();

const log = [];
function call(method, p, token, body, headers = {}, port) {
  const payload = body ? JSON.stringify(body) : null;
  return new Promise((resolve) => {
    const req = https.request({ method, host: base.hostname, port: port ?? base.port, path: base.pathname + p, ca, agent: false, timeout: 15000,
      headers: { Accept: 'application/json', ...(payload ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: 'Bearer ' + token } : {}), ...headers } }, (res) => {
      let d = ''; res.on('data', (c) => (d += c));
      res.on('end', () => {
        let j = null; try { j = JSON.parse(d); } catch {}
        const safe = JSON.parse(JSON.stringify(j ?? null, (k, v) => (k === 'token' ? '[redacted]' : v)));
        log.push({ method, path: p, status: res.statusCode, body: safe });
        resolve({ status: res.statusCode, json: j, headers: res.headers });
      });
    });
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', (e) => { log.push({ method, path: p, status: 'network_error', error: e.code ?? e.message }); resolve({ status: 'network_error', json: null, headers: {} }); });
    if (payload) req.write(payload);
    req.end();
  });
}
const login = async (email) => (await call('POST', '/login', null, { email, password })).json?.data?.token;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const day = (n) => new Date(Date.now() + n * 86400000).toISOString().slice(0, 10);
const form = (purpose, extra = {}) => ({ permit_type_id: 1, purpose, location: 'Barangay Covered Court', requested_start: day(5), ...extra });

const results = [];
function card(id, role, task, checks, note = '') {
  const pass = checks.every((c) => c.ok);
  results.push({ id, role, task, result: pass ? 'Pass' : 'Fail', checks, note });
  console.log(`${id} ${pass ? 'PASS' : 'FAIL'} ${checks.filter((c) => !c.ok).map((c) => c.what).join('; ')}`);
}
const want = (id) => !only || only.has(id);
const check = (what, ok, actual) => ({ what, ok: !!ok, actual });

async function main() {
  const T = {
    reqA: await login('requester@permit.test'), reqB: await login('load-req-01@load.test'),
    admin: await login('admin@permit.test'), inspector: await login('inspector@permit.test'),
    approver: await login('approver@permit.test'), senior: await login('senior@permit.test'),
  };
  const reviewers = {};
  for (const e of ['reviewer1@permit.test', 'reviewer2@permit.test', 'load-rev-1@load.test', 'load-rev-2@load.test', 'load-rev-3@load.test', 'load-rev-4@load.test']) reviewers[e] = await login(e);
  const reviewerFor = async (ref) => {
    for (const tok of Object.values(reviewers)) if (((await call('GET', '/permits', tok)).json?.data ?? []).some((p) => p.reference === ref)) return tok;
    return null;
  };
  const tag = crypto.randomUUID().slice(0, 6);
  const detail = async (tok, ref) => (await call('GET', '/permits/' + ref, tok)).json?.data;
  const inList = async (tok, ref) => ((await call('GET', '/permits', tok)).json?.data ?? []).find((p) => p.reference === ref);

  // UAT-01
  const s1 = await call('POST', '/permits', T.reqA, form(`Basketball clinic for the youth league (UAT ${tag})`), { 'Idempotency-Key': 'uat01-' + tag });
  const clinic = s1.json?.data?.reference;
  const l1 = clinic && await inList(T.reqA, clinic);
  if (want('UAT-01')) card('UAT-01', 'Requester', 'Apply for a Facility Use Permit', [
    check('created (201) with a PRM reference', s1.status === 201 && /^PRM-/.test(clinic ?? ''), `${s1.status} ${clinic}`),
    check('in the requester list as under_review', l1?.status === 'under_review', l1?.status),
    check('a reviewer is shown', !!(l1?.reviewer ?? s1.json?.data?.reviewer), l1?.reviewer ?? s1.json?.data?.reviewer),
  ]);

  // UAT-02
  if (want('UAT-02')) {
    const before = ((await call('GET', '/permits', T.reqA)).json?.data ?? []).length;
    const bad = await call('POST', '/permits', T.reqA, form('test', { requested_start: day(400) }), { 'Idempotency-Key': 'uat02-' + tag });
    const after = ((await call('GET', '/permits', T.reqA)).json?.data ?? []).length;
    const d = bad.json?.error?.details ?? {};
    card('UAT-02', 'Requester', 'Submit a form with mistakes', [
      check('refused with 422', bad.status === 422, bad.status),
      check('a message for the purpose', !!d.purpose, d.purpose?.[0]),
      check('a message for the start date', !!d.requested_start, d.requested_start?.[0]),
      check('nothing saved', before === after, `${before} -> ${after}`),
    ]);
  }

  // UAT-03
  const revClinic = clinic && await reviewerFor(clinic);
  const e3 = clinic && revClinic && await call('POST', `/permits/${clinic}/endorse`, revClinic);
  const d3 = clinic && await detail(revClinic, clinic);
  const endorsed = (d3?.events ?? []).find((e) => e.type === 'endorsed');
  if (want('UAT-03')) card('UAT-03', 'Reviewer', 'Endorse the application', [
    check('endorse accepted (200)', e3?.status === 200, e3?.status),
    check('status pending_approval', d3?.status === 'pending_approval', d3?.status),
    check('timeline shows the endorsement', !!endorsed, endorsed?.type),
    check('timeline shows who endorsed', !!(endorsed && (endorsed.by || endorsed.actor)), JSON.stringify(endorsed ?? null)),
  ]);

  // UAT-04
  if (want('UAT-04')) {
    const s = await call('POST', '/permits', T.reqA, form(`Zumba session for senior citizens (UAT ${tag})`), { 'Idempotency-Key': 'uat04-' + tag });
    const ref = s.json?.data?.reference;
    const rv = ref && await reviewerFor(ref);
    const note = 'Please give the exact start and end time.';
    const r1 = await call('POST', `/permits/${ref}/request-revision`, rv, { comment: note });
    const seen = await detail(T.reqA, ref);
    const noteVisible = JSON.stringify(seen ?? {}).includes(note);
    const r2 = await call('POST', `/permits/${ref}/resubmit`, T.reqA, { details: 'From 8:00 to 11:00 in the morning.' });
    const after = await detail(T.reqA, ref);
    card('UAT-04', 'Reviewer + Requester', 'Ask for a change, then resubmit', [
      check('request accepted (200)', r1.status === 200, r1.status),
      check('requester sees revision_requested', seen?.status === 'revision_requested', seen?.status),
      check("requester can read the reviewer's note", noteVisible, noteVisible ? 'shown' : 'not in the permit the requester receives'),
      check('resubmit accepted and back to under_review', r2.status === 200 && after?.status === 'under_review', `${r2.status} ${after?.status}`),
    ]);
  }

  // UAT-05
  const pendingApprover = async (ref) => {
    for (const tok of [T.approver, T.senior]) if ((await inList(tok, ref))) return tok;
    return null;
  };
  const apTok = clinic && await pendingApprover(clinic);
  const a5 = apTok && await call('POST', `/permits/${clinic}/approve`, apTok);
  const l5 = clinic && await inList(T.reqA, clinic);
  if (want('UAT-05')) card('UAT-05', 'Approver', 'Approve and issue the permit', [
    check('approve accepted (200)', a5?.status === 200, a5?.status),
    check('requester sees issued', l5?.status === 'issued', l5?.status),
    check('valid until is set', !!l5?.valid_until, l5?.valid_until),
  ]);

  // UAT-06
  if (want('UAT-06')) {
    const s = await call('POST', '/permits', T.reqB, form(`Videoke night at the covered court (UAT ${tag})`), { 'Idempotency-Key': 'uat06-' + tag });
    const ref = s.json?.data?.reference;
    const rv = await reviewerFor(ref);
    await call('POST', `/permits/${ref}/endorse`, rv);
    const ap = await pendingApprover(ref);
    const noReason = await call('POST', `/permits/${ref}/reject`, ap, {});
    const reason = 'Venue already booked that evening.';
    const rj = await call('POST', `/permits/${ref}/reject`, ap, { reason });
    const seen = await detail(T.reqB, ref);
    const visible = JSON.stringify(seen ?? {}).includes(reason);
    card('UAT-06', 'Approver', 'Reject with a reason', [
      check('rejecting without a reason is refused (422)', noReason.status === 422, noReason.status),
      check('reject accepted (200)', rj.status === 200, rj.status),
      check('requester sees rejected', seen?.status === 'rejected', seen?.status),
      check('requester can read the reason', visible, visible ? 'shown' : 'not in the permit the requester receives'),
    ]);
  }

  // UAT-07
  if (want('UAT-07')) {
    const v1 = clinic && await call('GET', `/permits/${clinic}/verify`, T.inspector);
    const other = (await call('POST', '/permits', T.reqB, form(`Clean-up drive along the creek (UAT ${tag})`), { 'Idempotency-Key': 'uat07-' + tag })).json?.data?.reference;
    const v2 = await call('GET', `/permits/${other}/verify`, T.inspector);
    card('UAT-07', 'Inspector', 'Check validity at the venue', [
      check('issued permit is valid with a date', v1?.json?.data?.valid === true && !!v1.json.data.valid_until, JSON.stringify(v1?.json?.data ?? null)),
      check('unissued permit is not valid, with its status', v2.json?.data?.valid === false && v2.json.data.status === 'under_review', JSON.stringify(v2.json?.data ?? null)),
    ]);
  }

  // UAT-08
  if (want('UAT-08')) {
    const ref = (await call('POST', '/permits', T.reqB, form(`Fun run for the barangay health week (UAT ${tag})`), { 'Idempotency-Key': 'uat08-' + tag })).json?.data?.reference;
    const rv = await reviewerFor(ref);
    const [a, b] = await Promise.all([call('POST', `/permits/${ref}/endorse`, rv), call('POST', `/permits/${ref}/endorse`, rv)]);
    const statuses = [a.status, b.status].sort().join('+');
    const loser = [a, b].find((x) => x.status === 409);
    const listNow = await inList(rv, ref);
    const d = await detail(rv, ref);
    card('UAT-08', 'Two reviewers', 'Endorse the same application at once', [
      check('one 200 and one 409', statuses === '200+409', statuses),
      check('the 409 says someone else acted', /already|someone|changed|status/i.test(loser?.json?.error?.message ?? ''), loser?.json?.error?.message),
      check('the list shows the current status (pending_approval)', listNow?.status === 'pending_approval', listNow?.status),
      check('exactly one endorsement in the timeline', (d?.events ?? []).filter((e) => e.type === 'endorsed').length === 1, (d?.events ?? []).filter((e) => e.type === 'endorsed').length),
    ], 'The web list reloading without Refresh after a 409 is covered by the Flutter test added in Week 17.');
  }

  // UAT-09
  if (want('UAT-09')) {
    const otherRef = (await call('GET', '/permits', T.reqB)).json?.data?.[0]?.reference;
    const peek = await call('GET', `/permits/${otherRef}`, T.reqA);
    const selfApprove = clinic && await call('POST', `/permits/${clinic}/approve`, T.reqA);
    card('UAT-09', 'Requester', "Open someone else's permit; approve own permit", [
      check("another requester's permit is refused (403)", peek.status === 403, peek.status),
      check('the refusal has a clear message', !!peek.json?.error?.message, peek.json?.error?.message),
      check('approving is refused (403)', selfApprove?.status === 403, selfApprove?.status),
    ]);
  }

  // UAT-10
  if (want('UAT-10')) {
    const tok = await login('load-req-02@load.test');
    const ref = (await call('POST', '/permits', tok, form(`Bible study in the multi-purpose hall (UAT ${tag})`), { 'Idempotency-Key': 'uat10-' + tag })).json?.data?.reference;
    operator('expire-sessions', 'load-req-02@load.test');
    const after = await call('GET', '/permits', tok);
    const again = await login('load-req-02@load.test');
    const still = again && await inList(again, ref);
    card('UAT-10', 'Requester', 'Session ends, then sign in again', [
      check('the old session is refused (401)', after.status === 401, after.status),
      check('the 401 says to sign in', !!after.json?.error?.message, after.json?.error?.message),
      check('after signing in again the permit is still there', !!still, still?.status),
    ], 'The "your session ended" screen in the web app is the Week 14 U5 behaviour (screenshot U5).');
  }

  // UAT-11
  if (want('UAT-11')) {
    const tok = await login('load-req-03@load.test');
    const key = 'uat11-' + tag;
    const body = form(`Feeding program for day-care children (UAT ${tag})`);
    const cut = await call('POST', '/permits', tok, body, { 'Idempotency-Key': key }, 8449); // nothing listens: connection fails
    const first = await call('POST', '/permits', tok, body, { 'Idempotency-Key': key });
    const retry = await call('POST', '/permits', tok, body, { 'Idempotency-Key': key }); // the answer was "lost": press Submit again
    const mine = ((await call('GET', '/permits', tok)).json?.data ?? []).filter((p) => p.purpose === body.purpose);
    card('UAT-11', 'Requester', 'Connection problem while submitting', [
      check('the cut connection fails without reaching the server', cut.status === 'network_error', cut.status),
      check('the next attempt creates the permit (201)', first.status === 201, first.status),
      check('pressing Submit again returns the same permit (200, replayed)', retry.status === 200 && retry.headers['idempotent-replayed'] === 'true' && retry.json?.data?.reference === first.json?.data?.reference, `${retry.status} ${retry.headers['idempotent-replayed']}`),
      check('exactly one permit exists', mine.length === 1, mine.length),
    ], 'The message that stays on the form is covered by the Week 16 Flutter test (U1).');
  }

  // UAT-12
  if (want('UAT-12')) {
    const tok = await login('bounce@invalid.test');
    const ref = (await call('POST', '/permits', tok, form(`Medical mission at the barangay hall (UAT ${tag})`), { 'Idempotency-Key': 'uat12-' + tag })).json?.data?.reference;
    await call('POST', `/permits/${ref}/endorse`, await reviewerFor(ref));
    await call('POST', `/permits/${ref}/approve`, await pendingApprover(ref)); // "issued" notification goes to the bad address
    let item = null;
    for (let i = 0; i < 12 && !item; i++) {
      await sleep(10000);
      item = ((await call('GET', '/admin/exceptions', T.admin)).json?.data?.notifications ?? []).find((n) => n.permit === ref);
    }
    let requeue = null; let gone = false;
    if (item) {
      operator('fix-bounce-email');
      requeue = await call('POST', `/admin/notifications/${item.id}/requeue`, T.admin);
      for (let i = 0; i < 12 && !gone; i++) {
        await sleep(10000);
        gone = !((await call('GET', '/admin/exceptions', T.admin)).json?.data?.notifications ?? []).some((n) => n.id === item.id);
      }
      operator('restore-bounce-email');
    }
    card('UAT-12', 'Admin', 'Handle a notification that needs a person', [
      check('listed on the exceptions screen with a reason', !!item && !!item.failure, item ? `${item.kind} ${item.failure} ${item.detail}` : 'not listed within 2 min'),
      check('requeue accepted (200)', requeue?.status === 200, requeue?.status),
      check('after the fix it leaves the list', gone, gone),
    ], 'Fixing the address is an operator step (php artisan tinker); there is no admin edit screen (known limitation).');
  }

  fs.writeFileSync(path.join(out, 'results.json'), JSON.stringify({ base: base.href, at: new Date().toISOString(), results, requests: log }, null, 1));
  const lines = ['| Card | Role | Task | Result | Failed checks |', '| --- | --- | --- | --- | --- |'];
  for (const r of results) lines.push(`| ${r.id} | ${r.role} | ${r.task} | **${r.result}** | ${r.checks.filter((c) => !c.ok).map((c) => `${c.what} (actual: ${c.actual})`).join('; ') || '–'} |`);
  fs.writeFileSync(path.join(out, 'results.md'), lines.join('\n') + '\n');
  console.log(`${results.filter((r) => r.result === 'Pass').length}/${results.length} cards passed`);
}
main();
