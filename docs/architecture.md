# Vytte — Architecture

## Multi-tenancy model

Single-database, workspace-scoped rows. Every query on tenant data must filter by `workspace_id` first.

**No stancl/tenancy.** No subdomain routing. No separate databases per tenant.

Enforcement layers (all three required):
1. `BelongsToWorkspace` global Eloquent scope — auto-applied on every tenant model
2. Laravel Policies — authZ on every create/read/update/delete action
3. Cross-workspace attack tests — required, not optional

## Hierarchy

```
Workspace
  └── Project (v1: exactly one target per project)
        ├── Target (the health facility being assessed)
        └── Assessment (a single diagnostic run)
              ├── AssessmentModule (which modules are in scope)
              └── Response (one row per answered question)
                    └── score computed by scoring engine
```

### v1 constraints (hard)
- One project = one target
- `project_target_id` FK on projects points to single target
- `project_targets` (many-to-many junction) = v2 only, do not build in v1

### v2 additions (deferred — do not build now)
- `project_targets` junction table for multi-target projects
- `project_domain_scores` rollup table
- `project_scores` rollup table

## Workspace auto-creation

Every signup automatically creates a workspace and adds the user as OWNER.
This happens inside a single DB transaction in `RegisteredUserController`.
There is no "no workspace" state — ever.

```
User created → Workspace created → WorkspaceMember (OWNER) created → all in one transaction
```

## Workspace membership roles

Stored in `workspace_members.role` (enum):
- `OWNER` — full control, one per workspace
- `ADMIN` — manage members, settings; cannot delete workspace
- `MEMBER` — can run assessments, view reports

## Platform roles

Stored in `users.platform_role` (nullable enum):
- `PLATFORM_ADMIN` — full platform access (backoffice)
- `CURATOR` — can author/review question weights and questionnaire content
- `null` — normal workspace user

## BelongsToWorkspace trait

Applied via `use BelongsToWorkspace` on every tenant model.

The trait does two things automatically:
1. Registers a global Eloquent scope filtering `workspace_id = current_workspace_id()`
2. Hooks `creating` to set `workspace_id` from session if not already set

Models that use BelongsToWorkspace:
- Project, Target, Assessment, AssessmentModule, Response, Report
- WorkspaceMember, WorkspaceInvitation

Platform-level models (not tenant-scoped):
- User, Workspace, Domain (the 7 PHSAI domains), AssessmentTier,
  TargetType, TargetCategory, Topic, QuestionType, StandardsRegistry,
  AssessmentModuleDefinition, SubIndex, Question, QuestionOption,
  MaturityLevel, RespondentRole

## Request lifecycle

```
Request → ResolveWorkspace middleware → sets app('current.workspace')
        → Controller/Livewire component
        → Model query — BelongsToWorkspace scope auto-applies workspace_id filter
        → Policy check — verifies user is member of workspace
```

## Routing

No subdomain multi-tenancy. Workspace is resolved from the authenticated user's active workspace.

Route groups:
- `/` — public (marketing, login, register)
- `/dashboard` — authenticated workspace user routes
- `/admin` — platform admin routes (`platform_role = PLATFORM_ADMIN`)
- `/curator` — content curation routes (`platform_role IN (PLATFORM_ADMIN, CURATOR)`)

## Database

PostgreSQL (dev via Docker port 5433, production via VPS).
UUID primary keys everywhere (`HasUuids` trait).
Non-standard PK names are explicitly declared with `protected $primaryKey`.

See `docs/database.md` for full 42-table schema.

## Email

Resend is the provider. All outbound email is gated by:

```php
PlatformSetting::get('email.notifications_enabled', false)
```

This is OFF by default. Auth does not require email verification until the toggle is turned on.

## Scoring engine

7-stage pipeline: Response Normalization → Sub-Index Aggregation → Domain Aggregation →
Facility Health Score → Root-Cause Ranking → Recommendation Generation → Report Assembly.

Calibration rule: if a sub-index has no questions with non-null `score_weight`, the engine
returns `null` for that sub-index and flags it `calibration_status = NOT_CALIBRATED`.
It NEVER returns 0 or a fabricated score.

HIVAW module (9 questions) is the only fully-calibrated module in v1.
