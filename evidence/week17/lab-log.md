# Week 17 lab log

Kept by Claude Code (AI assistant), working at the student's request on the student's laptop. Times are UTC (Asia/Manila is UTC+8). The student had no testers available and asked for a simulated round; the student also asked to leave out the module's activity exercises and focus on the system.

| Time (UTC) | What happened |
| --- | --- |
| 2026-11-16 ~12:40–13:01 | Removed the module activity exercises from Weeks 14–16 (repository and Drive), at the student's request. Created branch `week17-uat`. Fixed the stale list after a 409 (widget test fails without the fix). Added `watch.sh` and the off-machine backup option. Wrote the UAT plan, cards and forms. Commit `5773932`. |
| 13:02–21:24 | Started staging and deployed `5773932`. The deploy ran for hours because the laptop slept during `composer install`; it finished at 21:24 with health 200. Staging had been started twice, so it was stopped and started once, cleanly. |
| 21:25–21:30 | Recreated the staging data, took the `uat-before` backup, tested the off-machine copy logic with a local folder (OneDrive was not used without the student's agreement), started the watcher. Readiness `ready`; smoke 168 requests, 0 errors. |
| 21:30–21:33 | **Round 1: 7/12.** Findings F-01 to F-04 written down before any fix. UAT-12 was blocked by TD8 (the UAT-10 step had expired every session). |
| 21:33–21:37 | Fixed F-01 to F-04 with 5 PHPUnit tests and a Flutter test (3 of the new PHPUnit tests fail without the fixes). Fixed TD8. Commit `07d9154`, deployed (done 21:44). Wrote the OpenAPI file and checked it against the routes. |
| 22:24–22:25 | **Round 2: 8/12**: F-01 to F-04 still failed although the release contained the fixes. Found S4: the workers kept serving the old code. Stopping every worker and starting them again made UAT-03 pass. |
| 22:26–22:32 | Fixed `workers.sh` (pause, stop all, wait for none, start). Commit `18408c1`. Verified both ways: a rollback served the old release (UAT-03 fails), and deploying forward `57fcd31` served the new one (UAT-03 passes). |
| 22:32–22:33 | Noticed that the `--skip-web` deploy had copied the web build of the rolled-back release. Put the `07d9154` build in place (same client code) and made `deploy.sh` refuse `--skip-web` when the client differs. |
| 22:34–22:35 | **Round 3: 12/12**, no alerts. Commit `27ec59d`. The student paused the work. |
| 2026-11-17 | At the student's request: reset GitHub's `week13-automation` to Week 13 only (local backup branch kept) and made `week16-deployment` the default branch. Then resumed: wrote the UAT report and the unsigned sign-off, updated the known limitations, and added the S4 correction to the Week 16 rollback plan. PHPUnit 45/45 twice; Flutter analyze clean, 6/6; OpenAPI matches the 16 routes. Copied the ops log (0 secret matches). |

## Notes

- Nothing was deleted from the evidence. Round 2 is kept and renamed to say it ran on stale workers.
- No ease-of-use rating, tester name or quote was invented. "Requester A", "Reviewer" and similar are role names.
- The UAT runner reads the staging password from an environment variable and never prints or saves it.
