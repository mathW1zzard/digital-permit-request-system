# Week 17 – User acceptance test report

Prepared by Claude Code (AI assistant) at the student's request, from `evidence/week17/`. Student: Joseph R. Dionisio, BSIT 3A.

> **This was a simulated round.** No partner-office staff or classmates were available, so at the student's request Claude Code played every role. It followed the scenario cards (`41-uat-scenario-cards.md`) against staging through the real API. The round shows whether the system **behaves** correctly for each role. It does **not** show whether real people find it easy to use: no ease-of-use ratings or tester quotes were made up, and AC-U7 is "not assessed".

## Summary

| Round | When (UTC, 2026-11-16) | Release | Result |
| --- | --- | --- | --- |
| 1 | 21:30–21:33 | `5773932` | **7 / 12.** Four real findings (F-01 to F-04); UAT-12 blocked by a test-tool defect (TD8) |
| 2 | 22:24–22:25 | `07d9154` (fixes for F-01 to F-04) | 8 / 12. The fixes were deployed but **not served**: deploy defect S4 |
| 3 | 22:34–22:35 | `57fcd31` (fixes, plus the S4 fix) | **12 / 12** |

**Recommendation to the acceptance authority: accepted with conditions,** for supervised trials with fictional data only. The conditions are at the end. The decision and signature belong to the instructor (`evidence/week17/UAT-signoff.md`).

## Entry criteria (round 1)

| Criterion | Met? | Evidence |
| --- | --- | --- |
| PHPUnit, Flutter analyze and Flutter tests pass | Yes: 40/40, no issues, 5/5 | `5773932` commit checks |
| Staging deployed from the release; `/api/health` `ready`; smoke run 0 errors | Yes: `ready` at 21:30:03; smoke 168 requests, 0 errors | `UAT-round1/00-health-before.json`, `00-smoke/` |
| Backup taken; watcher running | Yes: `uat-before` backup; `watch.sh` running, no alerts in any round | `E2-ops-log.txt` |
| Testers briefed on fictional data | Not applicable (simulated); all data used is fictional | – |

## Results by card (round 3, release `57fcd31`)

| Card | Role | Result | What was observed |
| --- | --- | --- | --- |
| UAT-01 | Requester | Pass | 201 with a PRM reference, `under_review`, a reviewer assigned |
| UAT-02 | Requester | Pass | 422 with a message for the purpose and the start date; nothing saved |
| UAT-03 | Reviewer | Pass | `pending_approval`; the timeline shows the endorsement **and who did it** |
| UAT-04 | Reviewer + requester | Pass | `revision_requested`; **the requester can read the reviewer's note**; resubmitted to `under_review` |
| UAT-05 | Approver | Pass | `issued` with a valid-until date, seen by the requester |
| UAT-06 | Approver | Pass | Rejection without a reason refused (422); with a reason, `rejected`, and **the requester can read the reason** |
| UAT-07 | Inspector | Pass | The issued permit is valid with a date; an unissued one is not valid, with its status |
| UAT-08 | Two reviewers | Pass | 200 and 409 "**Someone else acted on this permit first. It is now pending approval.**"; one endorsement in the timeline |
| UAT-09 | Requester | Pass | Another requester's permit: 403 with a message; approving one's own permit: 403 |
| UAT-10 | Requester | Pass | Expired session: 401 "Sign in to continue."; after signing in again the permit is still there |
| UAT-11 | Requester | Pass | A cut connection fails cleanly; the next attempt creates the permit; pressing Submit again returns the same permit (200, replayed); exactly one permit |
| UAT-12 | Admin | Pass | The undeliverable "permit issued" notification is listed (`permanent`, `gateway_http_422`); after the address was fixed and Requeue pressed, it was delivered and left the list |

Every request and check of every round is in `results.json`. The per-card table for each round is in `results.md`.

## Findings and dispositions

| No. | Card | Finding | Severity | Disposition | Retest |
| --- | --- | --- | --- | --- | --- |
| F-01 | UAT-03 | The timeline did not show who acted | 3 | Fixed: events carry `by` (name) | Round 3 pass |
| F-02 | UAT-04 | A requester asked to revise could not read the reviewer's comment, so resubmitted without knowing what to change | 2 | Fixed: the timeline carries the comment as `note`, only to people allowed to open the permit; never in the list | Round 3 pass; `UatFindingsTest` |
| F-03 | UAT-06 | A rejected requester could not read the reason | 2 | Fixed: same as F-02 | Round 3 pass |
| F-04 | UAT-08 | The losing reviewer was told the action was "not available while the permit is under review" although it had just become pending approval | 2 | Fixed: a lost race says someone else acted first and names the current status | Round 3 pass; `UatFindingsTest` |
| S4 | (round 2) | After a deploy, the web workers could keep serving the previous release: Windows OPcache shared memory outlived a one-by-one worker restart | 1 for operations (a fix can silently not reach users) | Fixed in `workers.sh`; verified both ways (rollback served the old code, deploy forward served the new) | `S4-opcache/` |
| – | (round 3 setup) | `deploy.sh --skip-web` copied the web build of the live release, which after a rollback was older | 2 for operations | Fixed: `--skip-web` is refused when the client code differs; the right build was put in place by hand | Ops log 22:32:54 |

**Test-tool defect, not a system finding:** TD8. In round 1 the UAT-10 step expired every session on staging, so UAT-12's reviewer, approver and admin calls all got 401. The step now expires only the tester's sessions.

**Severity-1 findings open: 0.**

## Acceptance criteria

| ID | Criterion | Result |
| --- | --- | --- |
| AC-U1 | Core workflow end to end (UAT-01, 03, 05, 07) | Met, 4/4 |
| AC-U2 | Mistakes caught with a clear message, nothing wrong saved (UAT-02, 04, 06) | Met, 3/3 (after F-02 and F-03) |
| AC-U3 | Role limits hold (UAT-09) | Met |
| AC-U4 | No lost work or duplicates when things go wrong (UAT-08, 10, 11) | Met, 3/3 (after F-04) |
| AC-U5 | Exceptions reach a person (UAT-12) | Met |
| AC-U6 | No severity-1 finding open | Met |
| AC-U7 | Ease of use rated 4/5 or better | **Not assessed: simulated round** |

## Interpretation

**Observed.** The workflow itself (submit, review, approve, issue, verify, exceptions) worked from the first round. Everything UAT found was about what people **see**:
- who acted
- why they were asked to change something
- what the conflict message says

The most serious problem of the week was not in the application. It was in the deployment: a release that passed its health check could still be serving the old code (S4).

**Concluded.**
- **Hidden notes were a design mistake, now fixed.** Hiding the reviewer's notes from the browser looked like data minimisation, but it left requesters unable to act. The fix gives each note only to people who are already allowed to open that permit.
- **A health check proves the system is up, not which code is running.** S4 shows this. Round 2 caught it only because the UAT checks the behaviour itself.
- **A simulated round cannot replace real testers.** Real people would show whether the screens are clear and whether the workflow matches the office. The same kit is ready for that round.

**Recommended.**
1. Run a real round with the same kit, AC-U7 included, as soon as testers are available.
2. After every deploy, run a quick behaviour check, not just `/api/health`. UAT-03 takes about 5 seconds.
3. Keep the conditions below until the items in the maintenance plan are done.

## Conditions of acceptance (recommended)

- **Data:** fictional data only, and supervised use.
- **Hosting:** run from the staging setup, never `php artisan serve`.
- **Monitoring:** `watch.sh` runs during any trial.
- **Backups:** take a backup before each trial and copy it off the machine.
- **Before real users:**
  - per-user accounts and password reset
  - a privacy review with the barangay as data controller
  - a real host and certificate
  - a real notification provider
  - a real UAT round with ease-of-use ratings
