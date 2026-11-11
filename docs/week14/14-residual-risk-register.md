# Week 14 – Threat summary and residual-risk register

## Threat summary

| Threat | Before Week 14 | Control now | Tested by |
| --- | --- | --- | --- |
| Someone acts as another user | Anyone could set `X-Demo-User` | Password login, expiring and revocable tokens | W01–W06 |
| Password guessing | Not applicable | Throttle (5 per minute per email and address), bcrypt | W03 |
| Learning which emails have accounts | Not applicable | Same answer for unknown email and wrong password | W02 |
| Eavesdropping on the network | Plain HTTP | HTTPS via TLS proxy; API refuses HTTP | W07 |
| Talking to a fake gateway | Not checked | Certificate verified against the dev CA; failure goes to a person | W09 |
| Changing a message to the gateway | Undetected | AEAD tag over payload and associated data | W08 |
| Replaying a captured message | Not checked | Timestamp window and nonce list | W08 |
| Reading free text from a copied database | Plain text | Encrypted casts | W10 |
| Secrets leaking through logs | Exception messages could include SQL and emails | Only class and location logged; secret scan | W11 |

## Residual risks

| ID | Risk | Likelihood | Impact | Why it remains | Owner | Next step |
| --- | --- | --- | --- | --- | --- | --- |
| R1 | A script injected into the web page reads the in-memory token | Low | High | Bearer tokens in a browser are readable by scripts on the same page | Developer | Content Security Policy; cookie sessions if the app and API share a domain |
| R2 | All demo accounts share one password | Medium | Low (fictional data) | Simplifies the demo | Developer | Per-user passwords and a reset flow before real users |
| R3 | Keys sit in a plain `.env` file | Medium | High | No secret store on a laptop | Operator | Host secret manager in deployment (Week 16) |
| R4 | The dev CA is trusted only by our code; browsers show a warning unless the user accepts it | High | Low | Local certificates only | Developer | A real certificate on a real host |
| R5 | The gateway's nonce list is a local file; wiping it during the 5-minute window allows one replay | Low | Medium | Mock gateway | Gateway operator | Persistent store with expiry |
| R6 | A stolen payload key lets the holder forge messages (shared key, no proof of sender) | Low | Medium | Symmetric design (ADR-003) | Operator | Rotate on suspicion; signatures if a third party must verify |
| R7 | Encrypted fields cannot be searched, and losing `APP_KEY` loses them | Low | High | By design | Operator | Back up `APP_KEY` separately from the database |
| R8 | No automatic deletion of old permits, tokens and logs | Medium | Medium | Not built yet | Developer | Scheduled clean-up with a retention period |
| R9 | Throttling is per email and address; many addresses could still guess slowly | Low | Medium | Basic rate limit | Developer | Account lock-out alerts |
| R10 | Plain HTTP still exists behind the proxy on 127.0.0.1 | Low | Low | Proxy design on one host | Developer | Bind the app servers to localhost only (already) and use TLS end to end in deployment |

This is a classroom prototype with fictional data. These controls reduce specific risks; they do not make the system secure in every situation, and it is not production-ready.
