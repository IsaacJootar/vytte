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
- **Decision:** PostgreSQL is the production and release-candidate authority. SQLite remains allowed for local development and automated tests while the local Docker/PostgreSQL environment is unavailable.
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
