# ADR-004 User authentication for the API

**Context:** Week 13 used an `X-Demo-User` header, which let anyone act as anyone. The client is a Flutter Web app in a browser (an untrusted, public client). Requests cross from the browser to the API.

**Decision:** Email and password sign-in at `POST /api/login`, checked against bcrypt hashes. On success the API issues a Laravel Sanctum personal access token that expires after 60 minutes (`AUTH_TOKEN_MINUTES`). Only a SHA-256 hash of the token is stored. The browser keeps the token in memory only and sends it as `Authorization: Bearer` over HTTPS. Sign-out deletes the token on the server. Five failed attempts per email and address within a minute block further attempts for that minute. Wrong password and unknown email get the same answer.

| Option | Strength | Weakness | Verdict |
| --- | --- | --- | --- |
| Keep the demo header | No work | No authentication at all | Rejected |
| Sanctum cookie session (SPA mode) | Token not readable by scripts | Needs same-site domains and CSRF setup; the API and app run on different ports | Rejected for now |
| **Sanctum bearer token in memory** | Simple, works across ports, revocable, expiring | A script injected into the page could read it; reload signs the user out | **Chosen** |
| Token in localStorage | Survives reload | Readable by any injected script for as long as it is valid | Rejected |
| JWT signed by the API | No database lookup | Hard to revoke before expiry; more ways to misuse | Rejected |

**Consequences:** Users sign in again after 60 minutes or a page reload. Revocation is immediate. The API must be served over HTTPS so tokens and passwords never travel in clear text. Demo accounts share one password from `DEMO_PASSWORD`, which is acceptable only because every account is fictional.

**Evidence:** W01–W06 and `SecurityTest`.

**Review condition:** Switch to cookie sessions if the app and API move to one domain, and add per-user passwords and a reset flow before any real users.
