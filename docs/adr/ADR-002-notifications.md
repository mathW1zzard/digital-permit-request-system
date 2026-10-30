# ADR-002 Notification delivery

**Decision:** Transactional outbox. The notification row is written in the same transaction as the status change. A separate dispatcher delivers it with bounded retry, an idempotency key, and a manual-action state for anything it cannot deliver.

**Rejected:** Send inside the API request. The user waits on the gateway, and if the gateway is down the notification is lost or the request fails.

**Rejected for now:** Laravel queued jobs with `tries` and `backoff`. This is a valid option, but it needs a queue worker, and its `failed_jobs` table is not shown to admins. It is the natural upgrade if volume grows.

**Consequences:** Delivery can lag up to 1 minute, and the outbox table grows, so a cleanup job is future work.
