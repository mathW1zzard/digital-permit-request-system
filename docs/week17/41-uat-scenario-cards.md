# Week 17 – UAT scenario cards

One card per task. Give the tester only the **Task** part. The **Pass when** part is for the observer. All names, places and dates are fictional.

---

### UAT-01 · Requester · Apply for a permit
**Task:** Sign in as the requester. Apply for a **Facility Use Permit** for a basketball clinic at the Barangay Covered Court, starting next Saturday.
**Pass when:** the permit appears in your list with a reference (PRM-…) and the status **under review**, and a reviewer is shown on it.

### UAT-02 · Requester · Make mistakes on the form
**Task:** Start a new application. Leave the purpose almost empty ("test") and pick a start date more than a year away, or none at all. Press Submit.
**Pass when:** each wrong field shows a message saying what to fix, and no new permit appears in your list.

### UAT-03 · Reviewer · Endorse an application
**Task:** Sign in as the reviewer. Find the basketball clinic application and endorse it.
**Pass when:** its status becomes **pending approval**, and the timeline shows the endorsement with your name.

### UAT-04 · Reviewer and requester · Ask for a change
**Task (reviewer):** For a second application, ask the requester to change it ("Please give the exact time").
**Task (requester):** Open that application, see what was asked, and resubmit it with the change.
**Pass when:**
- after the request, the status is **revision requested** and the requester can read the reviewer's note
- after the resubmission, the status is back to **under review**

### UAT-05 · Approver · Issue the permit
**Task:** Sign in as the approver. Approve the basketball clinic permit.
**Pass when:** the status is **issued** with a **valid until** date, and the requester sees the same in their list.

### UAT-06 · Approver · Reject an application
**Task:** Reject another application, giving the reason "Venue already booked".
**Pass when:** the status is **rejected**, and the requester can read the reason. The approver cannot reject without a reason.

### UAT-07 · Inspector · Check a permit at the venue
**Task:** Sign in as the inspector. Check whether the basketball clinic permit is valid, then check a permit that is not issued.
**Pass when:** the issued permit shows **VALID until** its date, and the other shows **NOT VALID** with its status.

### UAT-08 · Two reviewers · Act at the same moment
**Task:** Two people (or two browser windows) open the same application and both press **Endorse** at about the same time.
**Pass when:**
- one succeeds
- the other gets a message that someone else already acted
- both lists show the same, current status without pressing Refresh
- there is one endorsement in the timeline

### UAT-09 · Requester · Try something not allowed
**Task:** As a requester, try to open another requester's permit by its reference, or try to approve your own permit.
**Pass when:** the system refuses with a clear message, and nothing changes.

### UAT-10 · Any user · Session ends
**Task:** Work normally. After the session expires (the observer forces it), press Refresh.
**Pass when:** the app says **your session ended**, returns to sign-in, and work already submitted is still there after signing in again.

### UAT-11 · Requester · Connection problem while submitting
**Task:** Fill in an application. The observer cuts the connection to the server; press Submit. The observer restores it; press Submit again.
**Pass when:**
- the first attempt shows a message that stays on the form, and your entries are kept
- the second attempt creates **exactly one** permit

### UAT-12 · Admin · Something needs a person
**Task:** Sign in as the admin. Open the exceptions screen and handle the item listed there (the observer prepares one: a notification the gateway refused).
**Pass when:** the item is listed with its reason, and after the cause is fixed and you requeue it, it leaves the list.
