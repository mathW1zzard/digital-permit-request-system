# Maintainability note

| Question a maintainer asks | Answer for Project 32 |
| --- | --- |
| Where do the rules live? | Validity, reminder days, SLA hours and senior approval are rows in `permit_types`, changed without code. Allowed state moves are in `PermitStatus::allowedNext()`. Retry numbers are in `.env` through `config/permit.php`. |
| Where does a status change happen? | Only in `PermitWorkflow`. Controllers and scheduled jobs call it and never write status themselves. |
| How do I add a notification type? | Call `$this->outbox->enqueue(...)` inside the workflow method with a new kind and a dedupe key that is unique per real-world event. The dispatcher needs no change. |
| How do I swap the mock for a real SMS or email provider? | Change `GATEWAY_URL` and `GATEWAY_API_KEY`, then map the provider's status codes onto `TRANSIENT_HTTP` in `NotificationDispatcher`. Check that the provider honours idempotency keys; if not, record that as a new risk. |
| How do I know it works after a change? | `php artisan test` plus scenarios S1-S9 in `evidence/week13/README.md`. |
| How do I see what happened in production? | `storage/logs/permit.jsonl`: search by permit reference or correlation ID. Error-level events are the ones to alert on. |
| What is deliberately simple? | One process, SQLite, a scheduler instead of a queue worker, a 5-minute scan instead of exact timers (see ADR-001 and ADR-002, and their review conditions). |
| Dependencies | Laravel 13 and its bundled packages, Sanctum (installed for Week 14), Flutter `http` and `provider`. There are no other third-party packages, which keeps the update surface small. |

Measured indicator: `NotificationDispatcher` is 144 lines with one public method; the 14 tests (the guide's 12 plus Laravel's 2 examples) ran in about 3.1 seconds on 2026-10-30.
