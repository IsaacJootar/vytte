# Platform Admin Architecture

Vytte has two administration levels: **Vytte Platform Admin** and **Workspace Admin**.

## Vytte Platform Admin

Vytte Platform Admins govern official platform-wide methodology and operations. This role is stored as `users.platform_role = PLATFORM_ADMIN` and is enforced by `EnsurePlatformAdmin`.

Platform Admins control:

- official departments and focused scopes;
- facility profiles and valid department/service mappings;
- question groups;
- reusable question identities;
- immutable question versions;
- framework versions, sections, indicators, and question placements;
- catalogue releases;
- scoring profile metadata and aggregation policies;
- analytical-domain taxonomies and mappings;
- publication, supersession, archival, and governance workflows;
- platform users and platform roles;
- workspace oversight and workspace suspension/reactivation;
- report share-link oversight and emergency revocation;
- platform settings, plan-feature controls, payment/webhook settings, and audit/compliance review.

## Workspace Admin

Workspace Admins govern only their workspace. They may manage workspace users, invitations, projects, assessments, respondents, reports, report sharing, local sections, workspace custom assessments, and workspace settings.

Workspace Admins must never modify official Vytte content, methodology, scoring policy, catalogue releases, analytical-domain taxonomies, or platform roles.

## Control-center areas

- `admin.dashboard` — platform overview and quick access.
- `admin.official-content.index` — official content control center.
- `admin.question-groups.*` — question-group management.
- `admin.question-identities.*` — reusable question identity creation and inspection.
- `admin.question-versions.*` — question-version approval and publication.
- `admin.framework-versions.*` — framework structure inspection and publication.
- `admin.catalogue-releases.*` — catalogue release inspection and publication.
- `admin.facility-profiles.*` — facility profile and department/service mapping inspection.
- `admin.scoring-policies.index` — scoring and aggregation policy visibility.
- `admin.domain-taxonomies.*` — analytical-domain taxonomy visibility.
- `admin.platform-users.*` — Platform Admin role assignment.
- `admin.workspaces.*` — workspace oversight and status control.
- `admin.assessment-oversight.index` — assessment lifecycle/artifact oversight.
- `admin.report-shares.*` — governed share-link oversight and revocation.
- `admin.audit-logs.index` — immutable audit review.
- `admin.plan-features.*` — plan feature controls.
- `admin.settings.*` — platform settings.

## Immutability

Draft official content can be edited through governed flows. Published official question versions, framework versions, catalogue releases, assessment snapshots, respondent score snapshots, aggregation results, and final reports are immutable. Changes require a new version or a new assessment/report lifecycle event.
