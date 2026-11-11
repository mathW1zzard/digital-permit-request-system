# Known limitations and residual risk

| Limitation | Risk it leaves | Planned fix |
| --- | --- | --- |
| ~~`X-Demo-User` header instead of login~~ | Closed in Week 14 | Sanctum tokens (ADR-004) |
| HTTPS only through a local proxy with a self-made certificate | Browsers warn until the user accepts it; plain HTTP still runs behind the proxy on 127.0.0.1 | A real certificate on a real host (Week 16) |
| Mock gateway; no real delivery | Real provider behaviour is untested | Contract tests against a provider sandbox |
| `--now` demo option on two commands | Could confuse operators if used in production | Remove, or restrict to non-production environments |
| Expiry can lag up to 5 minutes | Status shows `issued` briefly after lapse | `verify` already checks the clock; a shorter interval if needed |
| Escalation alerts once, with no repeats | An ignored alert stays ignored | Daily digest of open exceptions |
| Admin fixes data with tinker (e.g. a bad email) | Untracked manual change | An admin edit screen with an audit event |
| SLA counts clock hours, not office hours | Weekend escalations | Business calendar in `permit_types` |
| SQLite, one server | Limited concurrency; no failover | Week 16 environment comparison (MySQL or PostgreSQL) |
| Outbox and event tables grow forever | Disk use over time | Scheduled archive of rows older than N days |
| The scheduler itself is not monitored | A stopped scheduler means no automation | Week 16: health signal on the last `automation.run_completed` |
| ~~Unhandled-exception log lines include the raw exception message~~ | Closed in Week 14 | Only the class and location are logged |
| After a 409 the permit list is not reloaded | The list can show an old status until Refresh | Reload the list in `AppState.act` on failure too |

Week 14 security risks are listed in `docs/week14/14-residual-risk-register.md`.

This is a bounded classroom prototype, demonstrated with fictional data, and not ready for production.
