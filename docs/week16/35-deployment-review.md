# Week 16 – Tabletop deployment review (Activity 2)

Prepared by Claude Code (AI assistant) at the student's request.

## The package under review

The **Week 15 release package**, tag `week15-ia6` (commit `18e198e`). This is what would have been handed over before Week 16. It contains:
- the source
- `backend/.env.example`
- a README with development run instructions
- the Postman collection and the Week 15 test evidence

The review asks whether an operator could put this into a controlled non-production environment and run it safely. If not, what blocks it, and in what order should it be fixed?

**Method.** Read the package as an operator who did not write it. Then check each finding against what actually happened when Week 16 deployed it to staging (`evidence/week16/`).

**Who accepts the result:** the system owner at the partner office, advised by the instructor. For this classroom project, the instructor.

## Findings

**B** = blocker (do not deploy). **C** = conditional (deploy only to non-production, with a named owner and a date). **N** = note.

| # | Finding in the package | Evidence | Class | Status after Week 16 |
| --- | --- | --- | --- | --- |
| 1 | **No deployment procedure at all.** The README says how to run it on a developer's laptop: `php artisan serve`, a Node proxy, a terminal per process. There are no release, backup or rollback steps | README; ADR list stops at ADR-004 | B | Fixed: `deploy/staging/*`, `33-…`, `34-…`, ADR-005 |
| 2 | **The only documented server handles one request at a time.** | P4 (ordinary day) p95 3.6 s; P5 (peak) p50 18 s, 4.2 % errors | B | Fixed for staging: nginx + 4 workers (P2 p95 119 ms) |
| 3 | **The web form cannot submit permits in a browser** (D8, CORS) | Found 2026-11-16 in Chrome | B | Fixed in `d566854` (after the tag) |
| 4 | **`.env.example` has `APP_DEBUG=true`, `APP_ENV=local`, `LOG_LEVEL=debug`.** Copying it as is gives debug pages | `git show week15-ia6:backend/.env.example` | B | Fixed for staging: `.env.staging.example`, `APP_DEBUG=false` (E1) |
| 5 | **No readiness signal.** `/up` only says the process answers. A stopped scheduler or database is invisible | F8: `/up` 200 while the database was down | B | Fixed: `/api/health` (6 tests; F8; S2 detection) |
| 6 | **No backups or restore procedure.** SQLite file only | – | B | Fixed for staging: `backup.sh`, restore rehearsed (20.6 s) |
| 7 | **Paths in `.env` are assumed relative** (`GATEWAY_CA_FILE=../certs/dev-ca.pem`). An absolute path silently broke all notifications | S2 | C (found only in staging) | Fixed in `26f1ce7`; unit test |
| 8 | **Long-running processes are not part of any deploy step.** Nothing says the scheduler must restart with a new release | S3 | C | Fixed in `deploy.sh` / `rollback.sh` |
| 9 | **PHP accepts 2 GB request bodies;** the app limits itself to 1 MB (Week 15 D7) | Week 15 F7 | C | Fixed for staging: `post_max_size` 2 M |
| 10 | **Notifications are sent 50 a minute;** the peak creates about 180 a minute | P2 counts; S2 backlog drain | C | Configurable; staging 200 |
| 11 | **Secrets:** one `DEMO_PASSWORD` for all demo accounts; gateway keys copied between environments | Week 14 risk register; `setup.sh` | C | Staging has its own `APP_KEY`, database and demo passwords; the gateway keys are still shared (mock only) |
| 12 | **TLS uses a self-made local CA;** every browser must accept a warning | Week 14 | C | Open: needs a real host and certificate |
| 13 | **No alerting:** nothing turns a signal into a message | S2 went unnoticed for hours | C | Open (recommendation in `32-…`) |
| 14 | **Permit list has no paging** (H1) | Measured list p95 114 ms at about 3,000 permits | N | Open: fine at this volume |
| 15 | **Scripts are Windows-specific** (Git Bash, junctions, `php-cgi`) | ADR-005 | N | Open: a Linux / Docker version is recommended |

**Adverse case considered in the review:** an operator deploys the package as documented and the laptop's database file is lost. The package gives no way back: no backup, no procedure. Finding 6 is therefore a blocker, not a note, even though no data is real.

## Two ways to close the blockers

| Approach | For | Against | Decision |
| --- | --- | --- | --- |
| **Written manual runbook** (copy files, edit `.env`, run artisan by hand) | Quick to write; no scripts to maintain | Every deploy is different; steps get skipped (S3 is exactly a skipped step); no automatic backup or health gate | Rejected |
| **Scripted releases with a health gate** (Week 16 `deploy/staging`) | Same steps every time, logged; backup before every deploy; rollback is one command and rehearsed | Scripts are code that can have defects (S0, S1, S3 were all in the scripts or their config); Windows-only | **Chosen** |

## Prioritised improvement plan

Ranked by risk reduced per hour of work, most important first:

1. **Done in Week 16:** blockers 1–6 and conditionals 7–10 (see the table).
2. **Alerting on readiness and on `exception.unhandled`.** S2 showed that a correct signal nobody watches is worth little. About 2 hours with a small polling script.
3. **Move backups off the machine** and test a restore elsewhere. About 1 hour.
4. **Linux deployment with PHP-FPM** (Docker Compose): removes the home-made supervisor and the S1 class of problem. About 1 day.
5. **A real host and certificate** for the browser trial in Week 17 (UAT). This depends on the instructor's approval of a host.
6. **Build once, deploy many:** run composer and Flutter in a build step, ship the folder. Cuts deploy and roll-forward from about 4 minutes to under 1. About 2 hours.
7. Paging on the permit list (H1) before data volume grows beyond what was measured.

## What this review cannot show

- It was done by the same assistant that built the Week 16 changes, not by an independent operator.
- The partner office's real hosting constraints (machines, network, who has administrator rights) are not known, so the plan's later items are estimates.
