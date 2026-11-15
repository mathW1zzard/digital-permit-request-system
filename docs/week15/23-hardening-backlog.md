# Week 15 – Hardening backlog (Activity 2)

## The weak configuration reviewed

The baseline run (`evidence/week15/C1-baseline`) tested the Week 14 build as it was. Together with a review of the development settings, it showed these weaknesses. They are ranked by **likelihood × impact × exposure** (each 1–3).

| Rank | ID | Weakness | Evidence | L | I | E | Score | Decision |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | D8 | The web form could not submit permits: CORS did not allow the `Idempotency-Key` header added for D3, so Chrome blocked every submission | Found in Chrome on 2026-11-16, after the tag | 3 | 3 | 3 | 27 | **Fixed** (found by a real-browser check, after the tag) |
| 2 | D3 | A resent submission (double click, retry after a lost answer) creates a duplicate permit | T40 got 201 and a new reference | 3 | 2 | 3 | 18 | **Fixed** |
| 3 | D6 | No limit on signed-in calls; one user or a stolen token can flood the API | T45 no limit headers | 2 | 3 | 3 | 18 | **Fixed** |
| 4 | D1 | Server accepts start dates beyond 365 days while the form blocks them (client and server disagree) | T23 got 201 | 3 | 1 | 3 | 9 | **Fixed** |
| 5 | D4 | No browser security headers (MIME sniffing, framing, referrer, CSP) | T42 headers missing | 2 | 2 | 2 | 8 | **Fixed** |
| 6 | D5 | `X-Powered-By: PHP/8.4.12` tells attackers the exact version | T43 header present | 2 | 1 | 3 | 6 | **Fixed** |
| 7 | D7 | No request size limit of its own: this PHP install accepts 2 GB bodies, so a client can make the server read and parse huge requests | F7 got 422 after a 9 MB body was parsed | 2 | 2 | 2 | 8 | **Fixed** (found by failure injection, after the baseline) |
| 8 | D2 | Unknown paths and wrong methods return errors without a correlation ID, so they cannot be traced | T35, T36 | 2 | 1 | 2 | 4 | **Fixed** |
| 9 | F1 (Week 14) | `APP_DEBUG=true` in `.env` | Week 14 W13 review | 1 | 3 | 1 | 3 | Open until deployment (Week 16): API errors are already safe |
| 10 | H1 | Permit list returns up to 100 rows with no paging | Code review | 1 | 1 | 2 | 2 | Open: fine at this volume |
| 11 | H2 | `php artisan serve` handles one request at a time, so true concurrency cannot be tested on this laptop | F4, F5 notes | 2 | 1 | 1 | 2 | Open: retest on a multi-worker server in Week 16 |
| 12 | U1 | The New permit form shows no message when the API cannot be reached (the sign-in screen does) | D8 retest | 2 | 1 | 2 | 4 | Open: Week 16 |

## Implemented controls (before and after)

| ID | Change | Before | After |
| --- | --- | --- | --- |
| D1 | `PermitController::permitRules()` adds `before_or_equal: today + 365 days` (`permit.max_days_ahead`) | T23: 201 | T23: 422 `details.requested_start` |
| D2 | `CorrelationId` and `SecurityHeaders` are global middleware; error rebuilds keep `Allow` and `Retry-After` | T35, T36: no correlation ID | Correlation ID present; 405 lists allowed methods |
| D3 | `Idempotency-Key` support on `POST /permits` with an `idempotency_keys` table (unique per user and key, SHA-256 of the body); the Flutter form sends one key per content | T40: new permit | T40: 200 with the same reference; T41: 422 `idempotency_key_reused` |
| D4 | `SecurityHeaders` middleware | No headers | `nosniff`, `DENY`, `no-referrer`, `default-src 'none'; frame-ancestors 'none'` |
| D5 | `header_remove('X-Powered-By')` in the same middleware | `PHP/8.4.12` | Header absent |
| D7 | `LimitRequestSize` global middleware, 1 MB (`MAX_BODY_KB`) | 9 MB body read and validated, 422 (F7) | 413 `http_413`, nothing saved (F7b); T27's 100,000-character purpose still 422 |
| D8 | `config/cors.php` allows `Idempotency-Key` and exposes `Idempotent-Replayed` | Preflight allowed 4 headers; Chrome blocked the form submission | Preflight allows all 5 client headers; form submit 201 in Chrome |
| D6 | Named limiter `api`: 60 per minute per user (`API_RATE_PER_MINUTE`) | No limit | 61st request → 429 with `Retry-After` (F6) |

No secrets were needed to show any of these: the evidence contains status codes, headers and redacted bodies only.
