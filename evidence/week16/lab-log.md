# Week 16 lab log

Kept by Claude Code (AI assistant), working at the student's request on the student's laptop. Times are UTC (Asia/Manila is UTC+8). The student chose the second environment (nginx + 4 PHP workers + MySQL) and asked for push, tag and upload at the end.

| Time (UTC) | What happened |
| --- | --- |
| 2026-11-15 16:50–17:05 | Read the Week 16 requirements (Module 16 and the Project 32 guide). Created branch `week16-deployment` from `5962978`. Added `/api/health` (readiness) and its 6 tests. A first test version broke the shared test connection and 14 other tests; fixed by using a separate broken connection. Found the U1 cause with a widget test (the SnackBar disappears after 4 s) and fixed it. Commit `15aab32`. |
| 17:07–17:27 | Wrote the staging scripts and ran `setup.sh`: private MySQL on 3307, generated secrets (never printed), nginx and PHP settings. The first deploy of `5962978` stopped at `migrate`: unquoted paths with spaces in the staging `.env` (S0). Fixed, removed the partial release, redeployed (5 min 22 s, health 200). Seeded fictional users. Wrote the load generator and the models. Commit `3fc625a`, deployed to staging. |
| 17:34 | Smoke run on staging: 157 requests, 0 errors. |
| 17:35–17:40 | Prefill P0a: 486 of 1,200 failed (504, then 502). Diagnosed S1 from the nginx error log, wrote the hypothesis and prediction, removed the upstream keep-alive, reloaded nginx, recreated the staging database. P0b: 1,200/1,200, 0 errors, as predicted. |
| 17:40–17:48 | P1 (staging W1) pass; P2 (staging W2) pass. |
| 17:48–17:59 | Dev setup started; P3 dev prefill; P4 dev W1 fail. P5 (dev W2) started, then stopped when the student asked to pause; its partial output was deleted. The session then ended and every server stopped. |
| 22:45–22:59 | Resumed. Committed the S1 fix with a `.gitattributes` rule (LF for `.sh`) as `7a128b7`. Restarted staging and dev. P5 rerun: fail. Stopped dev. P6 stress with the per-user limit raised (logged), then restored. |
| 22:59–23:03 | H2 race test. Run 1: R2 30/30, R1 did not run (TD6). Fixed the reviewer lookup. Run 2: every request 429 (one requester had used its minute). Rotated 10 requesters. Run 3: R1 30/30, R2 30/30; database check 60/60, one event per permit. |
| 23:02–23:10 | Found 106 `exception.unhandled` lines (108 by 23:03): 30 from my 17:39 database reset, and the rest from S2 (dispatch failing every minute since the scheduler started, which readiness had been showing as `degraded`). Wrote the prediction, fixed and unit-tested it, made the batch size configurable, commit `26f1ce7`, deployed. |
| 23:10–23:18 | The fix had no effect: the scheduler kept running the old release (S3). Recorded it, put the scheduler under a restart loop, made deploy and rollback restart it, started the new scheduler. Dispatch sent 200 a minute with no exceptions. Commit `f2ed55f`. |
| 23:18–23:20 | F8 (database down) as predicted. F9 (worker killed under load): 0 errors instead of the predicted few 502s; explained. |
| 23:21–23:28 | Rollback rehearsal: backup, faulty change, `rollback.sh --restore` (20.6 s, counts identical), S2 back on the old release as predicted, roll forward (218 s), dispatch working on the new release. Commit `8054ac6` with all the evidence so far. |
| 23:28–23:33 | Collected the environment settings (E1). Opened the staging web app in the student's Chrome and captured the sign-in screen. Reading the staging password to sign in was refused by the assistant's safety rules, and Chrome then stopped answering screenshot requests, so no signed-in staging screenshots. |
| 23:33– | Wrote the workload models, result sheet, monitoring map, environment comparison and checklist, ADR-005, rollback plan and readiness report. PHPUnit 40/40 twice; Flutter analyze clean, tests 4/4. Copied the ops log (0 secret matches). Then: README and known limitations, commit, push, tag `week16-eval`, Drive upload. |

## Notes

- Failed runs (P0a, P4, P5, H2 runs 1–2) are kept with their explanations. The only deletion was the partial P5 from 17:59, stopped at the student's request and rerun in full.
- Nothing secret was printed or saved. The staging secrets are in `permit-staging/shared` (outside the repository); the load tool reads the password from an environment variable.
- The staging folder `permit-staging` is outside the repository on purpose: it holds generated secrets, database files and release copies.
