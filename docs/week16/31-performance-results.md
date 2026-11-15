# Week 16 – Performance result sheet

Prepared by Claude Code (AI assistant) at the student's request, from the runs in `evidence/week16/P*`. Each folder has the following files:

- `00-run-info.txt`: commit, machine, health before and after
- `requests.csv`: every request
- `timeline.csv`: 10-second windows
- `summary.txt` / `summary.json`
- `resources.csv`: CPU and memory every 2 s

**Machine:** one laptop with 8 logical cores and 7.2 GB RAM, running Windows 11. Load generator and server ran on the same machine over loopback, so network latency is near zero and the generator competes for CPU (see Limitations).

## Environments

| | Development | Staging |
| --- | --- | --- |
| Web server | `php artisan serve` (one request at a time) behind the Node TLS proxy | nginx 1.28 (TLS) in front of 4 `php-cgi` workers |
| PHP | 8.4.12, OPcache off, debug on | 8.4.12, OPcache on, debug off, config and routes cached |
| Database | SQLite file | MySQL 8.4 (private instance) |
| Code | working copy of `week16-deployment` | release folder built from commit `3fc625a`, later `26f1ce7`/`f2ed55f`. Same application code for P1, P2 and P6 |

Full settings: `evidence/week16/E1-environment-comparison/01-settings.txt`.

## Results

Percentiles exclude sign-ins and the 20 s warm-up. "Errors" are 5xx, timeouts and network failures; there were no unexpected 4xx in any run.

| Run | Env | Model | Users | Requests | Throughput | Errors | p50 | p95 | p99 | Max | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| P1 | staging | W1 ordinary | 13 | 360 | 2.0/s | 0 | 79 ms | 119 ms | 130 ms | 137 ms | **Pass** |
| P2 | staging | W2 peak | 46 | 4,226 | 23.5/s | 0 | 75 ms | 119 ms | 167 ms | 319 ms | **Pass** |
| P4 | dev | W1 ordinary | 13 | 152 | 0.6/s | 2 (1.3 %) | 948 ms | 3,620 ms | 180,597 ms | 180,600 ms | **Fail** |
| P5 | dev | W2 peak | 46 | 357 | 1.8/s | 15 (4.2 %) | 17,907 ms | 29,669 ms | 30,021 ms | 30,024 ms | **Fail** |
| P6 | staging | W3 stress* | 46 | 6,475 | 53.3/s | 0 | 832 ms | 1,770 ms | 1,937 ms | 2,106 ms | 0 errors; p95 over the W2 limit |

\* For P6 the per-user limit was raised to 100,000 a minute in the live staging release, and restored to 60 straight after (both changes are in `ops.log`).

**Per-operation p95 on staging at the peak (P2):**

| Operation | p95 |
| --- | --- |
| list | 114 ms |
| detail | 103 ms |
| types | 83 ms |
| submit | 138 ms |
| endorse | 139 ms |
| approve | 145 ms |

All 189 endorsements and 95 approvals succeeded. There was no 409 between the 4 reviewers, because each reviewer acts only on their own permits.

**Sign-in.** p50 was 474 ms and p95 1,019 ms at the peak. bcrypt makes this slow on purpose.

**Prefill (W0).** 1,200 submissions from 40 users:
- staging: 27.6/s with 0 errors, after the S1 fix
- dev: 1.8/s, and only 589 permits created in the 5-minute cap

## Resource use

Averages over the run; machine CPU is all 8 logical cores.

| Run | Machine CPU (avg / max) | PHP workers | MySQL | nginx |
| --- | --- | --- | --- | --- |
| P2 staging peak | 42 % / 79 % | 20 % avg, 46 % max, 179 MB total | 8 %, 126 MB | 1 %, 19 MB |
| P6 staging stress | 61 % / 100 % | 44 % avg, 64 % max, 195 MB | 16 %, 179 MB | 2 %, 23 MB |
| P5 dev peak | 48 % / 99 % | the single `php` server ≈ 1 core | – | – |

## Interpretation

**Observed.**
- **Staging met both models with a wide margin.** At the peak its p95 was 119 ms, an eighth of the 1,500 ms limit, with no errors.
- **Development failed both models, even the ordinary day.** Its throughput stayed at about 1.8 requests/s however many users were waiting. Response time grew with the queue: p50 was 18 s at the peak, and requests timed out at the 30 s limit.
- **Staging's capacity is about 53 requests/s.** Under stress throughput stopped there. p95 rose to 1.8 s, but no request failed: requests waited in nginx's queue instead of being dropped.
- **At that point the four PHP workers used 44 % of the machine.** Four single-threaded processes can use at most 50 % of 8 cores, while MySQL used only 16 %.
- **The slowest moments of every staging run were the first 20 seconds,** when all users sign in at once (W2 window p95 734 ms). Each bcrypt sign-in occupies a worker for about half a second.

**Concluded.**
- **The bottleneck is the number of PHP workers, not the database or nginx.** Throughput stopped rising at the point where the four workers were nearly saturated.
- **The biggest single gain came from the execution model,** a multi-worker server instead of `php artisan serve`. MySQL and OPcache helped less.
- **The dev setup is unsuitable for anything but one person testing.** That was assumed in Week 15 and is now measured.
- **At the modelled peak, staging uses about 44 % of its capacity** (23.5 of 53 requests/s). That leaves room for roughly twice the modelled peak before response times exceed the W2 limit, on this hardware.
- **A sign-in storm is the most likely cause of a slow minute**: everyone arriving at 8 a.m., or everyone signing in again after a restart.

**Recommended.**
1. Size the worker count to the CPU cores: 6–8 `php-cgi` workers on this 8-core machine, then rerun W3.
2. On Linux, use PHP-FPM, which manages its own worker pool. This replaces the Windows `php-cgi` supervisor loop and removes defect S1 by design.
3. Keep bcrypt as it is, but note the sign-in storm in the operations guide. Staggered sign-ins and longer token lifetimes both reduce it.
4. Raise the notification dispatch rate (S2 follow-up): 50 a minute fell behind the W2 peak, which created about 180 notifications a minute. Staging now uses 200 (`GATEWAY_DISPATCH_BATCH`).
5. Add an endurance run of several hours at W1 before real use. It would show memory growth and table growth (H1, the permit list without paging), which three-minute runs cannot.

## Limitations of these measurements

- **One laptop, loopback network, load generator on the same CPU.** The generator used about 1–2 % CPU, so its effect is small, but real network latency is absent. Real users would see extra network time on top of every figure.
- **Each run happened once.** P2 and P6 were stable across their 10-second windows (P2 steady-state p95 was 105–126 ms), but there are no repeat runs to give a spread.
- **The dev runs started with requests still queued.** P4 began 12 s after the dev prefill, whose timed-out requests may still have been queued in `php artisan serve`. This makes P4 look worse than a clean start would. It does not change the verdict, since P5 failed after a fresh start.
- **TD7, a load-tool limitation:** two P4 requests were recorded as timeouts after 180.6 s, although the tool's timeout is 30 s. Node's request timeout measures idle time on the socket, not total time, so a request queued behind others can wait longer. The figures are reported as recorded.
- **Data volume was about 1,200–3,000 permits.** Lists are capped at 100 rows (H1), so list cost did not grow with volume in these runs. A larger table was not tested.
- **The mock gateway answers instantly.** A slow real provider would not slow down API requests, because delivery is asynchronous, but it would slow down how fast the outbox drains.
