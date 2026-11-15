# Week 14 – Secure-exchange review (Activity 2)

**Scope:** the Week 14 build on `week14-security`: the browser-to-API exchange (sign-in, tokens, permit data) and the API-to-gateway exchange (notifications). Reviewed on 2026-11-15 by Claude Code at the student's request. Raw results: `evidence/week14/W13/01-source-and-config-review.txt`, plus scenarios W01–W12.

## Review checklist and results

| Area | Check | Observed | Result |
| --- | --- | --- | --- |
| Source inspection | Secrets read only through configuration, never hard-coded | No `env()` calls outside `config/`; no hard-coded keys or passwords in `app/`, `config/` or `routes/` | Pass |
| Source inspection | Established libraries, no homemade or weak cryptography | libsodium AEAD, Laravel `Hash` (bcrypt), Laravel encrypter; 0 uses of MD5, SHA-1, `openssl_encrypt` or similar | Pass |
| Source inspection | TLS verification never switched off | 0 occurrences of `verify => false` or `CURLOPT_SSL_VERIFYPEER`; both gateway calls verify against the dev CA | Pass |
| Source inspection | Secrets compared in constant time | Gateway compares the bearer key with `hash_equals`; libsodium checks the tag in constant time | Pass |
| Source inspection | Nothing secret in logs | 13 log events; none pass a password, token, key, email, comment or reason. One pattern match was a fixed label (`gateway_unhealthy`), not data | Pass |
| Configuration review | HTTPS required, token lifetime, CORS | `REQUIRE_HTTPS=true`; tokens 60 minutes; CORS allows one origin, GET and POST only, no cookies; only 127.0.0.1 may claim HTTPS via proxy headers | Pass |
| Configuration review | Debug mode | `APP_DEBUG=true` in development. API errors still use the safe shape (no trace), but Laravel's own web error pages would show details | **Finding F1** |
| Configuration review | Secrets kept out of Git | `.env`, `certs/`, the database and logs are all git-ignored; scan found 0 secrets in tracked files | Pass |
| Client review | No secrets in the Flutter source; token not stored | 0 uses of localStorage or shared preferences; 0 keys or passwords in `client/lib` | Pass |
| Transport verification | HTTP refused, HTTPS correct, untrusted CA refused | W07: 426, HSTS and `no-store`, curl exit 60 | Pass |
| Tamper testing | Altered, replayed, wrong-key and stale messages | W08: all six rejected or accepted as predicted | Pass |
| Failure handling | Untrusted gateway certificate | W09: one attempt, handed to a person, sent after the fix | Pass |
| Secret scanning | Git, the live log, the evidence folder | W11: 0 everywhere; test-log mixing found and fixed | Pass after fix |

## Findings

| ID | Finding | Risk | Recommendation | Status |
| --- | --- | --- | --- | --- |
| F1 | `APP_DEBUG=true` in `.env` and `.env.example` | Low here (API errors are already safe); high on a public server, where Laravel error pages can show code and settings | Set `APP_DEBUG=false` in any deployed environment (Week 16 environment comparison) | Open, recorded |
| F2 | Test runs wrote into the live log (W11) | Mixed test and real events would mislead an investigation | Tests now use `permit-test.jsonl` | Fixed and rechecked |
| F3 | The demo password and keys share one `.env` with no secret store | A copied laptop folder exposes everything | Host secret store in deployment (residual risk R3) | Open |

## Interpretation

**Observed:** every source, configuration, transport, tamper and scanning check passed except F1, which is a development setting, and F2, which was fixed.

**Concluded:** the two exchanges protect confidentiality and integrity as designed, and no secret reaches Git, the log or the evidence. The controls depend on keys staying in `.env`, on clocks staying within 5 minutes, and on development settings not reaching a real server.

**Recommended:** turn debug off and move keys to a secret store when deploying, and keep running `php artisan gateway:tamper-test` and the secret scan after every change to the exchange code.
