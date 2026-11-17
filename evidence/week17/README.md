# Week 17 evidence index: acceptance testing, documentation and evaluation

**Who produced this:** Claude Code (AI assistant), at the student's request on the student's laptop. It:
- wrote the UAT kit and the UAT runner
- **played every tester role** (simulated round; no real testers were available)
- ran the rounds, fixed the findings
- wrote the OpenAPI file and the reports

The outputs are unedited; the UAT runner replaces tokens with `[redacted]` and the ops log has its paths shortened. See `lab-log.md`.

**Build:** branch `week17-uat`, from Week 16's `de82fb7`. Staging releases `5773932` (round 1), `07d9154` (round 2) and `57fcd31` (round 3). Times are UTC.

Reports in `docs/week17/`:
- `40` UAT plan
- `41` scenario cards
- `42` forms
- `43` UAT report
- `45` final architecture and recommendation
- `46` maintenance plan

API documentation: `docs/api/openapi.yaml`.

## UAT

| Folder | Release | Result | Note |
| --- | --- | --- | --- |
| `UAT-round1` | `5773932` | 7 / 12 | Entry checks (`00-health-before.json`, `00-smoke/`), all requests (`results.json`), per-card table (`results.md`), findings written before any fix (`01-findings.txt`) |
| `UAT-round2-S4-stale-workers` | `07d9154` | 8 / 12 | The fixes were deployed but the workers still served the old code (defect S4). Kept to show how S4 was found |
| `UAT-round3` | `57fcd31` | **12 / 12** | After the S4 fix |
| `UAT-signoff.md` | – | Unsigned | Recommendation: accepted with conditions. Only the acceptance authority may decide |

## Defect S4 (deploy kept serving the old code)

| File | What it shows |
| --- | --- |
| `S4-opcache/01-before.txt` | UAT-03 fails on release `07d9154` although its files contain the fix |
| `S4-opcache/02-after-full-stop.txt` | After stopping every worker and starting them again, UAT-03 passes: the cause is the shared code cache |
| `S4-opcache/03-rollback-direction.txt` | After the fix, a rollback really serves the old release (UAT-03 fails, as it should there) |
| `S4-opcache/04-deploy-direction.txt` | Deploying forward really serves the new release (UAT-03 passes) |

## Other evidence

| File | What |
| --- | --- |
| `T1-phpunit-run1.txt`, `run2` | PHPUnit 45 / 45 both times (40 in Week 16, plus `UatFindingsTest` 5) |
| `T2-flutter-analyze-and-test.txt` | No analyzer issues; 6 / 6 tests (two new: list reload after a 409; timeline names and notes) |
| `T3-openapi-check.txt` | OpenAPI 3.1: 16 operations, exactly the 16 API routes in the code; all references resolve |
| `E2-ops-log.txt` | Every staging deploy, rollback, backup, worker restart and UAT operator step of Week 17. Checked for secrets: 0 matches |

## Reading the files

- **Expected results:** the "Pass when" conditions on each card are the expected results. `results.json` records the actual value of every check.
- **Not assessed:** ease of use (AC-U7) was not assessed. A simulated tester cannot give an honest rating, so none was invented.
- **Defects kept:** the test-tool defect TD8 (round 1, UAT-12) and the `--skip-web` mistake before round 3 are described in the UAT report and the lab log.
- **The Week 16 rollback rehearsal is affected by S4.** It proved the database restore and the scheduler's code switch, but not the web workers' switch. A correction is added to `docs/week16/34-rollback-and-recovery.md`, and the check was repeated here in both directions.
