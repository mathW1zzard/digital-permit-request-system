# Week 14 – Control selection rationale and misuse warnings

## Which control does what

| Control | Reversible? | What it gives | Used here for | Misuse warning |
| --- | --- | --- | --- | --- |
| Password hashing (bcrypt) | No | Lets the server check a password without storing it | User passwords | Never use a fast hash (SHA-256, MD5) for passwords: it lets an attacker try billions of guesses per second. Never encrypt passwords: anyone with the key reads them all. |
| Plain hash (SHA-256) | No | A fingerprint of data | Stored form of access tokens (Sanctum); throttle keys | A hash alone does not prove who made it: anyone can recompute it. |
| Message authentication code / AEAD tag | No (it is a check) | Proves the message was not changed and came from a key holder | Inside XChaCha20-Poly1305 for gateway messages | A MAC with a shared key cannot prove *which* key holder sent it (no non-repudiation). Compare tags in constant time. |
| Authenticated encryption (XChaCha20-Poly1305) | Yes, with the key | Confidentiality and integrity together | The gateway recipient, bound to reference and template | Never reuse a nonce with the same key; never accept decrypted data when authentication fails; encryption alone (for example AES-CBC without a MAC) does not detect changes. |
| Laravel encrypted cast (AES-256-CBC + HMAC) | Yes, with APP_KEY | Confidentiality and integrity at rest | Permit details, comments, reasons | Encrypted columns cannot be searched or sorted; losing APP_KEY loses the data; it does not protect data the app itself shows. |
| Digital signature | No (it is a check) | Integrity plus proof of the signer, verifiable by anyone with the public key | Not used (see ADR-003) | Needs key distribution and revocation; overkill for one sender and one receiver we both control. |
| TLS | Yes (per session) | Confidentiality and integrity on the wire, server identity | Browser to API, API to gateway | Disabling certificate verification "to make it work" removes the server-identity check entirely. |

## Activity 1: five proposed uses, reviewed

| # | Proposal | Objective | Problem | Corrected design | Operational assumptions |
| --- | --- | --- | --- | --- | --- |
| 1 | Store passwords encrypted with AES so admins can recover them | Confidentiality of passwords | Reversible: whoever holds the key (or steals it) reads every password; admins should never know passwords | bcrypt hashes; a reset flow instead of recovery | Cost factor tuned for the server; reset links expire |
| 2 | Hash the gateway message with SHA-256 and send the hash along, to detect tampering | Integrity | An attacker who changes the message just recomputes the hash | A keyed check: the AEAD tag (or HMAC-SHA256) with a secret key | The key is shared only by sender and receiver and rotated when exposed |
| 3 | Encrypt the recipient with AES-CBC so the gateway "can trust it" | Confidentiality and integrity | CBC without a MAC is malleable and gives no integrity; a static IV leaks patterns | XChaCha20-Poly1305 with a random 24-byte nonce per message, reference and template as associated data | Random-number source is sound; keys stay out of source code |
| 4 | Put the API token in the URL query (`?token=…`) for simplicity | Authentication | URLs end up in logs, browser history and proxy logs | `Authorization: Bearer` header over HTTPS, token kept in memory, short expiry, revoked on sign-out | Server logs never record headers |
| 5 | Encrypt the whole permits table so the data is "safe" | Confidentiality at rest | Breaks search and sorting, and the app holds the key anyway; protects little against an attacker inside the app | Classify first: encrypt only free-text fields that may hold personal data; minimize what is collected | Disk and backup protection handled by the host; APP_KEY backed up separately |

**Interpretation:** the common mistake is choosing a mechanism before naming the objective. A hash, a MAC and encryption each answer a different question, and only a keyed check proves integrity against someone who can change the message.
