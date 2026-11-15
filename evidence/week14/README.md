# Week 14 evidence index (SA8 / IA5 – Secure Data Exchange and Encryption)

**Who produced this:** Claude Code (AI assistant) ran W01–W13, captured the UI screenshots U1–U7, and wrote the review and interpretation, all at the student's request on the student's laptop. The outputs are unedited, except that access tokens are replaced with `[redacted]` before saving. See `lab-log.md`.

**Build:** commit `a20d703`, branch `week14-security`. Environment: `00-environment.txt` (PHP 8.4.12, Laravel 13.39.0, libsodium 1.0.20, Node 22). Tagged commit: `00f4ac9` (tag `week14-sa8`).

**Run:** W01–W12 on 2026-11-11, 15:30–15:33 Asia/Manila, in one pass from `migrate:fresh --seed`, through the HTTPS proxy.

| ID | Criterion | Expected | Actual | Evidence files | Result |
| --- | --- | --- | --- | --- | --- |
| W01 | Sign-in, stored secrets | Token with expiry; bcrypt hashes; token stored as a hash | Token expires in 60 min; `$2y$12$` hashes; 64-character token hash | W01\01-03 | Pass |
| W02 | No account enumeration | Same 401 for wrong password and unknown email | Identical `invalid_credentials` message | W02\01-02 | Pass |
| W03 | Login throttling | 6th attempt and the right password get 429 | As expected | W03\01 | Pass |
| W04 | Unauthenticated and unauthorized | 401, 401, 401, 403 | As expected; the old demo header no longer works | W04\01-04 | Pass |
| W05 | Expired token | 401 after expiry | 200 before, 401 after | W05\01-03 | Pass |
| W06 | Revoked token | 401 after sign-out | As expected | W06\01-02 | Pass |
| W07 | Transport security | 426 on HTTP; HSTS and `no-store` on HTTPS; untrusted CA refused | As expected (curl exit 60) | W07\01-04 | Pass |
| W08 | Message tampering and replay | 200, 409, 400, 400, 400, 400 | All six as expected | W08\01-02 | Pass |
| W09 | Controlled failure: untrusted gateway certificate | 1 attempt, `gateway_tls_failed`, no retry; sent after the fix | As expected | W09\01-08 | Pass |
| W10 | Encryption at rest | Ciphertext in the database; plain text to the owner | As expected | W10\01-03 | Pass |
| W11 | Secret scan | No secrets in Git, the log or the evidence | 0 everywhere; test-log finding fixed and rechecked | W11\01-03 | Pass after fix |
| W12 | Week 13 regression | Approval path sent; 422 still goes to a person | As expected | W12\01-08 | Pass |
| W13 | Source and configuration review (Activity 2) | No hard-coded secrets, weak crypto, disabled TLS checks or secrets in logs | All checks passed; finding F1: `APP_DEBUG=true` in development (see `docs/week14/15-secure-exchange-review.md`) | W13\01 | Pass, 1 finding recorded |

Also: `01-tests-run1.txt` and `01-tests-run2.txt` (24 of 24 passed both times), `02-flutter-analyze-and-test.txt` (no issues; 2 of 2 passed).

## Reading the files

- `W08\02-gateway-received.jsonl` holds two runs of the tamper test: one at 15:29 while checking the setup, and the evidence run at 15:31. Both gave the same six results.
- `W12\08-gateway-received.jsonl` also contains the W09 and W10 deliveries, because the gateway log was not cleared between those scenarios.
- `W11\02-auth-log-lines.jsonl` contains 19 lines from a test run (`"channel":"testing"`). That is the defect found in W11, kept as it was found. The 22 live lines are 12 sign-ins, 7 failed attempts, 2 throttled attempts and 1 sign-out, which match W01–W06.
- Evidence `curl` calls use `--ssl-no-revoke` because Windows' TLS library cannot check revocation for a local CA. The certificate is still verified.

## UI screenshots (`UI\`)

| # | State | File | Status |
| --- | --- | --- | --- |
| U1 | Sign-in screen with the password field (1280 px) | `U1-signin-desktop.jpg` | Captured by Claude, 2026-11-14 |
| U2 | Field validation: both fields empty (375 px) | `U2-validation-narrow.jpg` | Captured by Claude |
| U3 | Wrong password: "Email or password is incorrect." | `U3-wrong-password.jpg` | Captured by Claude, 2026-11-16 |
| U4 | Signed in over HTTPS, permit list with one permit open | `U4-signed-in.jpg` | Captured by Claude, 2026-11-16 (see note) |
| U5 | Session ended after the token expired | `U5-session-ended.jpg` | Captured by Claude, 2026-11-16 |
| U6 | Narrow width, 375 px | `U6-signin-375px.jpg` | Captured by Claude |
| U7 | API not running: safe "Cannot reach the server" message (extra) | `U7-api-down-network-error.jpg` | Captured by Claude. The console showed `ERR_CONNECTION_REFUSED`: the API and proxy were stopped. |

**How U3–U5 were captured (2026-11-16).** Claude's built-in browser refuses the local certificate, so Claude used the Claude in Chrome extension in the student's Chrome. The student accepted the certificate warning for https://localhost:8443 themselves; Claude did not bypass it. The app was the release web build (`week15-hardening` branch) served at http://localhost:5000, calling the API through the HTTPS proxy, from a freshly seeded database. U5: after signing in, all access tokens were expired with `php artisan tinker` and Refresh was pressed. The password stayed hidden in every screenshot.

**Note on U4.** The two permits in the list were created through the API with `curl` as the requester, not through the app's form. Submitting the form in the browser failed: the Week 15 client sends an `Idempotency-Key` header that the API's CORS settings do not allow, so Chrome blocked the request. This is defect D8, recorded in the Week 15 defect record.

## Interpretation

Written by Claude Code at the student's request.

**What was observed:** all twelve scenarios matched their predictions, and the source and configuration review (W13) passed with one recorded finding (debug mode on in development). The test-log defect (W11) was found during the run, fixed and rechecked. One test failure seen while building could not be reproduced. Three of the seven UI states are still to be captured.

**What it shows:** the API now knows who is calling, and tokens stop working when they expire or the user signs out. Traffic is encrypted and plain HTTP is refused. A message to the gateway cannot be read on the way, changed without detection, or replayed. Free text in the database is unreadable without `APP_KEY`.

**Limits of this evidence:** both systems run on one laptop with a self-made certificate. Keys sit in `.env`, and the demo accounts share one password. The tests show the controls work against these specific attacks, not that the system is secure in general (see `docs/week14/14-residual-risk-register.md`).

**Recommendation:** use a real certificate and a secret store, and set `APP_DEBUG=false`, when deployed (Week 16). Give each user their own password with a reset flow, and add a Content Security Policy to reduce the risk of token theft from the browser.

This is a classroom prototype and is not production-ready.
