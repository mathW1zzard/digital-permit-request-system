# Week 14 evidence index (SA8 / IA5 – Secure Data Exchange and Encryption)

**Who produced this:** Claude Code (AI assistant) ran W01–W13, captured the UI screenshots U1, U2, U6 and U7, and wrote the review and interpretation, all at the student's request on the student's laptop. The outputs are unedited, except that access tokens are replaced with `[redacted]` before saving. U3–U5 are for the student to capture (see below). See `lab-log.md`.

**Build:** commit `a20d703`, branch `week14-security`. Environment: `00-environment.txt` (PHP 8.4.12, Laravel 13.39.0, libsodium 1.0.20, Node 22). Tagged commit: not tagged yet.

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
| U3 | Wrong password | - | **Student to capture** |
| U4 | Signed in over HTTPS, permit list | - | **Student to capture** |
| U5 | Session ended | - | **Student to capture** |
| U6 | Narrow width, 375 px | `U6-signin-375px.jpg` | Captured by Claude |
| U7 | API not running: safe "Cannot reach the server" message (extra) | `U7-api-down-network-error.jpg` | Captured by Claude. The console showed `ERR_CONNECTION_REFUSED`: the API and proxy were stopped. |

U3–U5 need the HTTPS API, and Claude's browser refuses the local certificate, so they could not be captured. In your own Chrome, start the system (README), open https://localhost:8443/up once and choose **Advanced → Proceed**, then save into `evidence\week14\UI\` as `U3-wrong-password.jpg`, `U4-signed-in.jpg` and `U5-session-ended.jpg`:

- **U3:** tap the Requester chip, type a wrong password, then **Sign in**.
- **U4:** sign in correctly and show the permit list.
- **U5:** sign in, then in `backend\` run `php artisan tinker --execute="DB::table('personal_access_tokens')->update(['expires_at'=>now()->subMinute()]);"` and press Refresh.

Keep the password hidden (never click the eye icon) in every screenshot.

## Interpretation

Written by Claude Code at the student's request.

**What was observed:** all twelve scenarios matched their predictions, and the source and configuration review (W13) passed with one recorded finding (debug mode on in development). The test-log defect (W11) was found during the run, fixed and rechecked. One test failure seen while building could not be reproduced. Three of the seven UI states are still to be captured.

**What it shows:** the API now knows who is calling, and tokens stop working when they expire or the user signs out. Traffic is encrypted and plain HTTP is refused. A message to the gateway cannot be read on the way, changed without detection, or replayed. Free text in the database is unreadable without `APP_KEY`.

**Limits of this evidence:** both systems run on one laptop with a self-made certificate. Keys sit in `.env`, and the demo accounts share one password. The tests show the controls work against these specific attacks, not that the system is secure in general (see `docs/week14/14-residual-risk-register.md`).

**Recommendation:** use a real certificate and a secret store, and set `APP_DEBUG=false`, when deployed (Week 16). Give each user their own password with a reset flow, and add a Content Security Policy to reduce the risk of token theft from the browser.

This is a classroom prototype and is not production-ready.
