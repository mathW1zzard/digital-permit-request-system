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
