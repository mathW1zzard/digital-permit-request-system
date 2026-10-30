# Failure taxonomy, retry policy, recovery runbook

| Failure | Signal | Class | Automatic action | Who acts next |
| --- | --- | --- | --- | --- |
| Gateway timeout, refused connection | `ConnectionException` | Transient | Retry with backoff, up to 4 attempts in total | Admin, only if attempts run out |
| Gateway overloaded or erroring | HTTP 408, 425, 429, 500, 502, 503, 504 | Transient | Same as above | Same as above |
| Recipient rejected | HTTP 422 | Permanent | No retry; set `manual_action` | Admin fixes the address, then requeues |
| Wrong gateway key | HTTP 401 or 403 | Permanent (configuration) | No retry; set `manual_action` | Developer fixes `.env`, admin requeues |
| Invalid user input | Our API returns 422 | Business rule | Request refused, nothing saved | Requester corrects the form |
| Action not allowed in this state | Our API returns 409 | Business rule | Request refused, nothing changed | User refreshes |
| No reviewer or approver available | Router finds nobody | Exception | Flag the permit, alert admins once, retry routing every run | Admin restores staff |
| Review or approval past its SLA | `stage_entered_at` + SLA hours is in the past | Exception | Flag the permit, alert admins once | Admin follows up |
| Automation step crashes | Exception inside one step | Unexpected | Log an error; other steps still run; command exits non-zero | Developer |

## Retry policy (values live in `.env`)

- Timeout per gateway call: 3 s.
- Maximum attempts: 4 (the first try plus 3 retries).
- Delay before retry n: min(cap, base × 2^(n-1)), then a random ±20% jitter. In the demo, base = 5 s and cap = 300 s, giving roughly 5 s, 10 s and 20 s. For real use, pick a base of about 60 s.
- Each attempt sends the same `Idempotency-Key`, so a retry after a lost response can never deliver twice.
- Each row is claimed with a conditional update (a lease) before sending, so two dispatchers never send the same row at once.
- Back-pressure: after 3 transient failures in a row, the dispatcher stops that run instead of hammering a sick gateway.

## Recovery runbook (what the admin does)

1. Open the Needs attention tab and read the failure class.
2. Permanent (`recipient_rejected`): correct the user's email, then press Requeue.
3. Transient, attempts exhausted: check the gateway at http://127.0.0.1:9000/health, start it if it is down, then press Requeue.
4. Routing exception: restore or add a reviewer or approver. The next automation run re-routes the permit; to run it now, use `php artisan permits:run-automation`.
5. SLA exception: contact the assigned reviewer or approver. The flag clears as soon as the permit changes state.
6. Write what happened in the lab log.
