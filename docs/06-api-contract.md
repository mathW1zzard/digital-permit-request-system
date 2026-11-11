# API contract

Base URL `https://localhost:8443/api` (HTTPS only since Week 14; plain HTTP gets 426). Every request sends `Accept: application/json`. After sign-in, every request also sends `Authorization: Bearer <token>`; the token comes from `POST /login` and expires after 60 minutes. A request may also send `X-Correlation-Id`, and every response returns one.

Success responses look like `{"data": ...}`. Error responses look like `{"error": {"code", "message", "details"?, "correlation_id"}}`.

| Method and path | Allowed | Body | Success | Errors |
| --- | --- | --- | --- | --- |
| POST `/login` | Anyone | `email`, `password` | 200 token, expires_at, user | 401 `invalid_credentials` (same for unknown email and wrong password), 422, 429 after 5 failures a minute |
| POST `/logout` | Signed-in user | - | 200; the token is deleted | 401 |
| GET `/me` | Any signed-in user | - | 200 id, name, role | 401 |
| GET `/permit-types` | Any | - | 200 list | 401 |
| GET `/permits` | Any; scoped: requester sees own, reviewer and approver see assigned, admin and inspector see all | - | 200 list, may be empty | 401 |
| POST `/permits` | Requester | `permit_type_id`, `purpose`, `location`, `details`?, `requested_start` | 201 permit | 401, 403, 422 |
| GET `/permits/{reference}` | Owner, assigned staff, admin, inspector | - | 200 permit with events and delivery states | 401, 403, 404 |
| POST `/permits/{reference}/request-revision` | Assigned reviewer | `comment` | 200 | 403, 409, 422 |
| POST `/permits/{reference}/resubmit` | Owner | `purpose`?, `details`? | 200 | 403, 409, 422 |
| POST `/permits/{reference}/endorse` | Assigned reviewer | - | 200 | 403, 409 |
| POST `/permits/{reference}/approve` | Assigned approver | - | 200 | 403, 409 |
| POST `/permits/{reference}/reject` | Assigned reviewer or approver | `reason` | 200 | 403, 409, 422 |
| GET `/permits/{reference}/verify` | Inspector, admin | - | 200 reference, status, valid, valid_until | 403, 404 |
| GET `/admin/exceptions` | Admin | - | 200 notifications and permits needing a person | 403 |
| POST `/admin/notifications/{id}/requeue` | Admin | - | 200 | 403, 404, 409 |
| GET `/up` (no `/api` prefix) | Anyone | - | 200, Laravel's built-in health check | - |

Week 17 asks for full OpenAPI documentation. This table is its starting point.

## Gateway message (Week 14)

`POST /send` on the gateway carries `template`, `reference` and `envelope` = `{v, kid, ts, nonce, ct}`. `ct` is the recipient sealed with XChaCha20-Poly1305; the version, key id, timestamp, nonce, `Idempotency-Key` header, reference and template are authenticated as associated data. The gateway answers 400 `invalid_envelope` (altered or wrong key), 400 `stale_timestamp` (outside 5 minutes) or 409 `replayed`. See ADR-003.
