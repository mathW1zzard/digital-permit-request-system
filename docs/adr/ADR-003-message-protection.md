# ADR-003 Protecting messages to the gateway

**Context:** Notifications leave our system for a separate gateway. The message carries a recipient email, the one piece of personal data the gateway needs. TLS protects the wire, but the message passes through a proxy and lands in the gateway's own logs and queues. We must detect any change, refuse replays, and keep keys outside source code. One student maintains both ends.

**Decision:** Seal the recipient with libsodium's XChaCha20-Poly1305 (authenticated encryption). The version, key id, timestamp, nonce, idempotency key, permit reference and template go in as *associated data*: they stay readable for routing and logging, but changing any of them breaks the tag. Every attempt uses a fresh random 24-byte nonce and the real clock. The receiver rejects messages older or newer than 5 minutes and remembers nonces for 10 minutes. The 32-byte key comes from `.env`, with a key id so a new key can be introduced without downtime.

| Option | Strength | Weakness | Verdict |
| --- | --- | --- | --- |
| TLS only | Already in place | No protection once the message is inside the proxy or gateway; no replay protection | Rejected as the only control (kept as a layer) |
| HMAC-SHA256 signature, plaintext body | Simple; detects changes | The email is still readable in every log and queue | Rejected |
| AES-256-GCM | Standard AEAD | 12-byte nonce, so random nonces risk reuse at volume; nonce reuse is catastrophic | Rejected in favour of XChaCha20 |
| **XChaCha20-Poly1305 (libsodium)** | AEAD, 24-byte nonce safe to pick at random, built into PHP | Shared key (no proof of which holder sent it) | **Chosen** |
| Public-key signature plus encryption (e.g. Ed25519 + X25519) | Proof of sender; no shared secret | Key distribution, revocation and more code for a two-party link we control | Rejected for Week 14 |

**Consequences:** Retries cannot be confused with replays because each attempt is newly sealed, and duplicates are still prevented by the idempotency key. The receiver's nonce list must survive restarts for the replay window. Clocks must be within 5 minutes. Losing the key stops delivery until a new key is set on both sides. A stolen key lets the holder forge messages, so the key is rotated on suspicion (see the key lifecycle plan).

**Evidence:** `php artisan gateway:tamper-test` (W08) and `SecurityTest::test_envelope_detects_altered_data_and_wrong_keys`.

**Review condition:** Revisit if a third party must verify who sent a message (use signatures) or if the gateway becomes a real provider with its own security scheme.
