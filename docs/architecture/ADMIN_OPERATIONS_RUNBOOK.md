# Admin Operations Runbook

## Platform Admin entry point

Open `/admin` as a user with `platform_role = PLATFORM_ADMIN`.

## Common operations

- Official content overview: `/admin/official-content`.
- Assign Platform Admin role: `/admin/platform-users`.
- Suspend/reactivate workspace: `/admin/workspaces/{workspace}`.
- Review assessment lifecycle state: `/admin/assessment-oversight`.
- Revoke shared report link: `/admin/report-shares`.
- Review audit trail: `/admin/audit-logs`.
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
