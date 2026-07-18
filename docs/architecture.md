# Vytte Architecture

## Current Model

Vytte is a workspace-scoped Laravel monolith with platform-governed assessment content.

Vytte owns official:

- departments;
- department framework versions;
- facility profiles;
- assessment catalogue releases;
- questions, indicators, scoring rules, evidence requirements, and aggregation policies.

Workspaces consume published catalogue releases. They do not publish official content.

## Tenancy

Every tenant-facing query must resolve through the authenticated user's active workspace and membership. Projects and targets carry workspace ownership. Assessments inherit workspace authority through their project.

## Hierarchy

```text
Workspace
  Project
    Target
      FacilityProfile
    Assessment
      AssessmentSnapshot
        CompositionManifest
        Payload
        AggregationPolicy
      AssessmentModuleScope
      Responses
      Scores
      AssessmentReportSnapshot
      LocalCustomSections
```

## Platform Content Hierarchy

```text
AssessmentModule
  DepartmentFrameworkVersion

FacilityProfile
  FacilityProfileDepartment

AssessmentCatalogueRelease
  AssessmentCatalogueDepartmentVersion
```

## Creation Paths

1. **Comprehensive Health Assessment** resolves a facility profile and a published catalogue release, preloads required/default/optional departments, composes selected pinned framework versions, and freezes one assessment snapshot.
2. **Focused Health Assessment** resolves one published focused catalogue release and opens one health domain, programme, topic, or intervention.

No unrelated bulk starter set, generic module picker, or giant comprehensive template is part of the current architecture.

## Runtime Boundary

Assessment runners, scoring, reporting, exports, dashboards, and shared reports read the immutable assessment/report snapshots.

## Scoring

The current scoring algorithm is `vytte-4.0-numeric-bands` with canonical 0-100 output. Null means uncalibrated. Local custom sections are context only and excluded from official scoring.

## Verification

Current local verification:

- `php artisan test`: 395 tests, 972 assertions passing.
- Disposable PostgreSQL `migrate:fresh --seed`: passing.
- Production frontend build: passing.
