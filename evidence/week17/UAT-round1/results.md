| Card | Role | Task | Result | Failed checks |
| --- | --- | --- | --- | --- |
| UAT-01 | Requester | Apply for a Facility Use Permit | **Pass** | – |
| UAT-02 | Requester | Submit a form with mistakes | **Pass** | – |
| UAT-03 | Reviewer | Endorse the application | **Fail** | timeline shows who endorsed (actual: {"type":"endorsed","from":"under_review","to":"pending_approval","at":"2026-11-16T21:30:45+00:00"}) |
| UAT-04 | Reviewer + Requester | Ask for a change, then resubmit | **Fail** | requester can read the reviewer's note (actual: not in the permit the requester receives) |
| UAT-05 | Approver | Approve and issue the permit | **Pass** | – |
| UAT-06 | Approver | Reject with a reason | **Fail** | requester can read the reason (actual: not in the permit the requester receives) |
| UAT-07 | Inspector | Check validity at the venue | **Pass** | – |
| UAT-08 | Two reviewers | Endorse the same application at once | **Fail** | the 409 says someone else acted (actual: This action is not available while the permit is under review.) |
| UAT-09 | Requester | Open someone else's permit; approve own permit | **Pass** | – |
| UAT-10 | Requester | Session ends, then sign in again | **Pass** | – |
| UAT-11 | Requester | Connection problem while submitting | **Pass** | – |
| UAT-12 | Admin | Handle a notification that needs a person | **Fail** | listed on the exceptions screen with a reason (actual: not listed within 2 min); requeue accepted (200) (actual: undefined); after the fix it leaves the list (actual: false) |
