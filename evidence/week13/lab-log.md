# Week 13 lab log - Project 32 Digital Permit Request System

| Date and time (Asia/Manila) | Commit | What I did | Expected | Actual |
| --- | --- | --- | --- | --- |
| 2026-10-30 22:44-22:56 | (none yet) | S10 screenshots captured by Claude Code (AI assistant), at the student's request, in its built-in browser from the release build. Served on port 5001 instead of 5000 because `flutter run` held port 5000; FRONTEND_ORIGIN was temporarily extended and then restored. | The 13 states in Step 13g | All 13 states captured, with the phone screenshot in #12 added later by the student; see S10 notes below |
| 2026-10-30 22:50 | (none yet) | #7 deviation: instead of two browser tabs, endorsed through the API as the same reviewer, then pressed Endorse on the outdated page. | 409 message | "This action is not available while the permit is pending approval." |
| 2026-10-30 22:51 | (none yet) | #8 deliberate failure: reassigned PRM-20261030-TCWUK to reviewer id 5 with tinker while reviewer 1 had it open, then pressed Endorse. | 403 | "You are not allowed to do this." |
| 2026-10-30 22:52 | (none yet) | #9 deliberate failure: stopped the API, pressed Refresh, restarted it, pressed Try again. | Connection problem, then recovery | As expected |
| 2026-10-30 22:53 | (none yet) | #10 deliberate failure: renamed database.sqlite, pressed Refresh, renamed it back. Reference web-1793371900617-765351113 found in permit.jsonl (S10/10-server-error-log-line.jsonl). | Server problem with reference | As expected |
| 2026-10-30 22:54 | (none yet) | #11: corrected the bad address with tinker, pressed Requeue, ran notifications:dispatch. | Sent after fix | Dispatch reported sent 1; notification status sent, attempts 1 |

## S10 notes

- #3 was captured by pressing Refresh on the normal connection, not with DevTools Slow 4G.
- #12 phone screenshot (`12-phone.jpg`) was taken by the student at 23:39 on their Android phone in Chrome (Step 13c Option A). It shows the New permit form at phone width.
- #13 shows focus as a grey highlight on the focused chip, not an outline. Only the first Tab stops were captured, not the whole order.
- Observed defect: after the 409 in #7, the left-hand list still said "under review" until the next Refresh, because `AppState.act` reloads the list only on success. Not fixed.

## Phone test

| Date and time (Asia/Manila) | Commit | What was done | Expected | Actual |
| --- | --- | --- | --- | --- |
| 2026-10-30 23:31 | 11ce688 | First phone attempt: the app was started with the guide's example IP (192.168.1.10) instead of the laptop's 192.168.1.38. | Sign-in works | **Failed:** "Cannot reach the server." |
| 2026-10-30 23:36-23:39 | 11ce688 | Restarted `flutter run` with the correct IP and reopened the app on the phone. | Sign-in and New permit work at phone width | Worked; screenshot saved as `S10/12-phone.jpg` |

## Evidence run

| Date and time (Asia/Manila) | Commit | What was done | Expected | Actual |
| --- | --- | --- | --- | --- |
| 2026-10-30 21:45 (approx.) | (none yet) | Trial run of S1-S9 by Claude Code, before the first commit. | Dedupe keys include the revision number | **Failed:** first key was `review_assigned:2:` because `revision_count` was not set on create. Output not kept in this folder; the symptom is recorded here. |
| 2026-10-30 22:13 (approx.) | (none yet) | Fix: set `revision_count` to 0 in `PermitWorkflow::submit`. Reran `php artisan test`. | Key ends in `:0`; tests pass | 14 of 14 passed; S2 gateway log now shows `review_assigned:2:0` |
| 2026-10-30 22:58-23:03 | 00ce56d | Evidence run of S1-S9 by Claude Code at the student's request, from Git Bash with `curl` equivalents of the guide's commands. | See `README.md` | See `README.md`; all nine passed |
| 2026-10-30 23:05 | 00ce56d | Diagrams exported by Claude Code: `mermaid-cli` was blocked by a permission check, so they were rendered with Mermaid 11.4.1 in a browser and captured as PNGs. Shortened one sequence-diagram note so it fits. | 3 PNGs in `docs/` | 3 saved at about 800 px wide |
