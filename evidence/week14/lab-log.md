# Week 14 lab log - Project 32 Digital Permit Request System

All Week 14 work below was done by Claude Code (AI assistant) at the student's request, unless a row says otherwise.

| Date and time (Asia/Manila) | Commit | What was done | Expected | Actual |
| --- | --- | --- | --- | --- |
| 2026-11-11 (afternoon) | 6b156f4 | Created branch `week14-security` from `week13-automation`. Read Module 14 and set the scope: password login, HTTPS, protected gateway messages, encryption at rest. | Plan | Plan written in `docs/week14/` |
| 2026-11-11 | (none yet) | First test run after adding login and the envelope. | 24 pass | **Failed:** 6 errors, "Payload key must be 32 bytes". The test key in `phpunit.xml` decoded to 31 bytes. Replaced it with a 32-byte test-only key. |
| 2026-11-11 | (none yet) | Rerun. | 24 pass | **23 of 24** reported once; three runs straight after passed 24 of 24. The failing test was not identified. |
| 2026-11-11 | (none yet) | Flutter widget test for the new sign-in screen. | Pass | **Failed:** the Sign in button sat below the 800×600 test window. Test now scrolls it into view; 2 of 2 pass. |
| 2026-11-11 | (none yet) | Created dev certificates (`php tls-proxy/make-dev-certs.php`), started API, gateway and proxy. Checked with curl. | HTTPS 200 | **Failed at first:** Git's curl (Schannel) refused the local CA because it has no revocation list. `openssl verify` showed the certificate is valid; used `--ssl-no-revoke` for curl only. |
| 2026-11-11 | a20d703 | Committed the Week 14 code. Secret check before commit: payload key, gateway key and demo password not in staged files. | No secrets | 0 matches |
| 2026-11-11 15:30-15:33 | a20d703 | Evidence run W01-W12. | See `README.md` | All 12 as predicted |
| 2026-11-11 15:33 | a20d703 | W11 finding: `php artisan test` had written its log lines into the live `permit.jsonl`. Fix: tests log to `permit-test.jsonl` (`PERMIT_LOG_PATH`). Recheck. | Live log unchanged by tests | Unchanged (134 lines before and after); tests 24 of 24 |
| 2026-11-11 | a20d703 | Tried to capture UI screenshots in Claude's browser. | Screenshots | **Not possible:** the browser refused the local certificate. Left U1-U6 for the student. |
| 2026-11-11 | a20d703 | Noted the PHP version on PATH is now 8.4.12 (Week 13 used 8.3.30). | Same as Week 13 | Different; all tests still pass |
| 2026-11-11 | 319b81f, d901b3f | The student could not start the gateway with the CMD `for /f` commands from the instructions: they fail in PowerShell and cut base64 keys at the trailing `=`. Added `mock-gateway/start.php`, which reads the keys from `.env`. | Gateway starts with the right keys | Started; tamper test 6 of 6 PASS |
| 2026-11-14 | d901b3f | Captured UI screenshots U1, U2, U6 and U7 in Claude's browser from the release build. | U1-U6 | U1, U2, U6 captured. U3-U5 not possible: they need the HTTPS API, which the browser refuses. U7 added: the safe error when the API is down. |
| 2026-11-15 19:29 | d901b3f | Source and configuration review (W13). | No hard-coded secrets, weak crypto, disabled TLS checks or secrets in logs | All passed. One pattern match was a fixed label (false positive). Finding F1: `APP_DEBUG=true` in development, recorded, not changed. |
| 2026-11-16 00:37 | week15-hardening | Captured U3, U4 and U5 in the student's Chrome through the Claude in Chrome extension (the student accepted the certificate warning). | U3–U5 | Captured. Submitting the New permit form failed in the browser (Week 15 defect D8), so the permits in U4 were created with `curl`. D8 is fixed on the same branch. |
