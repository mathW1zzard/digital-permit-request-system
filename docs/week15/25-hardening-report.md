# Week 15 – Integration testing and hardening report (IA6)

Prepared by Claude Code (AI assistant) at the student's request, from the runs in `evidence/week15/`. Student: Joseph R. Dionisio, BSIT 3A.

## Executive summary

The Week 14 build was tested end to end over HTTPS with a 48-request Postman collection, 30 PHPUnit tests, 3 Flutter tests and 7 failure-injection scenarios. The baseline run passed 40 of 48 and exposed six defects (D1–D6). Failure injection found a seventh (D7, no request size limit). All seven are fixed and retested: the collection now passes 48 of 48 (runs C2 and C3), every failure scenario behaves as predicted or the difference is explained, and the original workflows still work.

**Update, 2026-11-16 (after the `week15-ia6` tag).** Capturing the Week 14 screenshots in a real Chrome found an eighth defect, D8: the web form could not submit permits, because the `Idempotency-Key` header added for D3 was not allowed by the API's CORS settings. None of the Week 15 tests runs in a browser, so none caught it. It is fixed, has a PHPUnit test, and was retested in Chrome (form submit 201) and with the full collection (run C4, 48 of 48).

**Remaining risk is low for a classroom prototype and moderate for a real deployment.** Four items stay open on purpose: debug mode in the development settings (F1), no paging on the permit list (H1), true concurrency not being testable on the single-threaded development server (H2), and the New permit form showing no message when the API cannot be reached (U1). All four are planned for Week 16.

## What was tested

| Layer | Result | Evidence |
| --- | --- | --- |
| API collection, baseline (Week 14 build) | 40 / 48 | `C1-baseline` |
| API collection after fixes D1–D6 | 48 / 48 | `C2-after-fixes` |
| API collection after fix D7 (regression) | 48 / 48 | `C3-after-d7` |
| API collection after fix D8 (regression) | 48 / 48 | `C4-after-d8` |
| Web form submission in Chrome (D8) | Blocked before the fix; 201 after | `D8/` |
| PHPUnit | 30 / 30, twice; 31 / 31 after D8 | `01-tests-run1.txt`, `01-tests-run2.txt`, `D8/D8-04-phpunit.txt` |
| Flutter analyze and test | No issues; 3 / 3 | `02-flutter-analyze-and-test.txt` |
| Failure injection | F1–F6 as predicted (F2 with a different failure detail); F7 failed its prediction → D7; F7b as predicted | `F1`–`F7b`, `docs/week15/22-failure-injection-log.md` |

The requirements behind these tests are traced in `21-traceability-matrix.md` (24 requirements, each with a negative or boundary test). Two early runs (`C0-environment-not-ready`, `C0b-runner-defect`) failed because of problems in the test tools, not the API. They are kept and explained in `24-defect-record.md`.

## Defects and fixes

| ID | Weakness | Fix | Proof |
| --- | --- | --- | --- |
| D1 | Server accepted start dates more than 365 days ahead; the form did not | Server limit from `permit.max_days_ahead` | T23 422 |
| D2 | Unknown paths and wrong methods had no correlation ID | Correlation and header middleware made global | T35, T36 |
| D3 | A resent submission created a duplicate permit | `Idempotency-Key` with a unique database index; the Flutter form sends one key per content | T39–T41, F5 |
| D4 | No browser security headers | `SecurityHeaders` middleware | T42 |
| D5 | `X-Powered-By` revealed the PHP version | Header removed | T43 |
| D6 | No rate limit on signed-in calls | 60 per minute per user | T45, F6 |
| D7 | No request size limit (this PHP allows 2 GB) | 1 MB limit, 413 | F7b |
| D8 | Web form could not submit: CORS did not allow `Idempotency-Key` | Header allowed; `Idempotent-Replayed` exposed | Chrome form submit 201; HardeningTest D8 |

Each fix has a PHPUnit test in `backend/tests/Feature/HardeningTest.php`, so it cannot silently come back.

## Interpretation

**Observed.** The happy path, the authorization rules and the error shape were already solid in Week 14: every authorization test from the wrong role failed as it should in the baseline. The failures were all at the edges: what happens when a request is repeated, too frequent, too large, too far in the future, or sent to a path that does not exist. The recovery paths built in Week 13 held up under injected failures. A slow gateway was retried without a duplicate delivery (F1). A stopped gateway was handed to a person after four attempts and delivered once after the restart (F2). A missing database gave a safe 500 and recovered without a restart (F3).

**Concluded.** Testing only the expected flows would have missed every one of the first seven defects. Two of them were found only because the prediction was written down first. F7 was expected to pass because of a server default, and the prediction failing showed that the app depended on a setting it did not control. The idempotency fix (D3) matters most for users: on a slow connection, pressing Submit twice no longer creates two permits. D8 is the lesson of this week: the D3 fix was tested at every layer except the one users have, the browser, and that is exactly where it broke. A fix that changes what the client sends has to be checked in a real browser.

**Recommended.**
1. In the Week 16 deployment, set `APP_DEBUG=false` and a `post_max_size` that matches the 1 MB application limit, so both layers agree.
2. Rerun F4 and F5 on a multi-worker server (for example PHP-FPM behind nginx) to test truly parallel requests (H2).
3. Add a browser end-to-end test (sign in and submit a permit in headless Chrome) and run it in CI on every push, together with the collection (which needs only PHP and the dev CA and exits non-zero on any failure).
4. Show an error on the New permit form when the API cannot be reached (U1).
5. Add paging to `GET /permits` before the data grows (H1).

## Limitations of this testing

- F4 and F5 were queued by the single-threaded development server, so the parallel-collision code path was not exercised (H2).
- The rate limit was tested for one user over one minute, not as a distributed attack. Limits are kept in the application cache, which is per server.
- The mock gateway stands in for a real provider. Real provider failures (partial outages, slow DNS) were not tested.
- The Flutter UI was driven in a real browser only once, after the tag, through the Claude in Chrome extension (the student accepted the certificate warning). That check found D8. There is still no automated browser test.
- All data is fictional and the volume is tiny. Performance is Week 16's subject.
