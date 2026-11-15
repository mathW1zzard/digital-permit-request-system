# Week 15 – Defect record

Every defect found during Week 15, including problems in the test tools. Nothing was deleted from the evidence; failed runs are kept in `evidence/week15/C0-*` and `C1-baseline`.

| ID | Found by | Steps to reproduce | Expected | Actual | Cause | Fix | Retest |
| --- | --- | --- | --- | --- | --- | --- | --- |
| D1 | T23 | Requester submits a permit starting 366 days from today | 422 (the form allows at most 365) | 201, permit created | Server rule had no upper bound | Added `before_or_equal` limit from `permit.max_days_ahead` | T23 422; HardeningTest D1 |
| D2 | T35, T36 | `GET /api/no-such-endpoint`; `DELETE /api/permits/{ref}` | Safe error with correlation ID | Safe error, no correlation ID | Correlation middleware ran only inside the API route group, which unknown routes never reach | Made it global; kept exception headers | T35, T36 pass; HardeningTest D2 |
| D3 | T40, T41 | Submit the same permit twice with the same `Idempotency-Key` | Same permit returned | Second permit created; key reuse not detected | No idempotency support | `idempotency_keys` table and controller logic; client sends the key | T39–T41, F5; HardeningTest D3; client test |
| D4 | T42 | Any API call | `nosniff`, frame, referrer, CSP headers | None | Not configured | `SecurityHeaders` middleware | T42; HardeningTest |
| D5 | T43 | Any API call | No server version | `X-Powered-By: PHP/8.4.12` | PHP adds it by default | Removed in middleware | T43; HardeningTest |
| D6 | T45 | Many calls in a minute | Rate limit | Unlimited | Only sign-in was throttled | Per-user `api` limiter | T45, F6; HardeningTest D6 |
| D7 | F7 (failure injection) | Signed-in requester posts a 9 MB JSON body | 413, body refused before it is processed | 422 `validation_failed` after the whole body was read and decoded; nothing saved | No size limit in the app; this PHP install sets `post_max_size` to 2 GB, so the 8 MB default assumed in the prediction did not apply | `LimitRequestSize` middleware (1 MB, `MAX_BODY_KB`) and a 413 message | F7b 413; HardeningTest D7; full collection run C3 48/48 |
| D8 | Browser check while capturing the Week 14 screenshots U3–U5 (2026-11-16, after the `week15-ia6` tag) | Requester fills in the New permit form in Chrome and presses Submit | 201, permit created | Chrome blocked the POST: the preflight allowed only `Accept`, `Content-Type`, `Authorization`, `X-Correlation-Id`, but the Week 15 client also sends `Idempotency-Key`. No permit created; the form showed no error | The D3 fix added a client header without adding it to `config/cors.php`. PHPUnit, the collection and the widget test do not run in a browser, so none of them checks CORS | Allowed `Idempotency-Key` and exposed `Idempotent-Replayed` in `config/cors.php` | Form submit in Chrome 201 (`D8/`); HardeningTest D8 (reads the header names from `client/lib/api_client.dart`; fails without the fix); run C4 48/48 |
| TD1 | Run C0 | Started the collection right after starting the servers | Requests reach the API | All 48 got 502 from the proxy | Readiness check counted any HTTP answer (including the proxy's 502) as ready | Wait until `/up` returns 200 | Run C1 |
| TD2 | Run C0b | Signed-in requests in the PHP runner | Token sent | 401 on every signed-in call | `$sub` was an arrow function, which copied the variables when created, so tokens saved later were never used | Changed to a closure that uses `$vars` by reference | Run C1 onwards |
| TD3 | Building the collection | Generated request URLs | `/login` | `{{baseUrl}}data.token` | A loop variable reused the name `$path` | Renamed it | Collection rebuilt before any run |
| TD4 | F4 | Failure script after the two parallel endorsements | Script continues to F5 | Script hung for two hours | Plain `wait` also waited for the mock gateway restarted in F2 | Wait only for the two request processes; stopped and resumed the script | F4 count, F5–F7 ran |
| TD5 | F4 | Count `endorsed` events | A number | `BadMethodCallException` | The query used a relation `PermitEvent` does not have | Count through `Permit::events()` | F4 count 1 |

**TD** = test-tool defect (in my test code, not in the API). They are recorded because they changed what the first runs showed.

**D8 and the silent form.** When the browser blocked the submission, the New permit form stayed open with no message, while the sign-in screen shows "Cannot reach the server" for the same kind of failure. This is recorded as open item U1 in the hardening backlog.
