# Week 14 evidence index (SA8 / IA5 – Secure Data Exchange and Encryption)

**Who produced this:** Claude Code (AI assistant) ran W01–W12 at the student's request, on the student's laptop. The outputs are unedited, except that access tokens are replaced with `[redacted]` before saving. The UI screenshots (U1–U6) are taken by the student. See `lab-log.md`.

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

Also: `01-tests-run1.txt` and `01-tests-run2.txt` (24 of 24 passed both times), `02-flutter-analyze-and-test.txt` (no issues; 2 of 2 passed).

## Reading the files

- `W08\02-gateway-received.jsonl` holds two runs of the tamper test: one at 15:29 while checking the setup, and the evidence run at 15:31. Both gave the same six results.
- `W12\08-gateway-received.jsonl` also contains the W09 and W10 deliveries, because the gateway log was not cleared between those scenarios.
- `W11\02-auth-log-lines.jsonl` contains 19 lines from a test run (`"channel":"testing"`). That is the defect found in W11, kept as it was found. The 22 live lines are 12 sign-ins, 7 failed attempts, 2 throttled attempts and 1 sign-out, which match W01–W06.
- Evidence `curl` calls use `--ssl-no-revoke` because Windows' TLS library cannot check revocation for a local CA. The certificate is still verified.

## UI screenshots (U1–U6) – to be taken by the student

Claude's browser refused the local certificate, so these were not captured. In your own Chrome, open https://localhost:8443/up once and accept the warning, then run the app (see the README) and save into `evidence\week14\UI\`:

| # | State | How |
| --- | --- | --- |
| U1 | Sign-in screen with the password field | Open the app |
| U2 | Field validation | Press **Sign in** with both fields empty |
| U3 | Wrong password | Requester chip, type a wrong password, **Sign in** |
| U4 | Signed in over HTTPS | Sign in correctly; show the permit list |
| U5 | Session ended | Sign in, then in `backend\` run `php artisan tinker --execute="DB::table('personal_access_tokens')->update(['expires_at'=>now()->subMinute()]);"` and press Refresh |
| U6 | Narrow width | DevTools device toolbar at 375 px on the sign-in screen |

Do not show the password in any screenshot (keep it hidden), and do not show the address bar if it reveals anything other than `localhost`.

## Interpretation

This section is a draft by Claude Code. Rewrite it in your own words.

**What was observed:** all twelve scenarios matched their predictions. The test-log defect (W11) was found during the run, fixed and rechecked. One test failure seen while building could not be reproduced.

**What it shows:** the API now knows who is calling, and tokens stop working when they expire or the user signs out. Traffic is encrypted and plain HTTP is refused. A message to the gateway cannot be read on the way, changed without detection, or replayed. Free text in the database is unreadable without `APP_KEY`.

**Limits of this evidence:** both systems run on one laptop with a self-made certificate. Keys sit in `.env`, and the demo accounts share one password. The tests show the controls work against these specific attacks, not that the system is secure in general (see `docs/week14/14-residual-risk-register.md`).

**Recommendation:** use a real certificate and a secret store when deployed (Week 16), give each user their own password with a reset flow, and add a Content Security Policy to reduce the risk of token theft from the browser.

This is a classroom prototype and is not production-ready.
