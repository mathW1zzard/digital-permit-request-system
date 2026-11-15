# Week 15 lab log

Kept by Claude Code (AI assistant), working at the student's request on the student's laptop. Times are Asia/Manila (UTC+8), 2026-11-15.

| Time | What happened |
| --- | --- |
| Before 20:35 | Created branch `week15-hardening` from `week14-sa8`. Wrote the test strategy and traceability matrix. The student chose a Postman collection with a PHP runner (Newman could not be installed from npm here) and asked for push, tag and upload at the end. |
| Before 20:35 | Wrote `api-tests/build-collection.php` (48 requests in 8 folders, Postman v2.1) and `run-collection.php`. While generating it, found TD3 (wrong URLs from a reused variable name) and fixed it before any run. |
| 20:35 | Run C0: 48 of 48 failed with 502. The runner started before the API answered; the readiness check accepted the proxy's 502. Fixed the check to wait for `/up` 200 (TD1). |
| 20:37 | Run C0b: 11 passed; every signed-in call got 401. The runner's substitution function captured the variables by value, so saved tokens were never used (TD2). Fixed. |
| 20:37 | Run C1 baseline on the Week 14 build: 40 of 48. Failures traced to six API defects, D1–D6. Ranked them in the hardening backlog. |
| 20:38–20:43 | Fixed D1–D6 with a PHPUnit test for each, added the Flutter idempotency key and its widget test. Commit `24af5dc`. |
| 20:43 | Run C2: 48 of 48. |
| 20:44–20:47 | Failure scenarios F1–F4 ran, each with a written prediction. F1–F3 as predicted (F2 with the 502 detail explained in the log). F4's requests gave 200 and 409. |
| 20:47–22:45 | The script hung: its `wait` also waited for the gateway restarted in F2 (TD4). Noticed when the student asked whether the work was finished. |
| 22:45 | Stopped the script and resumed from F4's event count with `wait` limited to the request processes. The first count query failed (TD5) and was re-run: 1 `endorsed` event. |
| 22:46–22:47 | F5 and F6 as predicted. F7 did **not** match: 422 instead of 413, because this PHP allows 2 GB bodies. Recorded as defect D7. |
| 22:47–22:48 | Added `LimitRequestSize` (1 MB) and a HardeningTest for it; PHPUnit 30 of 30. F7b: 413 as predicted, nothing saved. |
| 22:48 | Run C3 regression: 48 of 48 (T27's 100,000-character purpose is still 422, not 413). |
| 22:50 | Environment, PHPUnit (twice, 30 of 30), Flutter analyze (no issues) and Flutter test (3 of 3) saved. Secret scan of evidence and changed code: 0 hits. |
| Then | Wrote the failure-injection log and the hardening report; updated the README, validation rules, API contract and known limitations. Committed, pushed, tagged `week15-ia6`, and uploaded the evidence to Drive. |

## 2026-11-16 (Asia/Manila), after the tag

| Time | What happened |
| --- | --- |
| 00:15–00:36 | Started the servers and a fresh release web build to capture the Week 14 screenshots U3–U5 in the student's Chrome through the Claude in Chrome extension. The extension disconnected twice and the servers stopped once (the laptop slept); restarted them. The student accepted the certificate warning in the extension's tab. |
| 00:37–00:40 | Captured U3 (wrong password) and U5 (session ended). The New permit form could not submit: Chrome blocked the POST. The preflight did not allow `Idempotency-Key` (defect D8). For U4, created two fictional permits with `curl` and captured the list. |
| 00:41–00:44 | With the student's approval, fixed D8 in `config/cors.php` and added HardeningTest D8, which reads the header names from the Flutter client. It failed without the fix and passed with it. Form submit in Chrome: 201. |
| 00:45 | Run C4: 48 of 48. PHPUnit 31 of 31. Also fixed the collection command in the project README, where `` in `api-testsun-collection.php` had been written as a carriage return. |
| Then | Recorded D8 in the defect record, backlog, traceability matrix and report; committed and pushed (the `week15-ia6` tag was not moved), and uploaded the new files to Drive. |

## Notes

- Nothing was deleted from the evidence. Failed runs (C0, C0b, C1, F7) are kept with explanations.
- Real passwords, keys and tokens were never written into the collection, results, logs or this repository.
- Concurrency could not be tested truly in parallel on `php artisan serve` (open item H2).
