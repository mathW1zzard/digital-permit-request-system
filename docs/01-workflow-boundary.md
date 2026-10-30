# Workflow boundary map

**Decision question:** How should the permit system route approvals and handle reminders, expiry and delivery failures automatically, while a person stays in charge of every approval and every exception?

| Element | Project 32 |
| --- | --- |
| Actors | Requester, reviewer, approver, senior approver, inspector, admin (owns exceptions), system scheduler |
| Event triggers | Submit, resubmit, request revision, endorse, approve or reject a permit through the API |
| Time triggers | Scheduler: `permits:run-automation` every 5 minutes, `notifications:dispatch` every minute |
| Trusted inputs | Server-validated requests from a signed-in user; `permit_types` rules (validity, reminder days, SLA, senior approval); server clock |
| Untrusted inputs | Everything from the browser; every response from the notification gateway |
| Processing | Validate → create → route to reviewer → notify → review → route to approver → approve and issue → remind → expire |
| Outputs | Permit status, audit events, queued notifications, structured log lines, admin Needs attention list |
| External dependency | Notification gateway (mocked in Week 13) |
| Human approval points | Reviewer endorsement, approver decision, admin handling of every exception. Automation never approves or rejects. |
| Operational owner | Admin role. The student maintains the code. |
| Trust boundaries | Browser → API; API and scheduler → gateway |
| Non-goals (Week 13) | Real email or SMS, real login and HTTPS (both Week 14), attachments, payments, drafts, renewals, inspector check-ins beyond the read-only verify endpoint, multi-office routing |
