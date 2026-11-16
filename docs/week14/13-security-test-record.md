# Week 14 – Security test record

Run on 2026-11-11, 15:30–15:33 Asia/Manila, commit `a20d703` on `week14-security`, from `migrate:fresh --seed`, by Claude Code at the student's request. Outputs are in `evidence/week14/`.

| ID | Test | Predicted | Actual | Result |
| --- | --- | --- | --- | --- |
| W01 | Sign in with the right password | 200 with a token and expiry; password stored as bcrypt; only a token hash stored | 200, expires 60 minutes later; hashes start `$2y$12$`; stored token is a 64-character hash | Pass |
| W02 | Wrong password, and an unknown email | Both 401 with the same message | Both `invalid_credentials`, "Email or password is incorrect." | Pass |
| W03 | Six wrong passwords in a minute, then the right one | Attempts 1–5: 401; attempt 6 and the right password: 429 | As predicted | Pass |
| W04 | No token, an altered token, the old demo header, a requester calling an admin route | 401, 401, 401, 403 | As predicted | Pass |
| W05 | Use a token after its expiry | 401 | 200 before, 401 after the expiry was moved to the past | Pass |
| W06 | Use a token after signing out | 401 | Sign-out 200, reuse 401 | Pass |
| W07 | Plain HTTP; HTTPS headers; a client that does not trust the dev CA | 426; 200 with HSTS and `no-store`; connection refused | 426 "Use HTTPS"; HSTS and `no-store` present; curl exit 60 | Pass |
| W08 | Genuine, replayed, altered-ciphertext, altered-reference, wrong-key and stale messages to the gateway | 200, 409, 400, 400, 400, 400 | All six as predicted | Pass |
| W09 | Controlled failure: gateway certificate not trusted | 1 attempt, then manual handling (`gateway_tls_failed`), no retry; sent after the fix and Requeue | As predicted | Pass |
| W10 | Free text at rest | Database holds ciphertext; the owner still sees plain text | Raw values start `eyJpdiI6` (encrypted JSON); phone number and comment absent; API returns the text to the owner | Pass |
| W11 | Secret scan of Git, the log and the evidence | No secrets anywhere | 0 everywhere; **finding:** test runs wrote into the live log (fixed, see below) | Pass after fix |
| W12 | Week 13 regression over HTTPS with tokens | Full approval path sent; 422 recipient still goes to a person | Issued with 3 notifications sent on attempt 1; 422 → `manual_action` | Pass |

Automated: `php artisan test` 24 of 24 passed (`01-tests-run1.txt`, rerun `01-tests-run2.txt`); `flutter analyze` no issues and `flutter test` 2 of 2 passed.

## Findings

1. **Test runs polluted the live log.** `php artisan test` wrote its own `auth.*` lines into `storage/logs/permit.jsonl`, mixed with real events (`"channel":"testing"` lines in `W11/02-auth-log-lines.jsonl`). Fix: tests now log to `permit-test.jsonl` (`PERMIT_LOG_PATH`). Recheck: the live log stayed at 134 lines across a full test run (`W11/03-test-log-fix.txt`).
2. **One unexplained test failure while building.** A run reported 23 of 24 passing; three runs straight after passed 24 of 24, and the failing test was not identified. Recorded, not hidden.
3. **Git's curl on Windows** (Schannel) rejects the dev CA because it has no revocation list. Evidence calls use `--ssl-no-revoke`, which skips only that lookup. PHP's own curl (OpenSSL) verifies the certificate without it.

## Not covered

- Browser screenshots of the wrong-password, signed-in and session-ended states (U3–U5): Claude's browser refuses the local certificate, so the student captures these (see `evidence/week14/README.md`). The sign-in, validation, narrow-width and API-down states (U1, U2, U6, U7) were captured.
- The source and configuration review (W13) is in `evidence/week14/W13/`.
- The gateway runs on the same laptop, so "two systems" is simulated.
