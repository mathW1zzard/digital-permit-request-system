# Digital Permit Request System (PFEBSIT 002 finals project)

Individual finals project for PFEBSIT 002 (Weeks 13-18): a Flutter Web app with a Laravel backend. The course module is used as a guide for what each week adds. The whole project lives in this one repository, and each week is marked with a git tag.

- **Student:** Joseph R. Dionisio, BSIT 3A (solo)
- **Selected project:** #32 Digital Permit Request System
- **Backend:** Laravel 13 (PHP 8.3), SQLite
- **Frontend:** Flutter Web

## What it does

This project automates the approval and expiry cycle for permits in a small, controlled office setting. A requester submits a permit, and the system assigns it to the reviewer with the fewest open reviews. The reviewer can endorse it, request a revision or reject it. An endorsed permit goes to an approver, or to a senior approver for high-risk permit types, who approves or rejects it. An approved permit is issued with a validity period.

Scheduled jobs then send a reminder before a permit expires, mark lapsed permits as expired, and flag permits that have no available reviewer or approver or have passed their review or approval time limit (SLA). An inspector or admin can check whether a permit is still valid.

The backend validates every request, restricts actions by role and assignment (requester, reviewer, approver, inspector, admin) and records every state change in an audit trail and a structured log. Notifications use bounded retries. A notification that keeps failing or is rejected goes to the admin's Needs attention list for a person to handle, instead of retrying forever. Automation never approves or rejects a permit. All data is fake test data (sample permits and made-up names), with no real personal information.

Limits used in the demo: 4 attempts per notification, a 3 s timeout per attempt, waits of about 5 s, 10 s and 20 s between attempts, and a pause after 3 temporary failures in a row.

| Permit type | Valid for | Reminders before expiry | Review SLA | Approval SLA | Approver |
| --- | --- | --- | --- | --- | --- |
| Facility Use | 30 days | 7 days and 1 day | 24 h | 48 h | Any |
| Hot Work | 7 days | 2 days and 1 day | 8 h | 24 h | Senior |
| Minor Construction | 60 days | 14 days and 1 day | 48 h | 72 h | Senior |

## Weeks and tags

| Week | Tag | What was added | Status |
| --- | --- | --- | --- |
| 13 | `week13-sa7` | Backend API with roles and audit trail, approval routing, reminder and expiry automation (scheduler, outbox, bounded retries, manual handling), mock gateway, Flutter screens for all roles | Done |
| 14 | `week14-sa8` | Password login with expiring tokens, HTTPS with a local TLS proxy, encrypted and tamper-checked gateway messages, encryption at rest, security tests | Done on `week14-security`, tag not created yet |
| 15 | - | Integration testing and security hardening | Not started |
| 16 | - | Performance, reliability and deployment | Not started |
| 17 | - | Final integration, UAT and evaluation | Not started |
| 18 | - | Final review, demo and technical defense | Not started |

To see the project as it was in a given week, open the tag on GitHub, or run `git checkout week13-sa7`.

## Repository layout

```
backend/        Laravel API
tls-proxy/      Local HTTPS proxy and certificate script (Week 14)
client/         Flutter Web app
mock-gateway/   Fake notification service for failure testing (plain PHP)
docs/           Boundary map, diagrams, decision records, policies, API contract
evidence/       Evidence per week (evidence/week13/README.md is the index)
README.md
```

## How to run

You need Flutter (with Chrome), PHP 8.3 or newer with the `sodium` and `openssl` extensions, Composer, Node.js 18 or newer (only for the local HTTPS proxy) and Git. The commands are for Command Prompt (CMD). These steps worked on the developer's Windows PC with the project already set up; they have not been tested from a fresh clone.

**Once: certificates and keys** (from the repository root):

```
php tls-proxy\make-dev-certs.php
```

This creates `certs\` (git-ignored) with a local certificate authority and a certificate for `localhost`. Then in `backend\`:

```
composer install
copy .env.example .env
php artisan key:generate
php artisan gateway:key
```

Paste the printed key into `GATEWAY_PAYLOAD_KEY` in `backend\.env`, and set `DEMO_PASSWORD` to a password of at least 12 characters. All demo accounts use that password.

**Backend** (from `backend\`, first terminal):

```
php artisan migrate:fresh --seed
php artisan serve
```

If it asks whether to create the SQLite database, answer yes. The demo accounts are fake and are created by `backend\database\seeders\DatabaseSeeder.php`, for example `requester@permit.test`.

**Mock gateway** (from the repository root, second terminal). Use the values from `backend\.env`, in the form `key-id:key`:

```
set "GATEWAY_API_KEY=your-GATEWAY_API_KEY"
set "GATEWAY_PAYLOAD_KEYS=k1:your-GATEWAY_PAYLOAD_KEY"
php -S 127.0.0.1:9000 mock-gateway\router.php
```

**HTTPS proxy** (from the repository root, third terminal):

```
node tls-proxy\proxy.js
```

The API is now at https://localhost:8443 and the gateway at https://127.0.0.1:9443. The API refuses plain HTTP. Open https://localhost:8443/up once in Chrome and accept the warning (choose **Advanced → Proceed**), because the certificate comes from your own local authority, not a public one.

**Flutter Web app** (from `client\`, fourth terminal):

```
flutter pub get
flutter run -d chrome --web-port 5000 --dart-define=API_BASE_URL=https://localhost:8443/api
```

Use port 5000, because the API only accepts browser requests from the address in `FRONTEND_ORIGIN`. Sign in with a demo email and `DEMO_PASSWORD`. Sessions end after 60 minutes or when the page is reloaded.

**Automation** (from `backend\`, fifth terminal). This delivers notifications every minute and runs the reminder, expiry and escalation checks every 5 minutes:

```
php artisan schedule:work
```

To run them once by hand instead:

```
php artisan notifications:dispatch
php artisan permits:run-automation
php artisan gateway:tamper-test
```

The failure switches on the mock gateway (`POST /control`), the `--now` option and `gateway:tamper-test` are for demos only.

**Tests:**

```
cd backend && php artisan test
cd client && flutter test
```

## Configuration and secrets

The real `.env` file is never committed. Use `backend\.env.example` as the template. No API keys, passwords, tokens or real personal data belong in this repository, including screenshots and logs. Since Week 14, users sign in with a password and receive a token that expires after 60 minutes; the token is kept in browser memory only. Keys and the demo password live in `backend\.env`, and certificates in `certs\`; both are git-ignored. See `docs\week14\12-key-lifecycle.md`.

## Evidence

Evidence is listed in `evidence\week13\README.md` and `evidence\week14\README.md`, each with a lab log. Outputs and screenshots for scenarios S1-S10 are kept in that folder. Design documents are in `docs\`, and known limitations are in `docs\09-known-limitations.md`. This is a classroom prototype and is not production-ready.

## AI assistance and third-party code

I used Claude (Anthropic) as an AI assistant. A step-by-step build guide written with Claude gave the project its structure and starter code. Claude Code then generated the code, tests and documents in this repository, ran scenarios S1-S9, exported the diagrams and captured the S10 screenshots except the phone screenshot, which I took myself. These are recorded in the lab log. I directed the work, took the phone screenshot, set up the phone test, and published the repository to GitHub. The interpretation in the evidence index started from a draft by Claude.

Third-party code: the default Flutter and Laravel scaffolds, the Laravel framework and its default packages (via Composer, including Sanctum, installed for Week 14), the Flutter `http`, `provider` and `cupertino_icons` packages, and libsodium (bundled with PHP) for message encryption. The HTTPS proxy uses only Node.js built-in modules. The diagrams were rendered with Mermaid.
