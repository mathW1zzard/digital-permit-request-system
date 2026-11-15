// Week 16 load generator for the Permit Request API. Node 18+ built-ins only (no npm install).
//
//   LOAD_PASSWORD=... node perf/loadgen.mjs --base https://localhost:8444/api --model perf/models/ordinary.json \
//        --out evidence/week16/P1-staging-ordinary [--ca certs/dev-ca.pem] [--label "staging"]
//
// Each virtual user (VU) signs in once with its own fictional account, then repeats the journey
// in the model: pick an operation by weight, call the API, wait a random "think time", repeat.
// Every request is recorded (time, user, operation, status, duration). Results:
//   requests.csv   one line per request
//   timeline.csv   per 10-second window: requests, throughput, p95, errors
//   summary.json / summary.txt   percentiles per operation, throughput, errors, threshold verdicts
// The password is read from LOAD_PASSWORD and never written anywhere. Tokens are never logged.
import https from 'node:https';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { performance } from 'node:perf_hooks';

const args = Object.fromEntries(process.argv.slice(2).reduce((acc, a, i, all) => {
  if (a.startsWith('--')) acc.push([a.slice(2), all[i + 1] && !all[i + 1].startsWith('--') ? all[i + 1] : 'true']);
  return acc;
}, []));
const base = new URL(args.base ?? 'https://localhost:8444/api');
const model = JSON.parse(fs.readFileSync(args.model, 'utf8'));
const outDir = args.out ?? 'perf-out';
const password = process.env.LOAD_PASSWORD ?? '';
if (password.length < 12) { console.error('Set LOAD_PASSWORD (the environment\'s DEMO_PASSWORD).'); process.exit(2); }
const ca = args.ca ? fs.readFileSync(args.ca) : undefined;
fs.mkdirSync(outDir, { recursive: true });

const records = [];          // [tMs, vu, op, status, ms, outcome]
const t0 = performance.now();
const now = () => performance.now() - t0;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const between = ([a, b]) => a + Math.random() * (b - a);
let stopAt = Infinity;
let submitted = 0;

function request(agent, method, p, token, body, extraHeaders = {}) {
  const lib = base.protocol === 'https:' ? https : http;
  const payload = body ? JSON.stringify(body) : null;
  const headers = {
    Accept: 'application/json',
    'X-Correlation-Id': 'load-' + crypto.randomUUID(),
    ...(payload ? { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) } : {}),
    ...(token ? { Authorization: 'Bearer ' + token } : {}),
    ...extraHeaders,
  };
  return new Promise((resolve) => {
    const started = performance.now();
    const req = lib.request({
      method, host: base.hostname, port: base.port, path: base.pathname.replace(/\/$/, '') + p,
      headers, agent, ca, timeout: 30000,
    }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        let json = null;
        try { json = JSON.parse(Buffer.concat(chunks).toString('utf8')); } catch { /* not JSON */ }
        resolve({ status: res.statusCode, ms: performance.now() - started, json });
      });
    });
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', (e) => resolve({ status: e.message === 'timeout' ? 'timeout' : 'neterr', ms: performance.now() - started, json: null }));
    if (payload) req.write(payload);
    req.end();
  });
}

function record(vu, op, res, expected) {
  const outcome = expected.includes(res.status) ? 'ok' : (typeof res.status === 'number' && res.status < 500 && res.status !== 429 ? 'client' : 'error');
  records.push([Math.round(now()), vu, op, res.status, Math.round(res.ms * 10) / 10, outcome]);
  return outcome === 'ok';
}

const purposes = ['Barangay assembly in the covered court', 'Basketball league opening ceremony',
  'Repair of drainage cover near the plaza', 'Welding of the market stall frames', 'Community feeding program'];
const today = new Date();
function permitBody() {
  const start = new Date(today.getTime() + (2 + Math.floor(Math.random() * 60)) * 86400000);
  return {
    permit_type_id: 1 + Math.floor(Math.random() * 3),
    purpose: purposes[Math.floor(Math.random() * purposes.length)] + ' (load test)',
    location: 'Fictional Hall ' + (1 + Math.floor(Math.random() * 9)),
    requested_start: start.toISOString().slice(0, 10),
  };
}

function pick(mix) {
  const total = mix.reduce((s, m) => s + m.weight, 0);
  let r = Math.random() * total;
  for (const m of mix) { if ((r -= m.weight) <= 0) return m.op; }
  return mix[mix.length - 1].op;
}

async function runVu(vu, role, email) {
  const agent = new (base.protocol === 'https:' ? https.Agent : http.Agent)({ keepAlive: true, maxSockets: 1 });
  await sleep(between([0, (model.ramp_s ?? 0) * 1000]));
  const login = await request(agent, 'POST', '/login', null, { email, password });
  record(vu, 'login', login, [200]);
  const token = login.json?.data?.token;
  if (!token) return;
  let known = [];
  const mix = model.mix[role];
  while (now() < stopAt) {
    const op = pick(mix);
    let res;
    if (op === 'list') {
      res = await request(agent, 'GET', '/permits', token);
      record(vu, 'list', res, [200]);
      if (res.json?.data) known = res.json.data;
    } else if (op === 'detail') {
      if (!known.length) { res = await request(agent, 'GET', '/permits', token); record(vu, 'list', res, [200]); known = res.json?.data ?? []; }
      if (known.length) {
        const p = known[Math.floor(Math.random() * known.length)];
        record(vu, 'detail', await request(agent, 'GET', '/permits/' + p.reference, token), [200]);
      }
    } else if (op === 'types') {
      record(vu, 'types', await request(agent, 'GET', '/permit-types', token), [200]);
    } else if (op === 'submit') {
      if (model.max_submits && submitted >= model.max_submits) { stopAt = Math.min(stopAt, now()); break; }
      submitted++;
      const key = 'load-' + crypto.randomUUID();
      record(vu, 'submit', await request(agent, 'POST', '/permits', token, permitBody(), { 'Idempotency-Key': key }), [201, 200]);
    } else if (op === 'endorse' || op === 'approve') {
      const want = op === 'endorse' ? 'under_review' : 'pending_approval';
      res = await request(agent, 'GET', '/permits', token);
      record(vu, 'list', res, [200]);
      const candidates = (res.json?.data ?? []).filter((p) => p.status === want);
      if (candidates.length) {
        const p = candidates[Math.floor(Math.random() * candidates.length)];
        // 409 is a correct answer when another user acted first; it is counted separately.
        record(vu, op, await request(agent, 'POST', `/permits/${p.reference}/${op}`, token), [200, 409]);
      }
    } else if (op === 'health') {
      record(vu, 'health', await request(agent, 'GET', '/health', null), [200]);
    }
    await sleep(between(model.think_ms));
  }
  agent.destroy();
}

function pct(sorted, p) {
  if (!sorted.length) return null;
  const i = Math.min(sorted.length - 1, Math.ceil((p / 100) * sorted.length) - 1);
  return sorted[Math.max(0, i)];
}

async function main() {
  const users = [];
  const n = (k) => model.vus[k] ?? 0;
  for (let i = 1; i <= n('requester'); i++) users.push(['requester', `load-req-${String(i).padStart(2, '0')}@load.test`]);
  const reviewers = ['reviewer1@permit.test', 'reviewer2@permit.test', 'load-rev-1@load.test', 'load-rev-2@load.test', 'load-rev-3@load.test', 'load-rev-4@load.test'];
  for (let i = 0; i < n('reviewer'); i++) users.push(['reviewer', reviewers[i]]);
  const approvers = ['approver@permit.test', 'senior@permit.test'];
  for (let i = 0; i < n('approver'); i++) users.push(['approver', approvers[i]]);

  const startedAt = new Date();
  stopAt = (model.duration_s + (model.ramp_s ?? 0)) * 1000;
  console.log(`${model.name}: ${users.length} users, ${model.duration_s}s (+${model.ramp_s ?? 0}s ramp, ${model.warmup_s ?? 0}s warm-up excluded), base ${base.href}`);
  await Promise.all(users.map(([role, email], i) => runVu(i + 1, role, email)));
  const endedAt = new Date();

  fs.writeFileSync(path.join(outDir, 'requests.csv'),
    't_ms,vu,op,status,ms,outcome\n' + records.map((r) => r.join(',')).join('\n') + '\n');

  const warm = (model.warmup_s ?? 0) * 1000;
  const window = records.filter((r) => r[0] >= warm && r[2] !== 'login');
  const spanS = Math.max(1, (Math.max(...window.map((r) => r[0]), warm) - warm) / 1000);
  const byOp = {};
  for (const r of records) (byOp[r[2]] ??= []).push(r);
  const ops = {};
  for (const [op, rs] of Object.entries(byOp)) {
    const inWin = op === 'login' ? rs : rs.filter((r) => r[0] >= warm);
    const ms = inWin.map((r) => r[4]).sort((a, b) => a - b);
    const statuses = {};
    for (const r of inWin) statuses[r[3]] = (statuses[r[3]] ?? 0) + 1;
    ops[op] = {
      count: inWin.length, ok: inWin.filter((r) => r[5] === 'ok').length,
      errors: inWin.filter((r) => r[5] === 'error').length, client: inWin.filter((r) => r[5] === 'client').length,
      p50: pct(ms, 50), p90: pct(ms, 90), p95: pct(ms, 95), p99: pct(ms, 99), max: ms[ms.length - 1] ?? null,
      statuses,
    };
  }
  const allMs = window.map((r) => r[4]).sort((a, b) => a - b);
  const errors = window.filter((r) => r[5] === 'error').length;
  const overall = {
    requests: window.length, duration_s: Math.round(spanS), throughput_rps: Math.round((window.length / spanS) * 100) / 100,
    errors, error_rate: window.length ? Math.round((errors / window.length) * 10000) / 100 : 0,
    p50: pct(allMs, 50), p95: pct(allMs, 95), p99: pct(allMs, 99), max: allMs[allMs.length - 1] ?? null,
  };

  const timeline = [];
  const end = Math.max(0, ...records.map((r) => r[0]));
  for (let w = 0; w <= end; w += 10000) {
    const rs = records.filter((r) => r[0] >= w && r[0] < w + 10000 && r[2] !== 'login');
    const ms = rs.map((r) => r[4]).sort((a, b) => a - b);
    timeline.push([w / 1000, rs.length, Math.round((rs.length / 10) * 10) / 10, pct(ms, 95) ?? '', rs.filter((r) => r[5] === 'error').length]);
  }
  fs.writeFileSync(path.join(outDir, 'timeline.csv'), 'window_start_s,requests,rps,p95_ms,errors\n' + timeline.map((t) => t.join(',')).join('\n') + '\n');

  const verdicts = [];
  const th = model.thresholds ?? {};
  const check = (name, actual, limit, cmp = '<=') => {
    if (limit === undefined || actual === null || actual === undefined) return;
    const pass = cmp === '<=' ? actual <= limit : actual >= limit;
    verdicts.push({ name, actual, limit: `${cmp} ${limit}`, pass });
  };
  check('error rate %', overall.error_rate, th.error_rate_pct);
  check('p95 all requests ms', overall.p95, th.p95_ms);
  for (const op of ['list', 'detail', 'types']) check(`p95 ${op} ms`, ops[op]?.p95, th.read_p95_ms);
  for (const op of ['submit', 'endorse', 'approve']) check(`p95 ${op} ms`, ops[op]?.p95, th.write_p95_ms);
  check('throughput rps', overall.throughput_rps, th.min_rps, '>=');

  const summary = { model: model.name, label: args.label ?? '', base: base.href, started: startedAt.toISOString(), ended: endedAt.toISOString(),
    users: users.length, model_file: args.model, overall, ops, verdicts, verdict: verdicts.every((v) => v.pass) ? 'PASS' : 'FAIL' };
  fs.writeFileSync(path.join(outDir, 'summary.json'), JSON.stringify(summary, null, 2));

  const lines = [];
  lines.push(`${model.name} ${args.label ?? ''} | ${base.href} | ${startedAt.toISOString()} -> ${endedAt.toISOString()} | ${users.length} users`);
  lines.push(`Measured window (after ${model.warmup_s ?? 0}s warm-up, sign-ins excluded): ${overall.requests} requests in ${overall.duration_s}s = ${overall.throughput_rps} req/s; errors ${overall.errors} (${overall.error_rate}%); p50 ${overall.p50} ms, p95 ${overall.p95} ms, p99 ${overall.p99} ms, max ${overall.max} ms`);
  lines.push('');
  lines.push('op        count   ok  4xx  err    p50    p90    p95    p99    max  statuses');
  for (const [op, o] of Object.entries(ops)) {
    const f = (v) => String(v ?? '-').padStart(6);
    lines.push(`${op.padEnd(8)} ${String(o.count).padStart(6)} ${String(o.ok).padStart(4)} ${String(o.client).padStart(4)} ${String(o.errors).padStart(4)} ${f(o.p50)} ${f(o.p90)} ${f(o.p95)} ${f(o.p99)} ${f(o.max)}  ${JSON.stringify(o.statuses)}`);
  }
  lines.push('');
  for (const v of verdicts) lines.push(`${v.pass ? 'PASS' : 'FAIL'}  ${v.name}: ${v.actual} (limit ${v.limit})`);
  lines.push(`Verdict: ${summary.verdict}`);
  fs.writeFileSync(path.join(outDir, 'summary.txt'), lines.join('\n') + '\n');
  console.log(lines.join('\n'));
}

main().catch((e) => { console.error(e); process.exit(1); });
