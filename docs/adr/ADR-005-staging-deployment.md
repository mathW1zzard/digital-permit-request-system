# ADR-005 Staging deployment: release folders, nginx with PHP workers, MySQL

**Context:** Up to Week 15 the system only ran in development: `php artisan serve` (one request at a time), SQLite, debug on, and code edited in place. Week 16 must show whether it can be configured, measured, monitored and rolled back like a real deployment. Constraints:
- one Windows laptop with Laragon
- no administrator changes to the machine
- no paid services
- fictional data only

**Decision:**
- **Releases:** each release is exported from one Git commit into its own folder (`releases/<time>-<sha>`), installed without dev packages, and switched in by pointing a `current` junction at it. The previous release is kept, so rollback is a junction switch.
- **Serving:** nginx terminates TLS and serves both the Flutter Web build and `/api` from one origin. Requests go to four `php-cgi` workers under a restart loop.
- **Data and settings:** a private MySQL 8.4 instance holds the data. Settings and secrets live in one `shared/.env` generated for staging.
- **Scripts:** setup, deploy, rollback, backup, run and stop are in `deploy/staging/`.

| Option | For | Against | Verdict |
| --- | --- | --- | --- |
| Keep `php artisan serve` + SQLite | Nothing to set up | One request at a time (P4, P5 failed even the ordinary day); debug on; no backups | Rejected |
| nginx + `php-cgi` workers + SQLite | Isolates the effect of the web server | SQLite allows one writer at a time; no `mysqldump`-style online backup | Rejected (would not test H2 fairly) |
| **nginx + 4 `php-cgi` + MySQL, release folders** | Real concurrency; same database family as common hosts; online backups; rollback by switching folders; uses only what Laragon already has | Windows `php-cgi` needs a home-made supervisor and has defect-prone keep-alive (S1); fixed worker count | **Chosen** |
| Docker Compose (nginx, PHP-FPM, MySQL) | Closest to a Linux production host; PHP-FPM manages workers | Docker Desktop is not installed and needs administrator rights and a WSL2 setup on this laptop | Rejected for this week; recommended next |
| Apache from Laragon with mod_php | One process to run | Thread-safe PHP build needed; less common for Laravel; config shared with Laragon's own sites | Rejected |
| A cloud host (free tier) | Real network and certificate | Needs an account, payment details or a public repository with secrets handling; out of scope for controlled data | Rejected |

**Consequences:**
- **More moving parts:** five processes plus scripts. An operator must know the deploy, rollback and checklist steps (`33-environments-and-deployment.md`, `34-rollback-and-recovery.md`).
- **Secrets and paths:** secrets are separate per environment. Paths in `.env` are absolute, so they must be quoted when they contain spaces (S0: the first deploy failed on this), and the app must accept absolute paths (S2).
- **Deploy time:** about 4–6 minutes, mostly `composer install`. Rollback takes about 20 s.
- **Capacity:** about 53 requests/s on this laptop (P6). Capacity grows with the number of workers.
- **Restarts:** the deploy must restart every long-running PHP process, workers and scheduler (S3).

**Evidence:**
- runs P0–P6
- H2-parallel
- F8, F9
- RB-rollback-rehearsal
- S2 (in `evidence/week16/`)

**Review condition:** move to PHP-FPM on Linux (Docker or a VM) before any real users. Revisit the worker count when the partner office confirms its peak numbers.
