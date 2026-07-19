> **ARCHIVED 2026-07-19.** Historical record. Accurate when written; describes a past state of the repository. Do not treat as current and do not rewrite. See `README.md` in this folder.

# Module 01 — Foundation

## Scope

Everything needed to have a running, deployable, testable skeleton:
- Laravel 13 project with PostgreSQL connection
- Laravel Breeze auth (register/login/logout/password reset)
- Workspace auto-creation on signup (transaction: user + workspace + workspace_member OWNER)
- BelongsToWorkspace global scope trait
- Laravel migrations as the schema source of truth
- All reference seeders (domains, modules, sub-indices, questions, platform_settings)
- Tailwind v4 base layout (authenticated + guest shells)
- Dashboard stub (workspace name + welcome message)
- No email verification gate (email.notifications_enabled = false by default)

## Deliverables

### Auth
- [x] Register → creates User + Workspace + WorkspaceMember(OWNER) in one transaction
- [x] Login → redirects to /dashboard
- [x] Logout
- [x] Password reset (link-based, no email gate)

### Database
- [x] All 42 tables migrated in correct FK order
- [x] Reference data seeded (domains, maturity_levels, assessment_tiers, target_types,
      question_types, respondent_roles, assessment_module_definitions)
- [x] sub_indices seeded (120 rows, v1.1 column names)
- [x] governed demonstration questions seeded through platform catalogue releases
- [x] question_options seeded
- [x] platform_settings seeded (email.notifications_enabled = false)

### Tenant isolation
- [x] BelongsToWorkspace trait (global scope + creating hook)
- [x] ResolveWorkspace middleware (sets app('current.workspace'))
- [x] All tenant models use BelongsToWorkspace

### UI
- [x] resources/css/app.css with @theme tokens (vytte-* color palette, Inter font)
- [x] Authenticated layout (sidebar navigation)
- [x] Guest layout (centered card)
- [x] Dashboard stub page

### Tests
- [x] WorkspaceIsolationTest — cross-workspace attack tests, all must fail
- [x] BelongsToWorkspaceTest — scope auto-applies, creating hook sets workspace_id
- [x] RegisterCreatesWorkspaceTest — signup transaction verified
- [x] Feature test: login, register, logout

## What is NOT in this module

- Project/Assessment/Response CRUD (Module 02+)
- Scoring engine (Module 05+)
- Reports (Module 07+)
- AI features (Module 09+)
- Payment/billing
- Email sending (built, toggled off)
