# Architecture Decision Log

## Controlling Decision

### DEC-2026-07-18-001: Platform-Governed Assessment Composition

- **Status:** Accepted and implemented.
- **Decision:** Vytte owns official departments, department framework versions, facility profiles, catalogue releases, scoring methodology, aggregation policy, publication, versioning, and provenance.
- **Comprehensive Health Assessment:** implemented as a composition orchestrator. It resolves a facility profile, loads a published catalogue release, applies required/default/optional department policy, and freezes one immutable assessment snapshot.
- **Focused Health Assessment:** implemented as a single-scope catalogue release for one health domain, programme, topic, or intervention.
- **Catalogue rule:** the system must never resolve "latest framework version" at assessment creation time. A published catalogue release pins exact framework version IDs and hashes.
- **Snapshot rule:** every assessment freezes content, manifest, policy, collection config, scoring version, hashes, and exclusions.
- **Workspace rule:** workspaces consume approved Vytte content. They cannot publish official departments, official frameworks, official scoring, or official catalogue releases.
- **Local customization rule:** workspace-local custom sections may capture local context but cannot alter official content and never affect official scoring.
- **Reporting rule:** all use cases share the ordinary Vytte scoring and reporting architecture. No separate community, respondent, or local-section reporting subsystem exists.
- **Dataset rule:** current seeded catalogue content is demonstration-only and not production clinical methodology.

## Supporting Decisions

### DEC-2026-07-18-002: Production Database Authority

- **Status:** Accepted.
- **Decision:** PostgreSQL is the database authority for local development, automated tests, release verification, and production.
- **Release gate:** run migrations, full tests, and concurrency-sensitive checks against PostgreSQL before production release.

### DEC-2026-07-18-003: Two Creation Paths

- **Status:** Accepted and implemented.
- **Decision:** Vytte has exactly two creation paths: Comprehensive Health Assessment and Focused Health Assessment.
- **Boundary:** No unrelated bulk starter set, generic module picker, or giant comprehensive template is part of the product model.

### DEC-2026-07-18-004: Unified Respondent Architecture

- **Status:** Accepted and implemented.
- **Decision:** Community, patient, caregiver, citizen, and similar feedback use the same assessment lifecycle, scoring engine, reporting engine, permissions, exports, dashboards, and analytics.
- **Boundary:** Respondent role and template content may differ. Reporting architecture does not.

### DEC-2026-07-18-005: Inline Evidence Boundary

- **Status:** Accepted and implemented.
- **Decision:** Evidence is optional support attached to a question response. It is not a separate repository, workflow, or scoring input.

### DEC-2026-07-18-006: Response Type Publication Contract

- **Status:** Accepted and implemented.
- **Decision:** Official content may publish only response types with working UI, storage, validation, completeness, snapshot, reporting, and scoring or explicit unscored behavior.
- **Current publishable types:** scalar option, Likert, open text when unscored, and numeric with valid configuration.

### DEC-2026-07-18-007: Terminal Assessment Completion

- **Status:** Accepted and implemented.
- **Decision:** Assessment execution is `IN_PROGRESS -> COMPLETE`. Completion is terminal and creates the immutable report snapshot.
- **Future work:** reopening, correction versions, cancellation, or archive semantics require new approved lifecycle rules.

### DEC-2026-07-18-008: Demonstration Dataset

- **Status:** Accepted and implemented.
- **Decision:** Use a small labelled demonstration dataset to prove the architecture rather than inventing production clinical methodology.
- **Boundary:** production content requires governed source, licence, review, scoring, and publication.

### DEC-2026-07-18-009: Platform Admin UI and Demo Account Boundary

- **Status:** Accepted and implemented.
- **Decision:** Vytte Platform Admin uses the same application shell and visual language as the workspace app, with only a subtle sidebar color distinction to signal elevated platform authority.
- **Account boundary:** the demo Platform Admin account is not an Agency customer account; workspace plan labels must not be used as the visible authority label for Platform Admins.
- **UI rule:** Platform Admin users should see `Vytte Platform Admin` as their access label, while ordinary workspace users continue to see their configured paid-plan label.

### DEC-2026-07-18-010: Beta Licensing Without Payments

- **Status:** Accepted and partially implemented.
- **Decision:** Public beta uses configurable Starter, Professional, and Organization plan records with all features unlocked through plan configuration.
- **Boundary:** Payment processing, billing, subscriptions, invoices, provider webhooks, refunds, taxes, coupons, and ledgers are deferred until after beta.
- **Implementation rule:** Do not hardcode free access; use `subscription_plans`, `plan_features`, and `PlanService`.
- **Known drift:** `PlatformSettingController` still renders and persists `plan.free_projects`, `plan.free_assessments_per_project`, and `plan.pro_projects`. `PlanService` no longer reads them, so those controls have no effect. Resolving this requires a decision on whether to remove the controls; it is recorded here rather than silently corrected.

### DEC-2026-07-19-015: The Builder Retires A Version At Publication, Not At Clone Time

- **Status:** Accepted and implemented.
- **Context:** Advanced Tools supersedes a published framework version the moment its successor draft is created. In the builder that would pull a working assessment out of service as soon as an author began a correction, leaving nothing publishable until the new version was finished.
- **Decision:** "Create new version" clones the content into a draft and leaves the predecessor `PUBLISHED` and selectable. The predecessor, and the catalogue release pinning it, are moved to `SUPERSEDED` only when the successor is published.
- **Constraint:** only one open draft version per published assessment. A second attempt is refused so lineage stays a single line.
- **Preserved:** the predecessor keeps its frozen `published_payload` and `content_hash`. Assessments and reports already created are unaffected because their content lives in immutable snapshots. A superseded release can no longer start new assessments, which `AssessmentCreationService` already enforces.
- **Boundary:** Advanced Tools keeps its immediate-supersede behaviour for expert use. Both paths share one clone implementation in `AssessmentVersionService`; only the retirement timing differs.

### DEC-2026-07-19-013: Author Self-Approval Is A Temporary Beta Limitation

- **Status:** Accepted for beta. Requires a governance decision before production.
- **Context:** `CONTENT_GOVERNANCE.md` and the Phase A.5 review call for a review chain with distinct author, reviewer and approver. The assessment builder implements explicit, audited approval, but the same Platform Admin may author a question and approve it.
- **What is in place:** approval is never implicit. A question stays a draft through building, scoring and saving; a separate action approves it, routes through `QuestionVersionPublishingService` so every content check applies, freezes the version, and records `assessment.question.approved` with the acting user. Publication records `assessment.published`.
- **What is missing:** separation of duties. Nothing prevents one person performing both roles, and no reviewer identity distinct from the author is captured.
- **Why deferred:** a multi-person approval workflow is a governance design, not a UI change. Inventing one under beta time pressure risks a model that has to be replaced.
- **Boundary:** the audit trail already records who approved what, so a future policy can be applied without losing history. Do not describe current approval as independent review.

### DEC-2026-07-19-014: One Authored Assessment Publishes As One Focused Catalogue Release

- **Status:** Accepted and implemented.
- **Context:** what an author calls "an assessment" maps to two governed artifacts: a department framework version holding the content, and a catalogue release making it selectable. Publishing only the framework leaves nothing usable.
- **Decision:** the builder's Publish action publishes the framework and then creates and publishes one `FOCUSED` catalogue release pinning exactly that framework version, inside a single transaction. If either service refuses, the whole publication rolls back.
- **Rationale:** a focused release pins exactly one framework version and requires one health domain, which matches the author's mental model of one assessment.
- **Boundary:** comprehensive multi-department composition, facility profiles and multi-framework pinning remain Advanced Tools work and are unchanged.
- **Authority:** `DepartmentFrameworkPublishingService` and `CataloguePublishingService` remain the only authorities on whether publication may proceed. `AssessmentPublicationService` orchestrates and validates nothing itself.

### DEC-2026-07-19-011: Responses Are Keyed By Question Identity

- **Status:** Accepted as a description of existing behavior.
- **Context:** This record documents an existing implementation constraint that was previously undocumented. It introduces no new policy.
- **Behavior:** `responses` rows are keyed by `question_id`, the question identity, not by `framework_question_placement_id`. Scoring resolves each response by question identity against the frozen snapshot.
- **Consequence:** One question identity can appear at most once in a single assessment. `AssessmentCreationService::createFromCatalogue` rejects a composition containing duplicate question identities across the selected framework versions.
- **Boundary:** A question version may still be placed in multiple *frameworks* with different wording, weight, and analytical meaning. The constraint applies only within one composed assessment.
- **Trade-off:** A question cannot be answered separately per department within one comprehensive assessment. Distinct identities are required for that today.
- **Open question:** Whether to re-key responses to placements is not decided. Changing it would affect responses, both runners, both scorers, public session snapshots, and every existing report, and would be materially cheaper before a large content library exists than after.

### DEC-2026-07-19-012: Assessment Snapshot Immutability Is Enforced

- **Status:** Accepted and implemented.
- **Context:** This record documents an existing contract that is now enforced in code. It introduces no new policy.
- **Decision:** `AssessmentSnapshot` rejects updates through a model guard, matching `QuestionVersion`, `DepartmentFrameworkVersion`, `AssessmentCatalogueRelease`, and `AssessmentReportSnapshot`. Payload, composition manifest, aggregation policy, collection config, and content hash cannot be rewritten after creation.
- **Rationale:** The snapshot is the reproducibility authority. Silent mutation would falsify every downstream hash with no record of the original content and no way to detect or recover it.
- **Scope boundary:** Deletion is not guarded. The only real deletion path is the foreign-key cascade from `assessments`, which does not fire Eloquent events, and a missing snapshot already fails loudly in `ReportSnapshotService`. A delete guard remains a proposed follow-up.
- **Test contract:** Fixtures requiring different frozen content replace the snapshot instead of mutating it.
