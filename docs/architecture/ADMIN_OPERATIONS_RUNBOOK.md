# Admin Operations Runbook

## Platform Admin entry point

Open `/admin` as a user with `platform_role = PLATFORM_ADMIN`.

## Common operations

- Official content overview: `/admin/official-content`.
- Assign Platform Admin role: `/admin/platform-users`.
- Suspend or restore a person: `/admin/platform-users/{user}`. A reason is required and is shown to them at sign-in.
- Check platform health: `/admin/monitoring`.
- Review plan allocation and limit usage: `/admin/plan-features`.
- Suspend/reactivate workspace: `/admin/workspaces/{workspace}`.
- Review assessments customers are running: `/admin/assessment-oversight` (shown as Assessments in Use).
- Revoke shared report link: `/admin/report-shares`.
- Review the audit trail: `/admin/audit-logs` (shown as Activity).
- Manage plan features: `/admin/plan-features`.
- Manage platform settings: `/admin/settings`.

## Publication operations

- Approve and publish question versions through `/admin/question-versions`.
- Publish framework versions through `/admin/framework-versions`.
- Publish catalogue releases through `/admin/catalogue-releases`.

## Pending Platform Admin operations

- Framework sections are currently inspectable but not yet fully create/edit/reorder-manageable through polished UI.
- Framework indicators are currently inspectable but not yet fully create/edit/reorder-manageable through polished UI.
- Framework question placements are currently inspectable but not yet fully place/replace/weight/reorder-manageable through polished UI.
- Version comparison and dependency graph views are pending.
- Deeper payment-provider operations dashboards are pending.

## Safety checks

- Never edit a published immutable object in place.
- Use a new version for methodology changes.
- Keep Platform Admin and Workspace Admin boundaries separate.
- Verify audit logs after consequential administrative actions.
- Run the full sequential test suite before finalizing work. Batched runs are acceptable for fast feedback during work, but they have hidden real failures and do not evidence a passing suite.

## Removing and restoring access

Workspace status is set from `/admin/workspaces/{workspace}`:

| State | Effect | Reversal |
| --- | --- | --- |
| Active | Normal use | — |
| On hold | Members are blocked; existing sessions end immediately | Reactivate at any time |
| Closed | Same block, presented as the end of the relationship | Support decision, not one click |

Account suspension is set from `/admin/platform-users/{user}` and requires a reason. Suspension blocks sign-in, ends existing sessions, and notifies the person. Nothing is deleted in any of these states.

Two guards apply: an administrator cannot suspend their own account, and the last active platform administrator cannot be suspended or demoted.

## Sharing links while email is off

Email notifications are off by default. Invitations, respondent links and report share links are therefore delivered by hand:

- Workspace invitations: `/team`. Each pending invite shows its link permanently with copy and WhatsApp. "New link" replaces the token, so a link shared with the wrong person stops working.
- Respondent links: the assessment's respondent collection screen.
- Report share links: the assessment results screen.

Turning email on is a single setting at `/admin/settings`. Check Platform Health first: a configured Resend transport with no `RESEND_API_KEY` will fail at send time rather than at configuration time.
