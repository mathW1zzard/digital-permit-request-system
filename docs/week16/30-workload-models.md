# Week 16 – Workload models (Activity 1)

Prepared by Claude Code (AI assistant) at the student's request. Times in Week 16 documents are UTC unless marked otherwise.

## Decision question

What load must the permit API carry for a barangay office, and how do we show, with numbers, whether a given environment carries it?

## Users and journeys

All accounts are fictional: 40 requesters and 4 extra reviewers from `LoadTestUsersSeeder`, plus the seeded reviewers and approvers. Each virtual user signs in once and then repeats its journey until the run ends:

| Role | Journey (weights) | Why |
| --- | --- | --- |
| Requester | list my permits 40, open one 35, submit a new one 15, load permit types 10 | Most requester time is spent checking status. Submissions are the expensive write. |
| Reviewer | endorse a permit waiting for them 60, list 40 | Endorsing always starts with listing, so a reviewer action is two requests. |
| Approver | approve a permit waiting for them 60, list 40 | The same pattern as the reviewer. |

Submissions send an `Idempotency-Key`, like the web form. A 409 on endorse or approve, which means another user acted first, counts as a correct answer and is reported separately.

## The two models

| | W1 Ordinary office day | W2 Peak deadline |
| --- | --- | --- |
| Scenario | A normal hour at the permit desk, compressed | The last hour before a deadline, such as permits for a fiesta weekend |
| Users active at once | 10 requesters, 2 reviewers, 1 approver | 40 requesters, 4 reviewers, 2 approvers |
| Think time between actions | 4–10 s (reading, typing) | 1–3 s (people hurrying) |
| Expected rate | about 2 requests/s | about 20–25 requests/s |
| Ramp-up / warm-up / measured | 20 s / 20 s (excluded) / 180 s | 20 s / 20 s (excluded) / 180 s |
| Data volume at start | about 1,200 permits (prefill W0), growing during the run | the same, plus what W1 added |
| Pass criteria | errors ≤ 1 %; p95 ≤ 500 ms for reads and all requests; p95 ≤ 1,000 ms for writes | errors ≤ 2 %; p95 ≤ 1,500 ms for reads and all requests; p95 ≤ 2,500 ms for writes |

Files: `perf/models/ordinary.json`, `perf/models/peak.json`. **W0** (`prefill.json`) is not a measurement: it creates 1,200 permits through the API, roughly two busy months, so measured runs don't start from an empty database. **W3** (`stress.json`) is a capacity search, not a realistic office: 46 users with 0–100 ms think time and the per-user limit raised for that one run.

**Why these limits.** A desk officer notices a wait of over half a second on an everyday screen, so W1 uses 500 ms. At a deadline, people tolerate a slower system but not failures, so W2 allows a higher p95 but still at most 2 % errors. Sign-ins are excluded from the percentiles, because bcrypt makes them deliberately slow (about 0.5 s), and reported separately.

**Why each user stays under 60 requests a minute.** The API limits each signed-in user to 60 requests a minute (Week 15, D6). One user acting every 1–3 s makes about 30. So W2 tests the system, not the limiter. A test of one user in a loop would only measure the limiter, which is already tested in F6.

## What each model can and cannot prove

| | Can show | Cannot show |
| --- | --- | --- |
| W1 | That everyday use is comfortable; the baseline response time of each operation; that nothing fails at low load | Behaviour at a deadline; capacity; long-running effects (memory growth, table growth) |
| W2 | Whether a realistic burst at four times the users and four times the pace still meets the targets; whether races between reviewers cause errors; whether background work keeps up | Where the limit is (W3 does that); behaviour over a full day (no endurance run); behaviour across many machines or real networks (everything ran on one laptop over loopback) |

**Assumptions to confirm with the partner office:** the peak user count (40 requesters at once is a guess for a busy barangay), the share of submissions (15 %), and the hours of a deadline rush. If the office's real numbers are higher, rerun W2 with them; the model file is the only thing to change.

## Tools considered

| Option | For | Against | Decision |
| --- | --- | --- | --- |
| **Own Node script** (`perf/loadgen.mjs`, built-in modules only) | Nothing to install (npm installs failed here in Week 15); knows the API's sign-in, roles and idempotency; writes per-request CSV and percentiles | Single process, so it cannot generate very high load; I wrote it, so it can have its own defects (TD6, TD7) | **Chosen** |
| k6 or JMeter | Standard tools with good reporting | Must be downloaded and installed (k6 binary, Java for JMeter); scripting the role-based journeys takes as long as writing the script | Rejected for this week; a good choice for a real CI pipeline |
| Apache `ab` (included with Laragon) | Already on the laptop | One URL, no sign-in flow, no think time; measures a request, not a user | Rejected |

Results of the runs: `31-performance-results.md`.
