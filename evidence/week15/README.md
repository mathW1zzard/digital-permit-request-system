# Week 15 evidence index (IA6 – Integration Testing and Security Hardening)

**Who produced this:** Claude Code (AI assistant) wrote the test collection, runner and failure scripts, ran every test and scenario, fixed the defects, and wrote the reports, all at the student's request on the student's laptop. The outputs are unedited, except that the runner replaces access tokens with `[redacted]` before saving. See `lab-log.md`.

**Build:** branch `week15-hardening`. Fixes D1–D6 are in commit `24af5dc`; fix D7 and the reports are in `18e198e`. Tagged commit: `18e198e` (tag `week15-ia6`). Fix D8 came after the tag, on 2026-11-16, in commit `d566854` on the same branch (see below); the tag was not moved. Environment: `00-environment.txt` (PHP 8.4.12, Laravel 13.39.0, Node 22, Flutter 3.47.8, Windows 11). Every collection run used the HTTPS proxy at `https://localhost:8443/api` and started from `php artisan migrate:fresh --seed`.

## API collection runs (`api-tests/project32.postman_collection.json`, 48 requests)

| Run | When (UTC) | Build | Result | Why it is kept |
| --- | --- | --- | --- | --- |
| `C0-environment-not-ready` | 2026-11-15 12:35 | Week 14 | 0 / 48 (all 502) | Test-tool defect TD1: started before the API was ready |
| `C0b-runner-defect` | 12:37 | Week 14 | 11 / 48 (401 on signed-in calls) | Test-tool defect TD2 in the runner |
| `C1-baseline` | 12:37 | Week 14 | **40 / 48** | The real baseline. Failures T23, T35, T36, T40–T43, T45 → defects D1–D6 |
| `C2-after-fixes` | 12:43 | Fixes D1–D6 | **48 / 48** | Retest |
| `C3-after-d7` | 14:48 | Fixes D1–D7 | **48 / 48** | Regression after the D7 fix |
| `C4-after-d8` | 2026-11-15 16:45 | Fixes D1–D8 | **48 / 48** | Regression after the D8 fix |

Each folder has `summary.txt` (one line per request) and `results.json` (status, headers and redacted body for every request, with each check's result).

## Failure injection (predicted first, in each folder's `00-prediction.txt`)

| ID | Failure | Expected | Actual | Result |
| --- | --- | --- | --- | --- |
| F1 | Gateway slower than the timeout | Retried; one delivery; retry `duplicate_ignored` | As expected | Pass |
| F2 | Gateway stopped | Manual action after 4 attempts; sent once after restart and Requeue | As expected; failure detail `gateway_http_502` instead of the predicted `gateway_unreachable` (the proxy stayed up) | Pass, difference explained |
| F3 | Database file missing | Safe 500 with correlation ID; class-only log; recovers | As expected; 0 leak matches | Pass |
| F4 | Two endorsements at once | 200 and 409; one event | 200, 409; 1 event | Pass (queued, not truly parallel) |
| F5 | Same submission and key twice at once | One permit; 201 and replayed 200 | Permits 3 → 4; 201 and 200 `replayed=true`, same reference | Pass (queued, not truly parallel) |
| F6 | 65 requests in a minute | 60 × 200, then 429 with `Retry-After` | 60 × 200, 5 × 429 from request 61; other user 200 | Pass |
| F7 | 9 MB body | 413 | **422**, after the whole body was parsed | **Fail → defect D7** |
| F7b | 9 MB body after the D7 fix | 413 | 413 `http_413`, nothing saved | Pass |

Details and the reasons for each difference: `docs/week15/22-failure-injection-log.md`.

## D8: web form blocked by CORS (found after the tag)

Found on 2026-11-16 while capturing the Week 14 screenshots U3–U5 in the student's Chrome through the Claude in Chrome extension. The student accepted the certificate warning for https://localhost:8443 themselves.

| File | What it shows |
| --- | --- |
| `D8/D8-01-browser-before-fix.txt` | The form's POST blocked by Chrome; the preflight allowed 4 headers, not `Idempotency-Key`; no permit created |
| `D8/D8-02-preflight-after-fix.txt` | The preflight now allows all 5 headers the client sends |
| `D8/D8-03-form-submit-after-fix.jpg` | Form submitted in Chrome: permit `PRM-20261115-VVNUG` created and opened |
| `D8/D8-04-phpunit.txt` | PHPUnit 31 of 31, including HardeningTest D8 |

The D8 test failed when the fix was removed and passed with it. Run `C4-after-d8` is the full regression.

## Test runs

- `01-tests-run1.txt`, `01-tests-run2.txt`: PHPUnit, 30 of 30 passed both times (143 assertions), after the D7 fix.
- `02-flutter-analyze-and-test.txt`: no analyzer issues; 3 of 3 widget tests passed.

## Reading the files

- **F4:** `03-event-count.txt` was re-run after the first count query failed (test-script defect TD5). The F4 requests themselves (`02a`, `02b`) are the original ones.
- **F4–F7 timing:** the script hung after F4 (test-script defect TD4). It was stopped and resumed; `F4/00-prediction.txt` carries a note with the time.
- **F7 and F7b `01-response.txt`:** the script keeps at most 1,500 bytes of each response as a safety cap. Both responses were shorter (861 and 565 bytes), so they are complete. The error does not echo the 9 MB input.
- Evidence `curl` calls use `--ssl-no-revoke` because Windows' TLS library cannot check revocation for a local CA. The certificate is still verified against `certs/dev-ca.pem`.
- Secret scan before commit: the demo password, the gateway keys, `APP_KEY` and token patterns were each found 0 times in the evidence, docs and changed code.

## Related documents (`docs/week15/`)

`20-test-strategy.md`, `21-traceability-matrix.md` (Activity 1), `22-failure-injection-log.md`, `23-hardening-backlog.md` (Activity 2), `24-defect-record.md`, `25-hardening-report.md` (summary, interpretation and limitations).

## UI evidence

No new screens were added in Week 15. The only client change is invisible: the permit form sends an `Idempotency-Key`, which the widget test checks. The Week 14 screenshots still apply. U3–U5 in `evidence/week14/UI/` were captured on 2026-11-16 in the student's Chrome; that is how D8 was found.
