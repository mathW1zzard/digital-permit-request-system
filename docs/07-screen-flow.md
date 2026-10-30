# Flutter Web screen flow

| Screen | Who | Leads to | States it must show |
| --- | --- | --- | --- |
| Sign in (pick a demo account or type an email) | Everyone | Permits | Validation, loading, 401 unknown user, network error |
| Permits list | Everyone, scoped by role | Detail; New permit (requester) | Loading, empty, list, server or network error with Retry |
| Permit detail: a side pane at 900 px and wider, its own page when narrower | Everyone allowed | Action dialogs | Loading, 403, 404, timeline, delivery state per notification |
| Action dialog (revision comment, rejection reason, resubmit note) | Reviewer, approver, requester | Detail refreshes | Validation, 409 message, success message |
| New permit form | Requester | Detail of the new permit | Field validation, server 422 errors on the fields, submitting |
| Needs attention tab | Admin | Requeue | Loading, empty ("Nothing needs attention"), list, Requeue result |
