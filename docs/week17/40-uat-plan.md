# Week 17 – User acceptance test plan

Prepared by Claude Code (AI assistant) at the student's request. Student: Joseph R. Dionisio, BSIT 3A.

## Purpose

Decide, with evidence, whether the Digital Permit Request System does what its users need for a supervised trial with fictional data. UAT asks "is this the right system for the people who use it?". The tests of Weeks 13–16 asked "does the system work as built?".

## How this round was run: simulated testers

No partner-office staff or classmates were available. At the student's request, **this round uses simulated testers**: Claude Code played each role by following the scenario cards against the staging system.

- **What a simulated round can show:** each workflow works end to end through the real API on staging, with the real role rules, error messages and data, and every acceptance criterion that can be checked by an observable result.
- **What it cannot show:** whether real people understand the screens, how long they take, where they hesitate, and whether the workflow matches how the barangay office really handles permits.

Ease-of-use ratings were not invented: those columns are marked **"not assessed (simulated)"**. Any tester names in the results are role names, not people.

The plan, cards and forms below are written for **real** testers, so the same kit can be used for a real round later (see "Next round").

## Roles and decision maker

| Role | Who (simulated round) | Who (real round, recommended) |
| --- | --- | --- |
| Requester ×2 | Claude Code as "Requester A" and "Requester B" | A barangay clerk or resident, or non-technical volunteers |
| Reviewer | Claude Code as "Reviewer" | Barangay staff who check applications |
| Approver | Claude Code as "Approver" | The barangay official who signs permits |
| Inspector / admin | Claude Code | The student (support roles) |
| Observer | Claude Code (records results) | The student; does not help unless a tester is stuck, and records it if so |
| **Acceptance authority** | **The instructor** decides on the simulated round | The barangay contact, with the instructor |

## Environment and data

- **Where:** staging, `https://localhost:8444`, on the student's laptop.
- **Release:** the commit named in the evidence index, deployed with `deploy/staging/deploy.sh`.
- **Health:** `/api/health` must show `ready` before the session starts.
- **Data:** a freshly reset database, the fictional demo accounts, and the permits created during the session. No real names, addresses or contact details.
- **Monitoring:** `deploy/staging/watch.sh` runs during the session and writes alerts to `shared/logs/alerts.log`.
- **Backup:** `backup.sh uat-before` is taken before the session.

## Entry criteria (all must hold before testing starts)

1. PHPUnit, Flutter analyze and Flutter tests pass on the release.
2. Staging deployed from that release; `/api/health` is `ready`; the smoke run has 0 errors.
3. The backup is taken; the watcher is running.
4. The cards and forms are printed or shared; the testers have heard the fictional-data note.

## Acceptance criteria

Each scenario has a measurable pass condition (cards in `41-uat-scenario-cards.md`). The round is decided on these:

| ID | Criterion | Measure | Target |
| --- | --- | --- | --- |
| AC-U1 | Core workflow works end to end | Scenarios UAT-01, 03, 05, 07 pass | 4 of 4 |
| AC-U2 | Mistakes are caught with a clear message and nothing wrong is saved | UAT-02, 04, 06 pass | 3 of 3 |
| AC-U3 | People cannot do what their role does not allow | UAT-09 passes | 1 of 1 |
| AC-U4 | Problems while using it do not lose work or create duplicates | UAT-08, 10, 11 pass | 3 of 3 |
| AC-U5 | Exceptions reach a person who can act | UAT-12 passes | 1 of 1 |
| AC-U6 | No severity-1 finding is open | Findings log | 0 |
| AC-U7 | Ease of use rated 4 of 5 or better by each tester (real round only) | Feedback form | Average ≥ 4, none below 3 |

**Severity of findings:**
- **1:** blocks a task, loses data, or exposes data.
- **2:** the task is possible but wrong or confusing.
- **3:** cosmetic or a wish.

**Decision rule:**
- **Accepted:** all criteria met.
- **Accepted with conditions:** AC-U1 to AC-U6 met, with severity-2 findings that have a dated fix.
- **Rejected:** any severity-1 finding is open, or AC-U1 fails.

## After the session

1. Record every finding with its severity and a disposition: fix now / later / won't fix, with a reason.
2. Fix the "fix now" findings, and retest the scenario that found each one, plus the scenarios around it.
3. Write the UAT report: results, findings, retests and the decision. The acceptance authority signs the sign-off page (`42-uat-forms.md`).

## Next round (real testers)

Repeat with the same kit as soon as testers are available. A real round adds what this one cannot:
- the AC-U7 ease-of-use ratings
- time per task
- observed hesitation
- the office's view of whether the workflow matches its process
