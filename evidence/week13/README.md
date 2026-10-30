# Week 13 evidence index

Fill in Actual, Result, Time and Commit from your own runs (Step 12 of the build guide), even where they differ from what you expected.

Tagged commit: TODO

| ID | Criterion | Expected | Actual | Evidence files | Result | Time (Asia/Manila) | Commit |
| --- | --- | --- | --- | --- | --- | --- | --- |
| S1 | AC1 | 201, routed, issued, 3 notifications sent | | S1\01-06 | | | |
| S2 | AC5 | Sent on attempt 3; gateway: 503, 503, accepted | | S2\02-06 | | | |
| S3 | AC6 | `manual_action` after 1 attempt; sent after fix | | S3\02-07 | | | |
| S4 | AC7 | No row over 4 attempts; `transient_exhausted`; paused after 3 failures in a row; sent after restart and Requeue | | S4\ | | | |
| S5 | No duplicate | Gateway: one accepted and one `duplicate_ignored` for the same key | | S5\02-04 | | | |
| S6 | AC8 | Reminders 1, then 0 | | S6\01-03 | | | |
| S7 | AC9 | Expired 1, then 0; verify `valid: false`; requester gets 403 | | S7\01-05 | | | |
| S8 | AC10 | Escalated then 0; no reviewer flagged, then rerouted 1 | | S8\01-06 | | | |
| S9 | AC2-AC4 | 401, 422, 403, 403, 404, 409; correlation ID on each; no stack trace | | S9\ | | | |
| S10 | UI states | The 13 states in the guide's Step 13g | | S10\ | | | |

## Interpretation

What I observed: TODO

What I conclude: TODO

What I recommend: TODO

This is a classroom prototype and is not production-ready.
