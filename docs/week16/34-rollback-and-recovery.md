# Week 16 – Rollback and recovery plan

Prepared by Claude Code (AI assistant) at the student's request. Rehearsal evidence: `evidence/week16/RB-rollback-rehearsal/`.

## When to roll back

Roll back if any of these happen within the first 30 minutes after a deploy, and a fix cannot be deployed within 15 minutes:

| Trigger | Seen in |
| --- | --- |
| Deploy script stops at the health gate (not 200) | deploy output, `ops.log` |
| `/api/health` 503 `not_ready` for 1 minute | monitor |
| `scheduler_*` stale or the outbox backlog growing for 10 minutes after the deploy | readiness check |
| Error rate over 1 % in the nginx access log, or new `exception.unhandled` lines | logs |
| A user-facing workflow is broken (cannot submit, endorse or approve) | smoke run, reports |
| Data written wrongly by the new release | admin, database check |

The decision is the operator's. For a real office, the system owner should confirm it. Write the decision and the time in `ops.log`.

## Two kinds of rollback

| | A. Code only | B. Code and data |
| --- | --- | --- |
| Command | `deploy/staging/rollback.sh` | `deploy/staging/rollback.sh --restore <backup file>` |
| What it does | Points `current` at the previous release, restarts workers and scheduler, waits for health 200 | First backs up the current state, then restores the database from the file, then does A |
| Use when | The new code is faulty but its migrations only added things (the old code still works on the new schema) | A migration or the new code changed data in a way the old code cannot use, or wrote wrong data |
| Data loss | None | **Every write after the backup is lost.** The pre-restore backup keeps it for manual recovery |
| Measured time | ~20 s (the code switch and health wait inside B) | 20.6 s in the rehearsal (2,978 permits, 7,300 events) |

**Keeping rollback A possible.** Migrations must be backward compatible. Add columns and tables, and remove old ones only in a later release. If a release breaks this rule, plan rollback B before deploying and accept its recovery point.

**Rollback re-opens fixed defects.** The rehearsal proved this. With release `3fc625a` back in place, defect S2 came back within a minute (notification dispatch failed again). A rollback is a step back to a known state, not to a good one. Roll forward with a fix as soon as one exists.

## Recovery point and time (measured on staging)

| Event | Recovery point (data that can be lost) | Recovery time |
| --- | --- | --- |
| Faulty release, code rollback (A) | none | ~20 s |
| Faulty data change, restore (B) | writes since the last backup; backups are taken at every deploy and by `backup.sh` | 20.6 s, plus the time to decide |
| Database process stopped (F8) | none (MySQL recovers its own files) | 3 s after MySQL starts; no app restart |
| PHP worker crashed (F9) | the request it was serving, if any | 2.1 s, automatic |
| Disk lost | **everything since the last copy off the disk.** Backups are on the same disk today | not tested |

## Rehearsal (2026-11-15, 23:21–23:28 UTC)

The prediction was written before the run (`00-plan-and-prediction.txt`).

| Step | Result | As predicted? |
| --- | --- | --- |
| 1. Backup `rehearsal` (3.7 MB) | permits 2,978; under review 2,443; rejected 0; events 7,300 | – |
| 2. Faulty change: 25 permits under review set to rejected | rejected 25; under review 2,418 | – |
| 3. `rollback.sh --restore` | pre-restore backup taken; database restored; `current` moved to `3fc625a`; health 200; **20.6 s** | Yes (under 1 minute) |
| 4. Check | permits 2,978; under review 2,443; rejected 0; events 7,300: **identical to the backup**; serving `3fc625a` | Yes |
| 4b. While `3fc625a` was live | dispatch failed at 23:22:02 with the S2 exception | Yes (rollback re-opens fixed defects) |
| 5. Roll forward `deploy.sh HEAD --skip-web` | health 200; **218 s**, of which composer took 198 s | Yes (about 4 minutes) |
| 5b. After the roll forward | scheduler restarted from the new release (S3 fix); next dispatch sent 200; no new exceptions | Yes |

**No rows differed after the restore.** The scheduler did not write between the backup and the restore: the backup was at 23:21:23, the restore at 23:21:32, and dispatch runs at :12 past each minute.

## Improvements before real use

1. Copy each backup off the machine, and test a restore on another machine once a month.
2. Make `composer install` part of the build, not the deploy. Build once, copy the folder. That would cut the roll-forward time from about 4 minutes to well under a minute.
3. Put the site in maintenance mode (`php artisan down`) during a restore, so no writes land between the restore and the switch.
