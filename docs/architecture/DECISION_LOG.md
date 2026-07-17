# Architecture Decision Log

## Status definitions

- **Pending Isaac**: no implementation is authorized.
- **Accepted**: Isaac explicitly approved the decision.
- **Rejected**: Isaac explicitly declined the decision.
- **Superseded**: a later accepted decision replaces it.

Accepted items below record Isaac's explicit direction; all others remain implementation gates. This log records decisions raised by documentation/implementation discrepancies and the recommended safest default.

## Decision register

### DEC-021-001: Establish the executable baseline

- **Status:** Accepted with preservation constraint, 17 July 2026
- **Context:** `master` is one commit ahead of remote and contains uncommitted multi-module, school-module, scoring, UI, and test changes.
- **Options:** accept current worktree as baseline; baseline only committed `HEAD`; quarantine selected changes on a separate branch.
- **Decision:** Preserve the entire current worktree as the Phase 22 executable baseline. Do not discard, revert, or overwrite its uncommitted work. The committed anchor remains `c190e419108bb2483febcea755fe30c13b7348fe`; Phase 22 documentation and characterization tests remain visibly separate in Git status until Isaac chooses a commit boundary.
- **Trade-off:** Accepting current worktree captures intended progress but mixes unverified core-flow changes; using `HEAD` is more reproducible but omits current work.
- **Implementation gate:** No template implementation or broad cleanup during Phase 22.

### DEC-021-002: Supported database matrix

- **Status:** Accepted, 17 July 2026
- **Context:** Product instructions require PostgreSQL, while local, example, and test configurations use SQLite.
- **Options:** PostgreSQL only; SQLite unit/feature tests plus required PostgreSQL parity suite; SQLite as fully supported runtime.
- **Decision:** PostgreSQL remains the production authority. SQLite is temporarily permitted for desktop development and testing because the local Docker service is unavailable. PostgreSQL parity verification remains mandatory when the environment is available.
- **Trade-off:** SQLite speeds local tests but hides PostgreSQL behavior, especially nullable uniqueness and constraints.

### DEC-021-003: Project-to-target canonical relationship

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Documentation says one target per project; schema is many-to-many; UI uses the first target.
- **Options:** enforce one target using the existing junction; introduce an explicit primary target; embrace multi-target projects.
- **Recommendation:** Preserve `project_targets`, enforce one attached target for legacy flows, and postpone multi-target product behavior. Add an explicit primary-target concept only if a future approved workflow needs it.
- **Trade-off:** This avoids destructive schema work while removing ambiguous `first()` semantics.

### DEC-021-004: Disposition of current multi-module assessment work

- **Status:** Accepted with direction, 17 July 2026
- **Context:** Uncommitted code changes the core one-module flow to a multi-module default/exclusion workflow.
- **Options:** accept as current legacy flow; revert/quarantine; retain only behind a feature flag.
- **Decision:** Preserve the current work as the starting prototype for the comprehensive path. It must not become the focused path. Phase 22 must characterize target compatibility, public-runner behavior, scoring, exports, and comparison semantics before the prototype is treated as stable.
- **Trade-off:** Multi-module selection is useful for comprehensive assessments but currently exceeds the verified baseline.

### DEC-021-005: Assessment lifecycle vocabulary

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** `status` and `publish_status` overlap; code uses `COMPLETE`, documentation uses `COMPLETED`, and documented draft/archive states differ.
- **Options:** keep two independent state machines; consolidate; formalize lifecycle and publication separately.
- **Recommendation:** Keep existing stored values and formally define separate execution and publication state machines. Add adapters/validation rather than renaming values.
- **Trade-off:** Two state dimensions add complexity but avoid destructive data migration.

### DEC-021-006: Server-side completeness enforcement

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** UI hides submit until scored questions have option responses, but direct POST completes an unanswered assessment.
- **Options:** preserve permissive submit; enforce every scored question; define applicability-aware completion rules.
- **Recommendation:** Characterize existing data, then enforce snapshot/applicability-aware completion server-side. Do not use a simplistic all-question rule while unsupported response types remain.
- **Trade-off:** Strict enforcement protects data quality but can strand legacy or partially supported assessments.

### DEC-021-007: Public respondent semantics

- **Status:** Superseded by DEC-021-020, 17 July 2026
- **Context:** The earlier design treated external respondent results as a product concern outside the normal assessment report.
- **Reason superseded:** Any dedicated aggregation or reporting path adds unnecessary product and maintenance complexity. Respondent role must be expressed inside the existing assessment template, scoring profile, and report.

### DEC-021-008: Canonical score scale and algorithm versioning

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Current seed values mix 0-1 and 0-100 while bands/maturity expect 0-100; algorithms have no version identity.
- **Options:** normalize all values to 0-100; store 0-1 and scale display; allow per-template scoring profiles.
- **Recommendation:** Define a 0-100 canonical output with versioned scoring profiles. Preserve legacy rows and never silently recalculate completed assessments.
- **Trade-off:** Versioning requires more metadata but is necessary for historical reproducibility and diverse templates.

### DEC-021-009: Template snapshot strategy

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Completed assessments currently reference mutable master content.
- **Options:** deep-copy all content; immutable version references; hybrid snapshot of presentation/scoring-critical fields plus version references.
- **Recommendation:** Hybrid: immutable template/version records plus an assessment-owned normalized snapshot containing exact module composition, question/option text, ordering, applicability, evidence rules, response types, and scoring profile/version.
- **Trade-off:** Deep copy is safest but heavy; references are efficient but unsafe if any referenced row can mutate; hybrid balances reproducibility and reuse.

### DEC-021-010: Question/module reuse semantics

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Shared content is edited in place, but future templates require immutable published versions.
- **Options:** copy records into each template version; revision every reusable asset; freeze composition snapshots only.
- **Recommendation:** Keep mutable master assets for drafting, materialize immutable published template-version composition, and snapshot new assessments. Do not retroactively version legacy records.
- **Trade-off:** Some duplication is intentional to preserve history.

### DEC-021-011: Health taxonomy and terminology

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Global scoring domains, module-local sections, departments, settings, topics, and future health domains overlap in language.
- **Options:** rename legacy structures; map a new controlled taxonomy to legacy structures; continue informal labels.
- **Recommendation:** Add a controlled taxonomy and mapping layer in Phase 23; do not rename legacy tables/columns.
- **Trade-off:** Mapping adds a compatibility layer but avoids breaking existing content and reports.

### DEC-021-012: Content governance and curator authority

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** CURATOR exists conceptually but only platform admins can edit content; audit logging is inactive.
- **Options:** admin-only governance; curator role with review/publish separation; workspace-level authors.
- **Recommendation:** Define platform-admin and curator responsibilities with draft/review/publish separation and audit events before Phase 24 publishing.
- **Trade-off:** Separation slows publishing but protects clinical/standards integrity.

### DEC-021-013: Report reproducibility

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Reports are regenerated from mutable content and current views; no report records exist.
- **Options:** rely on assessment snapshot and regenerate; persist report data snapshots; persist final files only.
- **Recommendation:** Persist a structured report snapshot/content hash at finalization and allow file regeneration from that snapshot. Keep existing routes.
- **Trade-off:** Structured snapshots use storage but support audit, localization, and future formats better than file-only retention.

### DEC-021-014: Evidence product boundary

- **Status:** Accepted, 17 July 2026
- **Context:** Primary handoff describes broad evidence linkage; v2 refines evidence to optional question support and rejects a separate repository workflow.
- **Options:** full evidence repository; inline optional evidence only; no evidence.
- **Decision:** Follow v2: progressively disclosed `Add evidence` attached to a response/question, with governed privacy; no standalone normal-assessment repository.
- **Trade-off:** This limits capability intentionally to preserve a simple assessment experience.

### DEC-021-015: Supported response types for first template release

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Schema names many types, but runners implement only scalar option selection.
- **Options:** templates may use all declared types; publish only runner-supported types; implement all types before templates.
- **Recommendation:** Phase 24 validation should initially publish only proven types. Extend response renderers/storage separately, beginning with text/numeric and then true multi-select.
- **Trade-off:** Narrow initial templates preserve reliability and simplicity.

### DEC-021-016: Dormant schema ownership

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Many migrated tables have no active model/service/UI and may contain unknown production data.
- **Options:** adopt them for roadmap features; deprecate them; leave reserved pending production verification.
- **Recommendation:** Mark as reserved and read-only until production schema/data and original intent are verified. New designs must not assume they are empty or reusable.
- **Trade-off:** May lead to parallel new tables later, but prevents semantic collision and data loss.

### DEC-021-017: Seed-source licensing and portability

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** PHSAI seed depends on an external local DOCX; future external frameworks have copyright/licensing constraints.
- **Options:** commit source files; commit generated structured data; fetch from external storage at build time.
- **Recommendation:** Store approved, licensed, checksummed structured seed artifacts in a governed repository location; record source/version/license metadata. Never depend on a personal Downloads path.
- **Trade-off:** Requires content governance and possibly private artifact storage.

### DEC-021-018: Flutterwave webhook correction

- **Status:** Accepted as recommended, 17 July 2026
- **Context:** Flutterwave route is not excluded from CSRF despite documentation saying it is.
- **Options:** add exemption; move webhooks outside web middleware; disable Flutterwave until fixed.
- **Recommendation:** Add a failing end-to-end webhook test in Phase 22, then apply the smallest framework-approved exemption/configuration fix in a separately approved change.
- **Trade-off:** Operational correction is small but touches payment behavior and must be verified.

## Explicitly postponed decisions

- Marketplace and third-party publishing.
- Benchmark cohort rules and aggregation.
- AI model/provider and AI-generated content workflows.
- Organization-private/community template distribution.
- Recommendation/action-plan implementation.
- Evidence retention/deletion details beyond the future privacy design.
- Multi-target projects.
- Generic API creation.

### DEC-021-019: Assessment creation paths and terminology

- **Status:** Accepted, 17 July 2026
- **Decision:** Vytte has exactly two assessment creation paths.
  1. **Comprehensive Health Assessment** assesses health across an entire setting. The system loads a comprehensive setting-appropriate framework and allows removal of non-applicable assessment areas. The term **department** is used only when the selected setting genuinely has departments, especially a full health-facility run.
  2. **Focused Health Assessment** assesses exactly one health domain, programme, topic, or intervention. It must not show unrelated departments, programmes, grouped modules, standard batteries, or checkboxes. The system offers the best matching standard template and allows optional customization through progressive disclosure.
- **Setting rule:** A target may be a health facility, school, community, correctional facility, workplace, place of worship, NGO/programme, government organization, or custom setting. Settings are not health domains.
- **Template rule:** Published standard templates remain immutable. Customization produces an assessment snapshot or labeled derivative and must revalidate scoring/comparability.
- **Implementation consequence:** Existing multi-module work applies only to the comprehensive path. Focused creation resolves one selected health scope directly.

### DEC-021-020: Community-based assessments use the unified assessment architecture

- **Status:** Accepted, 17 July 2026
- **Decision:** Community surveys, patient-experience surveys, citizen feedback, caregiver feedback, and similar use cases are first-class assessment templates. They use the same assessment lifecycle, versioned scoring engine, reporting engine, permissions, exports, dashboards, and analytics as every other assessment.
- **Boundary:** Respondent role and template content may differ. They must not create a separate report, aggregation workflow, product module, or independent subsystem.
- **Implementation consequence:** Remove the dedicated plan feature and any proposed separate reporting routes, services, views, and terminology. Preserve the durable external-response collection infrastructure, then extend the shared scoring and reporting contracts with explicit respondent-role semantics.
- **Trade-off:** The shared scoring profile must become respondent-aware, but Vytte retains one coherent assessment and reporting model with lower maintenance cost and more consistent behavior.

### DEC-021-021: Canonical lifecycle vocabulary

- **Status:** Accepted, 17 July 2026
- **Decision:** Preserve the existing persisted values while assigning each to one bounded state machine. Assessments use `IN_PROGRESS -> COMPLETE`; included assessment areas use `PENDING -> COMPLETED`; excluded areas use `EXCLUDED`; template versions use `DRAFT -> PUBLISHED`.
- **Boundary:** `COMPLETE` and `COMPLETED` are not synonyms in storage. Completion and publication are terminal under the current product.
- **Implementation consequence:** Application constants and model guards reject unknown assessment/template states, assessment reopening, and published-template mutation. Human-readable UI labels may say “Completed” without changing the database value.
- **Postponed:** Assessment reopening, correction versions, cancellation, template retirement, and archival require explicit rules for immutable snapshots and audit history.

## Approval record

Isaac granted approval on 17 July 2026 to apply every recommendation and correct all recorded gaps. Implementation remains incremental: complete, test, commit, and push each bounded module separately.
