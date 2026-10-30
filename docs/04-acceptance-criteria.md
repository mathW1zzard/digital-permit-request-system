# Measurable acceptance criteria

| ID | Given / when | Then (pass condition) |
| --- | --- | --- |
| AC1 | A requester submits a valid permit | 201; status `under_review`; reviewer is the one with the fewest open reviews; one `review_assigned` notification queued |
| AC2 | Required fields are missing or the start date is in the past | 422 with field errors; no permit row created |
| AC3 | A requester calls approve, or the `X-Demo-User` header is missing or unknown | 403, or 401; nothing changes |
| AC4 | An action is not allowed in the current status (approve while `under_review`) | 409; nothing changes |
| AC5 | The gateway returns 503 twice, then 200 | Sent on attempt 3; the gateway records exactly 1 accepted message; the log shows 2 `notification.retry_scheduled` and 1 `notification.sent` |
| AC6 | The gateway returns 422 | `manual_action` after 1 attempt; 0 further attempts over the next 3 dispatch runs; shown on Needs attention; after the fix and Requeue, `sent` |
| AC7 | The gateway is stopped | `manual_action` (`transient_exhausted`) after exactly 4 attempts; the run pauses after 3 transient failures in a row |
| AC8 | An issued permit enters its reminder window | 1 reminder queued; running again queues 0 |
| AC9 | An issued permit passes `valid_until` | The next run sets it to `expired`; a second run changes 0; verify returns `valid: false` even before that run |
| AC10 | A permit sits in review past its SLA, or no reviewer exists | Flagged once with 1 alert per admin; with no reviewer, it is re-routed automatically once a reviewer exists |
