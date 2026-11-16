# Week 16 evidence index: performance, reliability and deployment evaluation

**Who produced this:** Claude Code (AI assistant) did all of the following at the student's request, on the student's laptop:
- built the staging environment and the measurement tools
- ran every measurement, drill and rehearsal
- fixed the defects
- wrote the reports

The outputs are unedited. The load tool never records passwords or tokens, and staging paths in the ops log are shortened to `<staging>` and `<repo>`. See `lab-log.md`.

**Build:** branch `week16-deployment`, from Week 15's final commit `5962978`. Tagged commit: `ab52099` (tag `week16-eval`). Times are UTC.
- **Staging:** release folders exported from commits `3fc625a`, `26f1ce7` and `f2ed55f`.
- **Environment:** PHP 8.4.12, Laravel 13.39, MySQL 8.4.3, nginx 1.28.2, Node 22, Flutter 3.47.8.
- **Machine:** Windows 11, 8 logical cores, 7.2 GB RAM.

Reports: `docs/week16/30–34`, `36` and `docs/adr/ADR-005-staging-deployment.md`.

## Measurements (`docs/week16/31-performance-results.md`)

Each run folder has these files:
- `00-run-info.txt`
- `requests.csv` (every request)
- `timeline.csv` (10 s windows)
- `summary.txt` / `summary.json`
- `resources.csv` (CPU and memory every 2 s; W1, W2 and W3 runs only)

| Folder | Environment | Model | Result |
| --- | --- | --- | --- |
| `P0a-staging-prefill-S1-keepalive` | staging | W0 prefill | **Failed:** 79 × 504, 420 × 502, 63 timeouts → defect S1. Has the diagnosis and the prediction written before the fix |
| `P0b-staging-prefill` | staging, after S1 fix | W0 | 1,200 × 201, 0 errors, 27.6/s, as predicted |
| `P1-staging-W1-ordinary` | staging | W1 | **Pass:** p95 119 ms, 0 errors |
| `P2-staging-W2-peak` | staging | W2 | **Pass:** 23.5/s, p95 119 ms, 0 errors |
| `P3-dev-prefill` | dev | W0 | 1.8/s, 589 permits in the 5-minute cap |
| `P4-dev-W1-ordinary` | dev | W1 | **Fail:** p95 3.6 s, 1.3 % errors (started 12 s after P3; see the limitations) |
| `P5-dev-W2-peak` | dev | W2 | **Fail:** 1.8/s, p95 29.7 s, 4.2 % errors |
| `P6-staging-W3-stress` | staging, per-user limit raised for this run only | W3 | 53.3/s, 0 errors, p95 1.8 s: capacity |

`P5` was started once at 17:59 and stopped at the student's request ("pause"). That partial run was deleted and the folder holds the complete rerun from 22:51. This is the only evidence that was removed, and it is recorded in the lab log.

## Reliability and deployment

| Folder | What | Result |
| --- | --- | --- |
| `H2-parallel` | Open item H2: truly parallel double endorsement (R1) and same-key double submission (R2), 30 trials each | Both 30/30 correct; the database check shows 60 keys → 60 permits and exactly one `endorsed` event per permit. Run 1 had TD6 (R1 did not run); run 2 hit the rate limit (429). Both are kept |
| `F8-database-down` | MySQL stopped and started | `/up` 200 while `/api/health` was 503; safe 500; recovery 3 s after start |
| `F9-worker-killed` | One `php-cgi` killed during load | Replaced in 2.1 s; 0 errors (I predicted a few 502s; the difference is explained in `04-result.txt`) |
| `S2-dispatch-ca-path` | Defect S2 (notifications never sent from staging) and S3 (deploy did not restart the scheduler) | Prediction, exception log, health before, S3 note, drain after the fix |
| `RB-rollback-rehearsal` | Faulty data change → code and data rollback → roll forward | 20.6 s; counts identical to the backup; S2 returned on the old release, as predicted; roll forward 218 s |
| `E1-environment-comparison` | Non-secret settings read from both environments | `APP_DEBUG` false, OPcache on, `post_max_size` 2 M, MySQL, cached config on staging |
| `E2-staging-ops-log` | Every staging deploy, rollback, backup, worker restart and config change | Checked for secrets: 0 matches |
| `T1-phpunit-run1.txt`, `run2` | PHPUnit | 40/40 both times |
| `T2-flutter-analyze-and-test.txt` | Flutter | No issues; 4/4 |
| `UI/` | Staging web app in Chrome | Sign-in screen served by staging at `https://localhost:8444`. Signed-in screens were not captured: reading the staging password was refused by the assistant's safety rules, and Chrome then stopped answering screenshot requests |

## Reading the files

- **Expected and actual results:** every drill has its prediction in a file written before the run.
- **Failed runs and test-tool defects are kept.** Test-tool defects are marked TD6 and TD7.
- **TD7:** `requests.csv` of P4 holds two requests recorded at 180.6 s. The tool's 30 s timeout measures idle time on the socket, not total time.
- **Run P6** changed `API_RATE_PER_MINUTE` in the live staging release and restored it straight after. Both changes are in `E2-staging-ops-log/ops.log` (22:56:06 and 22:59:35).
- **Database resets:** the staging database was recreated once (17:39:08) before the measured runs. That reset caused the 30 `QueryException` log lines at 17:39:03–04, because the scheduler was running.
- **Secret scan:** the staging and development passwords, keys and database passwords were each found 0 times in the evidence, docs and code changes.
