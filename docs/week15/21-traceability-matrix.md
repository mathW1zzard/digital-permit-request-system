# Week 15 – Requirements-to-test traceability matrix (Activity 1)

Test IDs: **T** = Postman collection request, **F** = failure-injection scenario, **P** = PHPUnit test class, **C** = Flutter test. Types: positive (+), negative (–), boundary (B), authorization (A), recovery (R).

| Requirement | Source | Tests | Type | Unambiguous expected result |
| --- | --- | --- | --- | --- |
| R1 Only signed-in users use the API | ADR-004 | T10, T11, P SecurityTest | – | 401 `unauthenticated` with a correlation ID |
| R2 Sign-in gives a short-lived token; wrong password does not reveal which part was wrong | ADR-004 | T01–T08, P SecurityTest | +, – | 200 with token and `expires_at`; wrong password 401 `invalid_credentials` |
| R3 Sign-in requires both fields | API contract | T09 | – | 422 with `details.email` and `details.password` |
| R4 Requesters submit a valid permit, which is routed | AC1 | T14, T15, T16 | + | 201, `under_review`, reviewer assigned, timeline and notification present |
| R5 Purpose is 10–255 characters | Validation rules | T17, T18, T19, T27 | B, – | 9 → 422; 10 → 201; 256 → 422; 100,000 → 422 |
| R6 Location is at most 120 characters | Validation rules | T20 | B | 121 → 422 `details.location` |
| R7 Start date is today up to 365 days ahead (same as the form) | Validation rules | T21, T22, T23, P HardeningTest D1 | B | yesterday → 422; today → 201; +366 days → 422 |
| R8 Permit type must exist and be a number | Validation rules | T24, T25 | – | 422 `details.permit_type_id` |
| R9 Malformed and oversized requests fail safely | OWASP API8, API4 | T26, F7, F7b, P HardeningTest D7 | – | 422 for broken JSON; 413 for a body over 1 MB (D7); no internals |
| R10 Only requesters submit | Roles | T28 | A | 403 |
| R11 Only the assigned approver approves | Policy | T29, T46 | A, + | Requester 403; assigned approver 200 `issued` |
| R12 A user cannot read another requester's permit | OWASP API1 (object authorization) | T30 | A | 403 |
| R13 Only the assigned reviewer endorses | Policy | T31, T37 | A, + | Other reviewer 403; assigned reviewer 200 |
| R14 Admin and inspector functions are role-limited | OWASP API5 (function authorization) | T32, T33, T47, T48 | A, + | Requester 403; inspector and admin 200 |
| R15 Unknown items, paths and methods fail safely | API contract | T34, T35, T36, T44, P HardeningTest D2 | – | 404 or 405, safe shape, correlation ID, no internals |
| R16 A workflow step cannot happen twice | AC4 | T38, F4 | –, R | Second endorsement 409 `invalid_transition`; one `endorsed` event |
| R17 A resent submission does not create a duplicate | Week 15 (D3) | T39, T40, T41, F5, P HardeningTest D3, C idempotency test | +, –, R | Same key and body → 200 same reference; same key, other body → 422 `idempotency_key_reused`; one permit |
| R18 Responses carry security headers and no server version | OWASP ASVS 14.4 (D4, D5) | T42, T43, P HardeningTest | – | `nosniff`, `DENY`, `no-referrer`, CSP present; no `X-Powered-By` |
| R19 Signed-in calls are rate limited | OWASP API4 (D6) | T45, F6, P HardeningTest D6 | B, – | Limit headers present; request 61 in a minute → 429 with `Retry-After` |
| R20 Gateway timeouts are retried without duplicate delivery | ADR-002 | F1 | R | Attempt 1 transient, attempt 2 sent, gateway `duplicate_ignored` |
| R21 A gateway outage is bounded and handed to a person | AC7 | F2 | R | Manual action after 4 attempts; sent after restart and Requeue |
| R22 A database outage fails safely and recovers | Week 15 | F3 | –, R | 500 safe shape with correlation ID; log has class only; works again after restore |
| R23 Errors never expose internals | OWASP API8 | T13, T24, T26, T27, T34–T36, T44, F3, F7, F7b | – | No SQL, paths, stack traces or emails in any response |
| R24 The browser allows every request the web client sends (CORS) | Week 15 (D8) | P HardeningTest D8, D8 browser check | – | Preflight from the app's origin allows every header in `client/lib/api_client.dart`; form submit 201 in Chrome |

Every requirement has at least one negative or boundary test, and every authorization rule has a test from the wrong role. Coverage gaps are listed in the hardening report.
