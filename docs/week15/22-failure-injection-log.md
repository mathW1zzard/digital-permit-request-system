# Week 15 – Failure-injection log

Each scenario was predicted before it ran (the prediction is saved as `00-prediction.txt` in its evidence folder). All of them ran against the hardened build (fixes D1–D6) over HTTPS on 2026-11-15 (Asia/Manila), in one pass from a freshly seeded database: F1–F4 at 20:44–20:47, then, after the script problem described below, F5–F7 at 22:46–22:47. F7b ran at about 22:48, after defect D7 was fixed.

| ID | Failure injected | Prediction | Observed | Match | Evidence |
| --- | --- | --- | --- | --- | --- |
| F1 | Gateway answers after 5 s (timeout is 3 s) | Attempt 1 transient and retried; attempt 2 sent; gateway marks the retry `duplicate_ignored` | Run 1: `retry_scheduled 1`. Run 2: `sent 1`. Gateway log: `accepted` then `duplicate_ignored` for the same idempotency key | Yes | `F1/` |
| F2 | Gateway process stopped | Retried with growing waits; handed to a person after 4 attempts; sent once after restart and Requeue | Pending after runs 1–3, `manual_action` after run 4 (`attempts 4`, `transient_exhausted`). After restart and Requeue: `sent 1`, one delivery | Yes, with one difference (below) | `F2/` |
| F3 | Database file removed during use | 500 in the safe shape with a correlation ID; log has class and location only; works again after restore | 500 `server_error` with correlation ID; 0 SQL or path matches in the response and the log line; log has `QueryException` at `Connection.php:864`; `/me` 200 after restore with no restart | Yes | `F3/` |
| F4 | Two endorsements of one permit sent at once | One 200, one 409; exactly one `endorsed` event | 200 and 409; 1 `endorsed` event | Yes (see limitation) | `F4/` |
| F5 | Same submission, same `Idempotency-Key`, sent twice at once | One permit; 201 and 200 with `Idempotent-Replayed: true`, same reference | Permits 3 → 4; 201 and 200 `replayed=true`, both `PRM-20261115-XSKHK` | Yes (see limitation) | `F5/` |
| F6 | One user sends 65 requests in a minute | 60 × 200, then 429 with `Retry-After`; other users unaffected | 60 × 200, 5 × 429, first 429 at request 61; `retry-after: 17`, safe shape; requester got 200 meanwhile | Yes | `F6/` |
| F7 | 9 MB request body | 413 in the safe shape, nothing saved | **422** `validation_failed` (purpose too long); nothing saved; no stack trace | **No** → defect D7 | `F7/` |
| F7b | Same as F7, after the D7 fix | 413 `http_413` in the safe shape, nothing saved | 413 `http_413` with correlation ID; permits 4 → 4; 0 stack-trace matches | Yes | `F7b/` |

## Differences from the predictions

**F2 detail.** I predicted the failure detail `gateway_unreachable`. The actual detail was `gateway_http_502`. The local TLS proxy on port 9443 stayed up while the gateway behind it was stopped, so the dispatcher got a 502 from the proxy instead of a refused connection. Both are treated as transient, so the behaviour (retry, then hand over to a person) is the same. This is also closer to a real deployment, where a load balancer answers for a service that is down.

**F7 status.** I predicted 413 because PHP's default `post_max_size` is 8 MB. The PHP install on this laptop (Laragon) sets it to 2 GB. So the 9 MB body was read in full, decoded and only refused by the 255-character rule. The answer was still safe and nothing was saved, but the API had no size limit of its own: a client could make the server read and parse very large bodies. This is recorded as defect **D7** and fixed with the `LimitRequestSize` middleware (1 MB, `MAX_BODY_KB`). F7b shows the fix.

## Problems in the test script (not in the system)

- After F4's two requests finished, the script hung for two hours. The plain `wait` command also waited for the mock gateway, which F2 had restarted as a background job and which never exits. The script was stopped and the rest was run with `wait` limited to the two request processes. The F4 requests themselves had finished at 20:46:47 and are unchanged.
- The first F4 event count used a relation that `PermitEvent` does not have and printed an error. The count was re-run with `Permit::events()` and the file notes this.

## Limitation of F4 and F5

`php artisan serve` on Windows answers one request at a time, so the two "simultaneous" requests were queued, not truly parallel. F4 and F5 prove the duplicate is refused or replayed when the second request arrives after the first is saved. The database guards (for F4, the status update only applies `where status = <the status read before>`, so the second writer changes 0 rows and gets 409; the unique index on user and key for F5, with a refetch when the insert collides) cover the truly parallel case, but that path was not exercised here. It is open item H2, to retest on a multi-worker server in Week 16.
