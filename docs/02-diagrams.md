# Diagrams

Exported PNGs: `diagram-state-machine.png`, `diagram-data-flow.png`, `diagram-retry-sequence.png`. Keep the source here so the diagram can be updated when the code changes.

## Permit state machine

```mermaid
stateDiagram-v2
  [*] --> under_review: submit (validated) / route to reviewer
  under_review --> revision_requested: reviewer requests revision
  revision_requested --> under_review: requester resubmits
  under_review --> pending_approval: reviewer endorses / route to approver
  under_review --> rejected: reviewer rejects
  pending_approval --> issued: approver approves / set validity
  pending_approval --> rejected: approver rejects
  issued --> expired: scheduler, valid_until passed
  rejected --> [*]
  expired --> [*]
```

## Data flow and trust boundaries

```mermaid
flowchart LR
  subgraph Browser["Browser (untrusted)"]
    UI[Flutter Web client]
  end
  subgraph Server["Laravel (trusted)"]
    API[API: auth stub, roles, validation]
    WF[PermitWorkflow + ApprovalRouter]
    DB[(SQLite: permits, events, outbox)]
    SCH[Scheduler: automation 5 min, dispatch 1 min]
    LOG[[storage/logs/permit.jsonl]]
  end
  GW["Notification gateway (external, mocked)"]
  ADM[Admin: Needs attention]
  UI -- "JSON + X-Demo-User (HTTPS in Week 14)" --> API
  API --> WF --> DB
  SCH --> WF
  DB --> SCH
  SCH -- "POST /send, Idempotency-Key, bounded retry" --> GW
  WF --> LOG
  SCH --> LOG
  DB -- "manual_action rows" --> ADM
```

## Notification retry sequence

```mermaid
sequenceDiagram
  participant API as Laravel API
  participant OB as Outbox table
  participant D as notifications:dispatch
  participant GW as Gateway
  API->>OB: insert row (dedupe_key, pending) in same transaction as status change
  D->>OB: claim row (attempts + 1, lease)
  D->>GW: POST /send (Idempotency-Key)
  GW-->>D: 503 transient
  D->>OB: next_attempt_at = now + backoff
  D->>OB: claim row again when due
  D->>GW: POST /send (same key)
  GW-->>D: 200 accepted
  D->>OB: status = sent
  Note over D,GW: 4xx such as 422 is permanent:<br/>status = manual_action, never retried
```
