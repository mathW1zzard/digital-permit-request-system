# ADR-001 Trigger pattern

**Context:** Routing must happen as soon as a permit changes state. Reminders, expiry and SLA checks depend on the clock. One student maintains the system, and it runs on a laptop.

**Decision:** Hybrid. State changes route and queue notifications inside the API request's database transaction (event-driven). Laravel's scheduler re-checks the clock-based rules every 5 minutes and delivers due notifications every minute.

| Option | Strength | Weakness | Verdict |
| --- | --- | --- | --- |
| Scheduler only | One mechanism | Routing waits up to 5 minutes; most runs find nothing | Rejected |
| Queue jobs only (delayed job per expiry) | Low latency | Needs an always-on worker; a 30-day delayed job is lost if the queue is cleared; harder for an admin to see | Rejected |
| Hybrid: event for state changes, scheduler for time rules | Immediate routing; self-healing time rules | Two mechanisms to explain and test | Chosen |

**Consequences:** Routing is immediate. A missed scheduler run catches up on the next one, because each run re-checks every rule. Expiry can lag up to 5 minutes, so the verify endpoint also compares `valid_until` with the current time and never reports a lapsed permit as valid. The scheduler must be running (`schedule:work` locally, cron in deployment).

**Review condition:** Revisit if expiry must be exact to the minute, or if the volume makes 5-minute scans slow.
