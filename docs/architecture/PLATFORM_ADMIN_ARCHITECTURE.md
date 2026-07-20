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

## Application shell and navigation

Every role renders through `resources/views/layouts/app.blade.php`. `AppLayout` and `AdminLayout` are the same view with a different navigation array; only `App\Support\RoleNavigation` differs between roles. Spacing, mobile behaviour, theme handling and focus states are therefore defined once and cannot drift apart between roles. See DEC-017.

Platform Admin navigation is grouped by how often the work is done, not by the objects behind it:

- **Primary (always open):** Dashboard, Assessments, Question Library, Publishing.
- **Setup (collapsed):** Departments, Facility Types, Scores.
- **Oversight (collapsed):** Workspaces, People, Assessments in Use, Activity, Shared Reports, Plans, Usage, Platform Health, Settings.
- **Advanced Tools (collapsed):** Official Content, Frameworks, Question Versions, Question Groups, Measurement Areas.

Setup and Advanced Tools are collapsed by default so an administrator who never opens them never meets governance vocabulary.

A platform administrator signing in lands on `/admin`, not on a workspace dashboard. See DEC-016.

## Control-center areas

- `admin.dashboard` — attention-first overview: what is awaiting approval, ready to publish, or stalled.
- `admin.official-content.index` — official content control center.
- `admin.question-groups.*` — question-group management.
- `admin.question-identities.*` — reusable question identity creation and inspection.
- `admin.question-versions.*` — question-version approval and publication.
- `admin.framework-versions.*` — framework structure inspection and publication.
- `admin.catalogue-releases.*` — catalogue release inspection and publication.
- `admin.facility-profiles.*` — facility profile and department/service mapping inspection.
- `admin.scoring-policies.index` — scoring and aggregation policy visibility.
- `admin.domain-taxonomies.*` — analytical-domain taxonomy visibility.
- `admin.platform-users.*` — shown as **People**. Role assignment, account suspension and reactivation, per-person history.
- `admin.workspaces.*` — workspace oversight, health signals, and status control (active / on hold / closed).
- `admin.assessment-oversight.index` — shown as **Assessments in Use**. Read-only view of the assessments customers are running, as distinct from the templates Vytte authors under `admin.assessments.*`. See DEC-018.
- `admin.report-shares.*` — shown as **Shared Reports**. Share-link oversight and revocation.
- `admin.audit-logs.index` — shown as **Activity**. Immutable audit review, searchable by actor and filterable by category and date.
- `admin.monitoring.index` — shown as **Platform Health**. Database, queue, failed jobs, scheduler, storage and email delivery.
- `admin.plan-features.*` — plan feature controls, per-plan allocation, and limit usage.
- `admin.settings.*` — platform settings.

Screen names describe the question an administrator is asking rather than the table behind it, and storage vocabulary is never displayed as text. See DEC-018 and DEC-019.

## Operational controls

Workspace status and account suspension are enforced, not merely recorded:

- `EnsureWorkspaceIsActive` blocks use of a suspended or closed workspace. Platform administrators are exempt so they can still review it.
- `LoginRequest` blocks a suspended account, checked after credentials pass so a wrong password never reveals that an account is suspended.
- `SessionRevocationService` ends existing sessions on suspension. It requires `SESSION_DRIVER=database` and reports doing nothing on any other driver rather than pretending.

Nothing is deleted in any of these states. See DEC-020 and DEC-021.

## Immutability

Draft official content can be edited through governed flows. Published official question versions, framework versions, catalogue releases, assessment snapshots, respondent score snapshots, aggregation results, and final reports are immutable. Changes require a new version or a new assessment/report lifecycle event.
