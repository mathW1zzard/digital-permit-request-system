# Known limitations and residual risk

| Limitation | Risk it leaves | Planned fix |
| --- | --- | --- |
| ~~`X-Demo-User` header instead of login~~ | Closed in Week 14 | Sanctum tokens (ADR-004) |
| HTTPS only with a self-made local certificate (development proxy; staging nginx since Week 16) | Browsers warn until the user accepts it | A real certificate on a real host (still open after Week 16) |
| Mock gateway; no real delivery | Real provider behaviour is untested | Contract tests against a provider sandbox |
| `--now` demo option on two commands | Could confuse operators if used in production | Remove, or restrict to non-production environments |
| Expiry can lag up to 5 minutes | Status shows `issued` briefly after lapse | `verify` already checks the clock; a shorter interval if needed |
| Escalation alerts once, with no repeats | An ignored alert stays ignored | Daily digest of open exceptions |
| Admin fixes data with tinker (e.g. a bad email) | Untracked manual change | An admin edit screen with an audit event |
| SLA counts clock hours, not office hours | Weekend escalations | Business calendar in `permit_types` |
| SQLite in development; staging uses MySQL on the same laptop (Week 16) | No failover; backups stay on the same disk | Off-machine backups; a separate database host |
| Outbox and event tables grow forever | Disk use over time | Scheduled archive of rows older than N days |
| ~~The scheduler itself is not monitored~~ | Closed in Week 16: `/api/health` shows a stale heartbeat | No alert is sent yet (see below) |
| ~~Unhandled-exception log lines include the raw exception message~~ | Closed in Week 14 | Only the class and location are logged |
| ~~`php artisan serve` answers one request at a time (Week 15, H2)~~ | Closed in Week 16: retested on 4 workers, 30/30 correct each | `php artisan serve` remains for development only |
| Permit list returns up to 100 rows with no paging (Week 15, H1) | Slow lists at higher volume (p95 114 ms measured at about 3,000 permits) | Add paging when volume grows |
| ~~`APP_DEBUG=true` in a deployment (Week 14, F1)~~ | Closed for staging in Week 16 (`APP_DEBUG=false`); development keeps it on | Use `deploy/staging/.env.staging.example` for any deployment |
| After a 409 the permit list is not reloaded | The list can show an old status until Refresh | Reload the list in `AppState.act` on failure too |
| No alerting (Week 16) | A `degraded` or failing system is noticed only when someone looks; S2 went unnoticed for hours | Alert on readiness 503/`degraded` and on `exception.unhandled` |
| Staging scripts are Windows-only (`php-cgi`, junctions) | Not reusable on a Linux host | PHP-FPM on Linux (Docker Compose) |
| Capacity about 53 requests/s on this laptop with 4 workers (Week 16, P6) | Response times grow above about twice the modelled peak | More workers sized to the CPU; PHP-FPM |
| Deploy takes 4–6 minutes, mostly `composer install` | Slow roll forward after a rollback | Build once, deploy the built folder |

Week 14 security risks are listed in `docs/week14/14-residual-risk-register.md`. Week 15 testing and hardening results are in `docs/week15/`, and Week 16 deployment and performance results in `docs/week16/`.

This is a bounded classroom prototype, demonstrated with fictional data, and not ready for production.
