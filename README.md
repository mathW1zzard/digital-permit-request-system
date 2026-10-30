# Digital Permit Request System (PFEBSIT 002 finals project)

Individual finals project for PFEBSIT 002 (Weeks 13-18): a Flutter Web app with a Laravel backend. The course module is used as a guide for what each week adds. The whole project lives in this one repository, and each week is marked with a git tag.

- **Student:** [Your full name], [Your course, year and section] (solo)
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
| 13 | `week13-sa7` | Backend API with roles and audit trail, approval routing, reminder and expiry automation (scheduler, outbox, bounded retries, manual handling), mock gateway, Flutter screens for all roles | Done, tag not created yet |
| 14 | - | Secure data exchange and encryption | Not started |
| 15 | - | Integration testing and security hardening | Not started |
| 16 | - | Performance, reliability and deployment | Not started |
| 17 | - | Final integration, UAT and evaluation | Not started |
| 18 | - | Final review, demo and technical defense | Not started |

To see the project as it was in a given week, open the tag on GitHub, or run `git checkout week13-sa7` once the tag exists.

## Repository layout

```
backend/        Laravel API
client/         Flutter Web app
mock-gateway/   Fake notification service for failure testing (plain PHP)
docs/           Boundary map, diagrams, decision records, policies, API contract
evidence/       Evidence per week (evidence/week13/README.md is the index)
README.md
```

## How to run

You need Flutter (with Chrome), PHP 8.3 or newer, Composer and Git. The commands are for Command Prompt (CMD). These steps worked on the developer's Windows PC with the project already set up; they have not been tested from a fresh clone.

**Backend** (from `backend\`):

```
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

If it asks whether to create the SQLite database, answer yes. The API runs at http://localhost:8000. The demo accounts are fake and are created by `backend\database\seeders\DatabaseSeeder.php`, for example `requester@permit.test`.

**Mock gateway** (from the repository root, in a second terminal). Use the same key as `GATEWAY_API_KEY` in `backend\.env`:

```
set "GATEWAY_API_KEY=your-key-from-backend-env"
php -S 127.0.0.1:9000 mock-gateway\router.php
```

**Flutter Web app** (from `client\`, in a third terminal):

```
flutter pub get
flutter run -d chrome --web-port 5000 --dart-define=API_BASE_URL=http://localhost:8000/api
```

Use port 5000, because the API only accepts browser requests from the address in `FRONTEND_ORIGIN`.

**Automation** (from `backend\`, in a fourth terminal). This delivers notifications every minute and runs the reminder, expiry and escalation checks every 5 minutes:

```
php artisan schedule:work
```

To run them once by hand instead:

```
php artisan notifications:dispatch
php artisan permits:run-automation
```

The failure switches on the mock gateway (`POST /control`) and the `--now` option on those two commands are for demos only.

**Tests:**

```
cd backend && php artisan test
cd client && flutter test
```

## Configuration and secrets

The real `.env` file is never committed. Use `backend\.env.example` as the template. No API keys, passwords, tokens or real personal data belong in this repository, including screenshots and logs. Week 13 has no real login: the `X-Demo-User` header is a stand-in, and anyone can use it to act as any user. Real login is planned for Week 14.

## Evidence

Evidence is listed in `evidence\week13\README.md`, with the lab log in `evidence\week13\lab-log.md`. Outputs and screenshots for scenarios S1-S10 are kept in that folder. Design documents are in `docs\`, and known limitations are in `docs\09-known-limitations.md`. This is a classroom prototype and is not production-ready.

## AI assistance and third-party code

I used Claude (Anthropic) as an AI assistant. A step-by-step build guide written with Claude gave the project its structure and starter code. Claude Code then generated the code, tests and documents in this repository, ran scenarios S1-S9, exported the diagrams and captured the S10 screenshots except the phone screenshot, which I took myself. These are recorded in the lab log. [Add what you changed, reviewed and tested yourself, and use your institution's required wording. Keep this truthful.]

Third-party code: the default Flutter and Laravel scaffolds, the Laravel framework and its default packages (via Composer, including Sanctum, installed for Week 14), and the Flutter `http`, `provider` and `cupertino_icons` packages. The diagrams were rendered with Mermaid.
