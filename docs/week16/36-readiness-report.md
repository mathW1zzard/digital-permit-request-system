# Week 16 – Performance, reliability and deployment evaluation: readiness report

Prepared by Claude Code (AI assistant) at the student's request, from the evidence in `evidence/week16/`. Student: Joseph R. Dionisio, BSIT 3A.

## Recommendation

**Conditionally ready for a supervised trial with fictional data on a controlled staging host. Not ready for real users or real personal data.**

The conditions, all of which must be met before the Week 17 user acceptance test:

1. Run it from the staging setup (nginx, PHP workers, MySQL, `APP_DEBUG=false`), never from `php artisan serve`.
2. Someone named watches `/api/health` and the error log during the trial, because there is no automatic alerting yet.
3. Take a backup before the trial and copy it off the laptop.
4. Use only the fictional accounts and data.

Before any real use, the following must be added:
- alerting
- off-machine backups
- a real host with a public certificate
- per-user passwords
- a Linux / PHP-FPM deployment

## What was evaluated

| Area | Result | Evidence |
| --- | --- | --- |
| Workload models | W1 ordinary office (13 users, 4–10 s think time) and W2 peak deadline (46 users, 1–3 s), with pass criteria; W3 stress | `30-workload-models.md` |
| Performance, staging | W1: p95 119 ms, 0 errors. W2: 23.5 requests/s, p95 119 ms, 0 errors. **Both pass** | P1, P2 |
| Performance, development | W1: p95 3.6 s, 1.3 % errors. W2: 1.8 requests/s, p95 29.7 s, 4.2 % errors. **Both fail** | P4, P5 |
| Capacity | About 53 requests/s at saturation with 0 errors (p95 1.8 s); the PHP workers are the bottleneck | P6 |
| Truly parallel duplicates (open item H2 since Week 15) | Double endorsement 30/30 correct (200 + 409, one event each); same-key double submission 30/30 correct (201 + 200 replayed, one permit per key) | H2-parallel |
| Monitoring | New readiness check `/api/health` (database, scheduler heartbeats, outbox); liveness vs readiness shown in F8 | `32-monitoring-and-incidents.md`, HealthTest |
| Failure drills | Database stopped: safe 503 and 500, recovery 3 s after restart (F8). Worker killed under load: replaced in 2.1 s, 0 errors (F9) | F8, F9 |
| Environment comparison | 16 settings compared; two defects found only through the difference | `33-…`, E1 |
| Deployment | Scripted release folders, backup before every deploy, health gate; full deploy 5.4–5.6 min | `33-…`, ops.log |
| Rollback | Code and data rollback rehearsed: 20.6 s, data identical to the backup; roll forward 218 s | `34-…`, RB |
| Regression | PHPUnit 40/40 (was 31 in Week 15); Flutter analyze clean, widget tests 4/4 | `evidence/week16/T*` |

## Defects found in Week 16

| ID | Where | Found by | What | Fix | Verified |
| --- | --- | --- | --- | --- | --- |
| U1 | Flutter form (open from Week 15) | Widget test | After a failed submit the only message was a SnackBar that disappeared after 4 s | A persistent error panel above Submit, with the correlation ID | Widget test fails on the old code and passes on the new |
| S0 | `setup.sh` | First deploy | Paths with spaces in the staging `.env` were not quoted; `migrate` stopped | Quote them | Redeploy succeeded |
| S1 | nginx config | Prefill P0a: 79 × 504, 420 × 502 | Keep-alive connections to `php-cgi` (one connection per worker) stalled new requests | No upstream keepalive; `max_fails=3` | P0b: 1,200/1,200, 0 errors, as predicted |
| S2 | `NotificationDispatcher` | Readiness `degraded` and the log, noticed late | Absolute `GATEWAY_CA_FILE` became an invalid path; no notification was ever sent from staging | Absolute paths used as given; unit test | Dispatch sends 200 a run, 0 exceptions |
| S3 | `deploy.sh` | Checking the S2 fix | The scheduler was not restarted, so background jobs kept running the old release | Scheduler under a restart loop, stopped by deploy and rollback | Roll forward in the rehearsal: the new release's scheduler sent 200 at the next run |
| TD6 | `race.mjs` (test tool) | First H2 run | Looked for the reviewer in the permit detail, which does not include it; R1 never ran | Look it up in each reviewer's list | H2 run 3 |
| TD7 | `loadgen.mjs` (test tool) | P4 | Two requests recorded after 180 s although the timeout is 30 s (idle-socket timeout) | Not fixed; reported as recorded | – |

H2 run 2 got 429 on every request because one requester had already used its 60 requests that minute. That was the rate limit working, not a defect. The test now rotates 10 requesters.

## Interpretation

**Observed.**
- **Execution model:** the same application code met the peak with an eighth of the allowed response time on staging, and failed the ordinary day on the development server.
- **Bottleneck:** under stress the four PHP workers saturated at about 53 requests/s, while MySQL stayed under 20 % CPU.
- **Failures:** every injected failure (database down, worker killed, faulty data) was handled safely and recovered without data loss beyond what the plan states.
- **Defects:** all three real defects of the week (S1, S2, S3) were in the deployment itself, not in the application's request handling.

**Concluded.**
- **Deployment is now the biggest source of risk.** The application logic was tested hard in Weeks 13–15 and held up again under true concurrency (H2). The new code this week was configuration, scripts and process supervision, and that is where the defects were.
- **The monitoring design worked, but the monitoring practice did not.** The readiness check reported S2 correctly from the start; no one was alerted. A signal without an owner and an alert is half a control.
- **Treat a rollback as a tool, not a fix.** It was fast (20 s) and exact, but it brought back S2.

**Recommended (in order).**
1. Alerting on readiness `degraded`/503 and on `exception.unhandled`.
2. Off-machine backups and a restore test elsewhere.
3. A Linux deployment with PHP-FPM (Docker Compose), sized to the CPU cores.
4. A real host and certificate for UAT.
5. Build once, deploy the built folder (deploy under 1 minute).
6. An endurance run at W1 for several hours.
7. Paging on the permit list (H1).

## Limitations

- **One machine for everything:** server, database and load generator shared one laptop over loopback. Real network latency is absent, and the numbers are for this hardware only.
- **Single runs:** each measured run happened once; there are no repeats to show variance.
- **The staging host is the developer's laptop.** It was configured like a server, but it is not separate hardware, has no firewall rules, and uses the local development certificate.
- **Partly real-browser checked:** the Flutter Web build was served by staging and checked in Chrome, but only up to the sign-in screen. Reading the staging password was refused by the assistant's safety rules, so signed-in screens on staging come from the load runs and from the Week 14 screenshots.
- **The mock gateway answers instantly;** a real provider's slowness or outages were not tested this week (Week 13 and 15 tested the retry logic).
- **The workload numbers are assumptions** until the partner office confirms its peak.
- **Reviewer independence:** the work was produced and reviewed by the same AI assistant; the student and instructor are the independent reviewers.

This is a bounded classroom prototype, demonstrated with fictional data, and not ready for production.
