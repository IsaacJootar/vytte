# Admin Role and Permission Matrix

| Capability | Vytte Platform Admin | Workspace Admin |
| --- | --- | --- |
| Official departments/scopes | Manage | View only through published content |
| Facility profiles | Inspect/govern | No |
| Question groups | Manage | No |
| Reusable question identities | Create/inspect | No |
| Question versions | Approve/publish | No |
| Framework versions | Inspect/publish | No |
| Catalogue releases | Inspect/publish | No |
| Scoring profiles | Inspect/govern | No |
| Aggregation policies | Inspect/govern | No |
| Analytical-domain taxonomies | Inspect/govern | No |
| Platform roles | Manage | No |
| Workspace users | Oversight | Manage within workspace |
| Invitations | Oversight | Manage within workspace |
| Assessments | Oversight metadata | Create/run/manage within workspace |
| Respondents | Oversight metadata | Manage within workspace |
| Reports | Oversight metadata | Manage within workspace |
| Report share links | Emergency revoke | Create/revoke within workspace |
| Platform settings | Manage | No |
| Plan-feature definitions | Manage | No |
| Workspace settings | Oversight | Manage within workspace |
| Audit logs | Inspect | Limited through workspace activity where exposed |
| Workspace status (active / on hold / closed) | Manage | No |
| Account suspension and reactivation | Manage | No |
| Platform health and operational monitoring | View | No |
| Plan allocation and limit usage | View/manage | No |
| Workspace invitations | View through People | Manage own workspace |

## Guards on platform access

- An administrator cannot suspend their own account.
- The last active platform administrator cannot be suspended or demoted. Both guards exist so the platform cannot lock itself out of its own governance controls.
- Platform administrators are exempt from `EnsureWorkspaceIsActive`, so a suspended workspace remains reviewable.

See DEC-2026-07-19-021.
