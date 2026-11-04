# Week 13 evidence index

**Who produced this:** Claude Code (AI assistant) ran S1-S9 and captured S10 (except the phone screenshot) at the student's request, on the student's laptop. The outputs are unedited. See `lab-log.md`.

**Build:** commit `00ce56d`, branch `week13-automation`. Environment: `00-environment.txt`. Tagged commit: `abd9eeb` (tag `week13-sa7`).

**Run:** S1-S9 on 2026-10-30, 22:58-23:03 Asia/Manila, in one pass from `migrate:fresh --seed`, in this order. S10 was captured on the same evening, 22:44-22:56, just before that commit, from the same code.

| ID | Criterion | Expected | Actual | Evidence files | Result | Time (Asia/Manila) | Commit |
| --- | --- | --- | --- | --- | --- | --- | --- |
| S1 | AC1 | 201, routed, issued, 3 notifications sent | `under_review`, reviewer Rita Reviewer; approver Alma Approver; `issued`, valid until 2026-11-29; 3 notifications sent on attempt 1 | S1\01-07 | Pass | 22:58 | 00ce56d |
| S2 | AC5 | Sent on attempt 3; gateway: 503, 503, accepted | 2 `retry_scheduled`, then `notification.sent` attempt 3 with `recovered_after_retry: true`; gateway 503, 503, accepted | S2\01-06 | Pass | 22:58 | 00ce56d |
| S3 | AC6 | `manual_action` after 1 attempt; sent after fix | 1 attempt, `permanent`, `gateway_http_422`; next run attempted nothing; requester's requeue got 403; after the address fix and admin Requeue, sent 1 | S3\01-09 | Pass | 22:59 | 00ce56d |
| S4 | AC7 | No row over 4 attempts; `transient_exhausted`; paused after 3 failures in a row; sent after restart and Requeue | All 4 rows ended at 4 attempts, `transient_exhausted`, `gateway_unreachable`; 12 retries and 5 `dispatch.paused` logged; after restart and Requeue, sent 1 | S4\01-06, S4\20-24 | Pass | 22:59-23:02 | 00ce56d |
| S5 | No duplicate | Gateway: one accepted and one `duplicate_ignored` for the same key | First call timed out (`retry_scheduled` 1); retry sent 1; gateway recorded accepted, then `duplicate_ignored`, same key | S5\01-04 | Pass | 23:02 | 00ce56d |
| S6 | AC8 | Reminders 1, then 0 | Reminders 1, then 0; dispatch sent 7 (1 reminder plus 6 admin alerts, see below) | S6\00-04 | Pass | 23:03 | 00ce56d |
| S7 | AC9 | Expired 1, then 0; verify `valid: false`; requester gets 403 | `valid: true` before; expired 1, then 0; status `expired`, `valid: false`; requester 403 | S7\01-05 | Pass | 23:03 | 00ce56d |
| S8 | AC10 | Escalated then 0; no reviewer flagged, then rerouted 1 | Escalated 1, then 0; with no reviewers the permit was flagged `no_reviewer_available`; rerouted 1 once they returned | S8\01-06 | Pass | 23:03 | 00ce56d |
| S9 | AC2-AC4 | 401, 422, 403, 403, 404, 409; correlation ID on each; no stack trace | All six returned as expected, each with a correlation ID; a search of S9 found no stack trace, vendor path or SQL | S9\01-08 | Pass | 23:03 | 00ce56d |
| S10 | UI states | The 13 states in the guide's Step 13g | All 13 captured; #3, #7 and #13 differ from the guide (see `lab-log.md`). The phone screenshot in #12 was taken by the student | S10\ | Pass, with noted differences | 22:44-22:56; phone 23:39 | before 00ce56d, same code |

Also: `01-migrations.txt`, `02-schedule.txt`, `03-tests-run1.txt` (14 of 14 passed: the guide's 12 plus Laravel's 2 examples), `04-flutter-analyze.txt` (no issues).

## Reading the numbers

- S6 escalated 6 because the simulated time, about 24 days ahead, put the older S2-S5 permits past their 24-hour review SLA. They stayed flagged, which is why S8 escalated only its own new permit (1).
- S4 logged 12 retries for 4 rows: 3 per row, then the fourth attempt failed. `S4\20-log-lines.jsonl` also contains each run's `dispatch.run_completed` line, because those lines mention the same counters. 3 of the rows reached the limit in one run, and the run then paused, which is why the last row ended one run later.
- S1 and S6 received the same `reference` in the gateway log because S6 reminded S1's permit, as the guide directs.

## Interpretation

## Interpretation

**What I observed.** All nine scripted scenarios (S1-S9) matched their expected results in the evidence run on commit 00ce56d. A valid permit was routed to a reviewer and an approver, then issued with 3 notifications delivered. In S2, the gateway returned 503 twice, and the notification was delivered on attempt 3. In S3, a rejected recipient (HTTP 422) stopped after one attempt and appeared on the admin's Needs attention list. It was delivered only after the address was corrected and an admin pressed Requeue. In S4, with the gateway stopped, all 4 rows stopped at 4 attempts and each dispatch run paused after 3 transient failures in a row. In S5, the gateway recorded one accepted message and one duplicate_ignored for the same key, so the retry did not deliver twice.

Not everything went right the first time. An earlier trial run showed that the first notification's dedupe key had an empty revision number (review_assigned:2:). This was fixed before the evidence run. My first phone test also failed with "Cannot reach the server" because the app was started with the wrong IP address. In S6, 6 permits were escalated instead of 1. This was expected: the simulated date was about 24 days ahead, so the older test permits were also past their 24-hour review SLA.

**What I conclude.** The four Week 13 automations (approval routing, expiry reminders, automatic expiration and exception escalation) work in this controlled setup. The retry design separates failures correctly: temporary failures are retried a limited number of times, while permanent failures go straight to a person. The automation never approved or rejected a permit by itself.

These results have limits. The gateway is a mock, the data is fictional, S6-S8 used a simulated clock, and each scenario ran only once. The commands were triggered by hand with the scheduler stopped, so schedule:work was not tested in this run. Claude Code (an AI assistant) ran S1-S9 from Git Bash and captured most of the S10 screenshots at my request, so api.cmd and ref.cmd were not tested. Three S10 captures differ from the guide (#3, #7 and #13). I also found two defects that are still open: the permit list can show an old status after a 409, and an unhandled database error can write raw details such as SQL and file paths to the log.

**What I recommend.** Before this system is used for anything real, replace the X-Demo-User header with real login and HTTPS (planned for Week 14), stop writing raw exception messages to the log, and reload the permit list after a failed action. The retry mapping should also be tested against a real provider's sandbox, and the scheduler should be monitored, because no automation runs if it stops.

This is a classroom prototype demonstrated with fictional data, and it is not production-ready.

**What was observed:** all nine scripted scenarios matched their expected results on the first complete evidence run. An earlier trial run of the same scenarios, before this commit, exposed one defect: the first notification's dedupe key had an empty revision number (`review_assigned:2:`). It was fixed before commit `00ce56d`; see `lab-log.md`.

**What it shows:** the four automations work against a separate gateway process. A transient failure recovered without a duplicate delivery, and a permanent failure stopped after one attempt and waited for a person.

**Limits of this evidence:** the gateway is a mock, the clock was simulated with `--now` for S6-S8, the scheduler was stopped so the commands were triggered by hand, and each scenario ran once. `schedule:work` itself was not exercised in this run, and neither were `api.cmd` and `ref.cmd`: the commands were run from Git Bash with equivalent `curl` calls, not typed in CMD. Two defects remain open: the list stays out of date after a 409, and the unhandled-exception log can include raw SQL details (`docs/09-known-limitations.md`).

**Recommendation:** replace the demo header with real login in Week 14, stop logging raw exception messages, and test against a provider sandbox before relying on the retry mapping.

This is a classroom prototype and is not production-ready.
