# Week 15 – Test strategy

**Decision question:** How do we show, with repeatable evidence, that the permit API behaves correctly for normal, invalid, unauthorized, duplicate and failing-dependency cases, and stays correct after hardening changes?

## Scope

**In scope:** the Laravel API behind the HTTPS proxy (sign-in, permits, workflow actions, admin exceptions, verify), the notification dispatcher and mock gateway, and the Flutter client's request behaviour.

**Out of scope:** load testing beyond a single user (Week 16), real email or SMS providers, browser automation of the Flutter UI, and penetration testing of the operating system.

## Layers

| Layer | Tool | What it proves | Where |
| --- | --- | --- | --- |
| Unit and feature | PHPUnit (`php artisan test`), 30 tests | Workflow rules, retry and expiry, security controls, each hardening fix | `backend/tests/` |
| Client | `flutter test`, 3 tests | Sign-in validation, session expiry, idempotency key resend | `client/test/` |
| API contract and integration | Postman collection, 48 requests in 8 folders, run by Postman or `api-tests/run-collection.php` | Status codes, error shape, validation boundaries, authorization, duplicates, headers, leakage, recovery path, end to end over HTTPS | `api-tests/` |
| Failure injection | Scripted scenarios F1–F7 (and F7b, the retest after D7) | Timeouts, dependency down, database unavailable, racing requests, bursts, oversized input | `evidence/week15/F1`–`F7` |
| Regression | All of the above, after each change | Hardening did not break approved workflows | `evidence/week15/C2-after-fixes`, test runs |

**Alternative considered:** Postman with Newman as the only runner. Rejected for this laptop because Newman must be installed from npm, and an earlier npm download was blocked here. The collection is still a standard Postman v2.1 file, so it can be imported and run in Postman or Newman, and the PHP runner executes exactly the same file and checks.

## Test data

Fictional accounts from the seeder, fictional permits created by the tests, and the `DEMO_PASSWORD` from `backend/.env` (never written into the collection or results; tokens are redacted). Every run starts from `php artisan migrate:fresh --seed`.

## Entry and exit criteria

- **Entry:** API, gateway and HTTPS proxy running; `/up` returns 200; database freshly seeded.
- **Exit:** all 48 collection requests pass, all PHPUnit and Flutter tests pass, every failure-injection prediction is confirmed or the difference is recorded as a defect, and every open defect has an owner and a decision.

## Ownership

The student owns the tests and their results. A failing test is never deleted or weakened to make a run pass; the defect is fixed or recorded as open.
