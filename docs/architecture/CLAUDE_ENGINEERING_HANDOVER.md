# Claude Engineering Handover

Date: 2026-07-18

This handover captures the current architecture and the non-negotiable engineering rules Claude must preserve while finishing beta readiness.

## Current Architecture

### Platform Admin

Platform Admin governs official Vytte content and platform-wide operations. The control center lives under `/admin` and manages official content, question groups, question identities, question versions, framework versions, catalogue releases, facility profiles, scoring policies, plan features, users, workspaces, assessment oversight, report shares, audit logs, settings, modules, domain taxonomies, and geographic usage.

### Workspace Admin

Workspace Admin governs only their own workspace: users, invitations, assessments, respondents, reports, sharing, local sections, workspace custom assessments, and workspace settings. Workspace Admin must never modify official Vytte methodology or official benchmark content.

### Question Groups

Question Groups organize reusable question identities inside official departments or focused scopes. They replaced the obsolete `module_domains` architecture and are the required grouping primitive.

### Question Identities

Question Identities are stable reusable question concepts. They carry stable codes and belong to modules and question groups. They are not the immutable assessment payload by themselves.

### Question Versions

Question Versions are immutable once published, superseded, or archived. Draft question versions can be configured by Platform Admin with response type, options, option order, option scores, numeric limits, numeric bands, band labels, band scores, observation requirements, respondent role hints, methodology notes, source summary, review notes, and effective date.

Published question versions freeze exact wording, response type, options, numeric validation, numeric bands, methodology metadata, and content hash. Supersession creates a successor draft linked by lineage and leaves the old version reproducible for snapshots and reports.

### Frameworks

Framework Versions define official department or focused-scope assessment content. They contain sections, indicators, and exact placements of published question versions. Draft frameworks are editable. Published, superseded, and archived frameworks are immutable. Supersession clones the existing structure into a successor draft and preserves the original for historical reproducibility.

### Sections

Framework Sections are ordered groups within a framework version. They hold indicators and preserve report/runner grouping.

### Indicators

Framework Indicators sit inside framework sections and hold question placements. Indicator domain mappings provide analytical-domain routing unless a placement override is configured.

### Placements

Framework Question Placements attach exact published question versions to a framework section and indicator with display order, required state, scoring contribution, weight, criticality, evidence expectation, help text, local display text, and optional domain overrides.

### Domains

Analytical domains are governed through domain taxonomies and taxonomy versions. The platform must never reintroduce `module_domains`.

### Scoring

Scoring uses frozen snapshot payloads and the current scoring service contract. Option scores and numeric bands are part of published question-version configuration and are frozen into framework payloads and assessment snapshots. Open-ended questions are contextual and must not be scored.

### Snapshots

Assessment snapshots freeze catalogue release, facility profile, composition manifest, aggregation policy, collection config, and all module/question payloads used at creation time. Later official-content changes must not mutate existing assessment snapshots.

### Reports

Report snapshots are ordinary immutable Vytte reports. Once a report snapshot is created, it must not be updated or deleted independently. Shared reports must render from immutable report/snapshot data.

## Critical Rules

Claude must never violate these rules:

- Published content is immutable.
- Superseded content is immutable.
- Archived content is immutable.
- Snapshots freeze scoring and content configuration.
- Reports are immutable.
- Local workspace content never changes official benchmarks.
- Tenant isolation is mandatory.
- Server-side authorization is mandatory.
- Use Question Groups for official content organization.
- Never reintroduce `module_domains`.
- Never reintroduce Curator; use Platform Admin or Vytte Platform Admin.
- Community feedback, patient experience, citizen feedback, caregiver feedback, and similar work are assessment templates, not a separate reporting subsystem.
- Multi-respondent collection uses the ordinary assessment/scoring/reporting lifecycle and remains disabled unless a published immutable template version explicitly enables it.

## Important Files

Claude should read these first.

### Models

- `app/Models/QuestionGroup.php`
- `app/Models/Question.php`
- `app/Models/QuestionVersion.php`
- `app/Models/DepartmentFrameworkVersion.php`
- `app/Models/FrameworkSection.php`
- `app/Models/FrameworkIndicator.php`
- `app/Models/FrameworkQuestionPlacement.php`
- `app/Models/AssessmentCatalogueRelease.php`
- `app/Models/AssessmentSnapshot.php`
- `app/Models/AssessmentReportSnapshot.php`
- `app/Models/DomainTaxonomyVersion.php`
- `app/Models/WorkspaceCustomAssessmentDesign.php`

### Services

- `app/Services/QuestionVersionPublishingService.php`
- `app/Services/DepartmentFrameworkPublishingService.php`
- `app/Services/CataloguePublishingService.php`
- `app/Services/AssessmentCreationService.php`
- `app/Services/FrameworkContentService.php`
- `app/Services/ScoringService.php`
- `app/Services/ReportSnapshotService.php`
- `app/Services/MultiRespondentAggregationService.php`
- `app/Services/GovernanceDependencyService.php`
- `app/Services/WorkspaceCustomAssessmentService.php`

### Controllers

- `app/Http/Controllers/Admin/QuestionVersionController.php`
- `app/Http/Controllers/Admin/FrameworkVersionController.php`
- `app/Http/Controllers/Admin/CatalogueReleaseController.php`
- `app/Http/Controllers/Admin/OfficialContentController.php`
- `app/Http/Controllers/Admin/DomainTaxonomyController.php`
- `app/Http/Controllers/AssessmentController.php`
- `app/Http/Controllers/MultiRespondentAssessmentController.php`
- `app/Http/Controllers/ExportController.php`

### Policies and Middleware

- `app/Policies/QuestionVersionPolicy.php`
- `app/Policies/WorkspaceCustomAssessmentDesignPolicy.php`
- `app/Http/Middleware/EnsurePlatformAdmin.php`
- `app/Http/Middleware/SetCurrentWorkspace.php`

### Migrations

- `database/migrations/2026_07_18_000001_create_platform_governed_composition.php`
- `database/migrations/2026_07_18_000002_create_question_bank_architecture.php`
- `database/migrations/2026_07_18_000003_create_domain_taxonomy_architecture.php`
- `database/migrations/2026_07_17_000005_create_assessment_snapshots.php`
- `database/migrations/2026_07_17_000008_create_assessment_report_snapshots.php`
- `database/migrations/2026_07_17_000013_create_multi_respondent_scoring_contract.php`
- `database/migrations/2026_07_18_020000_add_governance_lineage_to_question_versions_and_catalogue_releases.php`

### Seeders

- `database/seeders/ReferenceDataSeeder.php`
- `database/seeders/PlatformGovernedDemoSeeder.php`
- `database/seeders/DemoDataSeeder.php`
- `database/seeders/SubscriptionPlanSeeder.php`

### Tests

- `tests/Feature/PlatformAdminControlCenterTest.php`
- `tests/Feature/QuestionBankArchitectureTest.php`
- `tests/Feature/PlatformGovernedCompositionTest.php`
- `tests/Feature/DomainArchitectureTest.php`
- `tests/Feature/MultiRespondentScoringTest.php`
- `tests/Feature/PlanFeatureTest.php`
- `tests/Feature/ResultsTest.php`
- `tests/Feature/ExportTest.php`

### Architecture Documents

- `docs/architecture/CURRENT_ARCHITECTURE.md`
- `docs/architecture/CURRENT_ASSESSMENT_FLOW.md`
- `docs/architecture/QUESTION_BANK_ARCHITECTURE.md`
- `docs/architecture/OFFICIAL_ASSESSMENT_CONTENT_LIFECYCLE.md`
- `docs/architecture/CONTENT_GOVERNANCE.md`
- `docs/architecture/DOMAIN_ARCHITECTURE.md`
- `docs/architecture/SCORING_CONTRACT.md`
- `docs/architecture/PLATFORM_ADMIN_ARCHITECTURE.md`
- `docs/architecture/WORKSPACE_ADMIN_ARCHITECTURE.md`
- `docs/architecture/PLAN_ARCHITECTURE.md`
- `docs/architecture/DECISION_LOG.md`
- `docs/architecture/GO_LIVE_CHECKLIST.md`

Historical records are in `docs/architecture/archive/`. They describe past states and must not be treated as current.

## Remaining Work

Priorities are:

1. Complete production operations hardening.
2. Complete Platform Admin content authoring for framework sections, indicators, placements, and catalogue composition.
3. Research and author the official Vytte content library under a governed review chain.
4. Implement plan-to-content entitlement.
5. Final beta verification.

### Completed since this handover was written

- The complete sequential test suite runs and passes. It had never been run end to end; the first run found five failures, all since fixed. Earlier green claims came from batched runs.
- Assessment snapshot immutability is enforced in code.
- Governance dependency counting is correct and bounded.

### Correction: the previous question bank

An earlier version of this handover listed "recover and adapt the previous approximately 500 questions" as a priority. That item is withdrawn as written.

It traced to the former `CLAUDE.md`, which described 528 questionnaire questions awaiting calibration and an HIVAW reference module. `archive/CURRENT_QUESTION_BANK_STATE_AUDIT.md` established that the PHSAI question bank was not migrated, copied, adapted, or reused, and neither `PHSAI` nor `HIVAW` appears anywhere in `app/` or `database/`.

Unless source material exists outside this repository, building the official library is **authoring, not recovery**. Treat any recovered material as a draft input that must pass the full review chain, and never inherit an unexplained score weight. Confirming whether such source material exists is a prerequisite to sizing the content work.
