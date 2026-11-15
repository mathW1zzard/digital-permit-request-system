# Week 16 – Monitoring map and incident detection

Prepared by Claude Code (AI assistant) at the student's request.

## Signals

| Signal | Where | What it answers | Healthy | Who acts, and how |
| --- | --- | --- | --- | --- |
| **Liveness** `GET /up` | Laravel | Does a PHP process answer? | 200 | Operator: restart workers / nginx |
| **Readiness** `GET /api/health` (new, Week 16) | `HealthController` | Can this instance serve permits, and is background work keeping up? | 200 `ready` | See below |
| ↳ `database` | `select 1` | Database reachable | `ok`; `fail` makes it 503 `not_ready` | Operator: database |
| ↳ `scheduler_automation`, `scheduler_dispatch` | heartbeat written by each run of the two commands | Did the scheduled jobs run in the last 10 minutes? | `ok`; `stale` makes it `degraded` | Operator: scheduler, then the job log |
| ↳ `outbox` | count of pending notifications overdue by more than 10 min, and of those waiting for a person | Are notifications being delivered? | `ok`; `backlog` makes it `degraded` | Operator: gateway and dispatch log; admin: exception screen |
| Structured log `permit.jsonl` | `shared/storage/logs` | What happened, with correlation IDs; class and location only for exceptions | no `exception.unhandled` | Developer |
| nginx access log | `shared/logs/nginx-access.log` | Status and time of every request (`$request_time`, `$upstream_response_time`) | no 5xx; times as in the result sheet | Operator |
| nginx error log | `shared/logs/nginx-error.log` | Upstream failures (timeouts, no live upstreams) | empty | Operator |
| Ops log | `shared/logs/ops.log` | Deploys, rollbacks, backups, worker restarts, config changes | every change listed | Operator; reviewer |
| Admin exception screen | Flutter (admin) | Permits and notifications waiting for a person | empty | Admin |

**Liveness and readiness are different questions.** F8 showed this: with MySQL stopped, `/up` still said 200 and `/api/health` said 503. A load balancer that only checks `/up` would keep sending users to an instance that cannot sign anyone in.

**Readiness keeps the gateway out of the request path.** The readiness check does not call the gateway. Delivery is asynchronous through the outbox, so a gateway outage shows as an overdue backlog, not as a failed API.

**`degraded` still answers 200 on purpose.** Requests still work, so the instance must stay in service, but a person must be told.

The readiness check is public and shows statuses, ages and counts only. It is rate-limited in nginx (1 per second per address, burst 5), not in the app, because the app's limiter needs the database.

## Incident walkthrough 1 – S2, found by the readiness signal but not acted on

**What happened.** From the first minute staging ran, every notification dispatch failed (defect S2: an absolute CA path became an invalid path). No notification was sent for the whole test session.

**What the signals showed, in order:**

1. `GET /api/health` → `degraded`. In detail: `scheduler_dispatch: stale` (no heartbeat, because the run crashed before writing it) and `outbox: backlog`, with the overdue count rising (2,140 at 23:03, 3,625 at 23:13).
2. `permit.jsonl` → one `exception.unhandled` per minute: `GuzzleHttp\Exception\InvalidArgumentException at CurlFactory.php`, then the scheduler's own "command failed".
3. Users would have noticed nothing in the API: every request worked.

**Why it went unnoticed for hours of testing.** Nothing turned "degraded" into a message. The signal was correct, but no one owned it. That makes this a monitoring gap, separate from the code defect.

**How it was found.** While checking the race results I searched the log for `exception.unhandled` and found 106 lines.

**Fix and verification.**
- Code fix S2.
- Deploy fix S3: the deploy had not restarted the scheduler, so the first deploy of the fix changed nothing. The readiness signal showed that within 3 minutes: still stale, backlog still growing.
- After both fixes, `scheduler_dispatch` returned to `ok` within one minute, and the backlog fell by 200 a minute.

Evidence: `evidence/week16/S2-dispatch-ca-path/`.

**Lessons.**
1. **Act on `degraded`, not just on 503.** The recommended alert:
   - page the operator when readiness is 503 for 1 minute
   - send a message when it is `degraded` for 10 minutes
   - send a message for any `exception.unhandled`
2. **Check the effect of a deploy, not only the HTTP status.** After a deploy, confirm `scheduler_*` is `ok` in the next 2 minutes. A 200 from `/api/health` can still be `degraded`.

## Incident walkthrough 2 – F8, database stopped (planned drill)

| Time (UTC) | Signal |
| --- | --- |
| 23:18:37 | MySQL stopped |
| 23:18:40 | `/up` 200 (misleading); `/api/health` 503 `not_ready`, `database: fail`; sign-in 500 in the safe error shape with a correlation ID; no SQL, paths or hostnames in any response |
| 23:18:58 | MySQL started (no app restart) |
| 23:19:01 | `/api/health` 200 (3 s after the start); sign-in 200 |

**Observation:** during the outage the readiness check took about 9 seconds to answer, because the MySQL connection attempt waits before failing. A monitor with a short timeout (for example 5 s) would record "no answer" rather than 503. Both should page. **Recommendation:** set the monitor timeout to 15 s, or lower the MySQL connect timeout for the readiness query.

Evidence: `evidence/week16/F8-database-down/`.

## Incident walkthrough 3 – F9, a PHP worker killed during load (planned drill)

- **Supervisor:** the loop in `workers.sh` logged the exit and had a new worker listening in 2.1 s.
- **Load:** the 60-second load run had 0 errors, with a short slowdown (slowest request 1.6 s).
- **Signals:** `ops.log` (`worker on 9102 exited; restarting`). There was nothing in nginx's error log and nothing visible to users.
- **Gap:** an operator would not know a worker crashed unless they read `ops.log`. Recommendation: count restarts and alert on more than 3 in 10 minutes.

Evidence: `evidence/week16/F9-worker-killed/`.

## Gaps that remain

- **No alerting system.** Every signal above must be looked at by a person. On a real host, a monitor would poll `/api/health` and tail the logs (for example Uptime Kuma or a cron job that sends a message).
- **No time-series metrics.** Response times exist in the nginx access log but are not graphed.
- **Log files grow without rotation** on staging.
