# Week 17 – Maintenance plan

Prepared by Claude Code (AI assistant) at the student's request. It covers how the Digital Permit Request System is kept running, safe and correct after the course: who does what, how often, and how changes are made.

## Roles

| Role | Who (recommended) | Responsible for |
| --- | --- | --- |
| System owner | Barangay official who approves permits | Decides what changes, accepts releases, owns the data (data controller) |
| Operator | Barangay staff member trained on the runbook, or the student | Daily checks, backups, restarts, reading alerts, requeueing failed notifications |
| Maintainer | The student or a contracted developer | Code changes, security updates, releases, rollbacks |
| Admin user | Barangay staff with the admin role | Exceptions screen; asks the operator for data fixes |

For a classroom prototype the student holds every role. Before real use, the owner must name a person for each.

## Routine work

| How often | Task | How | Done when |
| --- | --- | --- | --- |
| Every working day | Check health and alerts | Open `/api/health`; read `shared/logs/alerts.log` (or keep `watch.sh` running) | `ready`, or `degraded` for a known reason |
| Every working day | Clear exceptions | Admin opens the exceptions screen; fix the cause (for example a wrong email), then Requeue | The list is empty or each item has a note |
| Daily (automatic) | Database backup | `backup.sh daily` from a scheduled task, with `OFFSITE_DIR` set to a second device | A new file in `shared/backups` and the off-machine copy verified (`ops.log`) |
| Weekly | Look for unhandled exceptions and slow requests | `permit.jsonl` (`exception.unhandled`); nginx access log (`$request_time` over 1 s) | Each one explained or logged as an issue |
| Monthly | Restore test | Restore last night's backup into a scratch database; count permits and events | Counts match the source |
| Monthly | Security updates | Update the OS, PHP, nginx and MySQL patch versions; `composer audit`; `flutter pub outdated` | No known critical vulnerability left unpatched |
| Monthly | Trim logs | Rotate `permit.jsonl` and the nginx logs; keep 90 days | Disk use stable |
| Every 6 months | Rotate keys | New `APP_KEY` is **not** routine (it would make encrypted notes unreadable); rotate the gateway keys and the database password as in `docs/week14/12-key-lifecycle.md` | New keys in use, old ones removed |
| Yearly, or when the office changes | Review permit types and rules | Validity days, reminder days, SLA hours and senior approval in `permit_types` | Rules match the office's process |

## Making a change

1. **Issue:** write what is wrong or wanted, who asked, and how to see it.
2. **Branch:** create a branch from the main branch. Change the code, and add or update a test that fails without the change.
3. **Checks:** run these and require them to pass:
   - `php artisan test`
   - `flutter analyze`
   - `flutter test`
   - the API collection (`php api-tests/run-collection.php`)
4. **Review:** the maintainer, and someone else where possible, reads the diff. Run the secret scan.
5. **Release:** merge, tag, and deploy to staging with `deploy/staging/deploy.sh <tag>`. Follow the deployment checklist (`docs/week16/33-environments-and-deployment.md`). For a change users will notice, rerun the related UAT cards.
6. **Production:** the same tag, the same script, after the owner accepts.
7. **If it goes wrong:** follow the rollback plan (`docs/week16/34-rollback-and-recovery.md`): `rollback.sh` for code, or `rollback.sh --restore <backup>` for code and data.

## Known risks that maintenance must watch

| Risk | Signal | Action |
| --- | --- | --- |
| Scheduler stopped | `/api/health` → `scheduler_*` stale; alert after 10 min | Restart `run.sh scheduler`, then check why it stopped |
| Notifications piling up | `outbox: backlog` | Check the gateway, then the dispatch log; raise `GATEWAY_DISPATCH_BATCH` if it is volume |
| Database full or down | readiness 503 | Free space or restart MySQL; it recovers without an app restart (Week 16 F8) |
| Slow at peak times | p95 over 1.5 s in the nginx log | Add PHP workers (capacity was about 53 requests/s with 4) |
| Tables growing | Permit list slower; disk use | Paging (H1) and archiving rows older than N years, after the owner agrees on retention |
| Shared demo password | – | Must be replaced by per-user accounts before real use |

## Documents a maintainer needs

| Document | What it gives |
| --- | --- |
| `README.md` | How to set up, run, test and deploy |
| `docs/api/openapi.yaml` | Every API endpoint, its fields, roles and errors |
| `docs/adr/` | Why each design decision was made, and when to revisit it |
| `docs/09-known-limitations.md` | What is not done yet |
| `docs/week16/33-…`, `34-…` | Deployment checklist and rollback plan |
| `evidence/` | What was tested, when, and the results |
