# Current Assessment Flow

## Scope

This document describes the implemented flow after the approved template, snapshot, scoring, and two-path creation modules. It distinguishes the current authenticated and public response paths and records the remaining gaps that are not yet resolved.

## End-to-end flow

```mermaid
flowchart TD
    P[Create project and setting] --> C[Create assessment]
    C --> K{Assessment path}
    K -->|Comprehensive| CT[Choose a published framework for the setting]
    CT --> D[Keep or exclude applicable areas]
    K -->|Focused| FT[Choose one published health-domain template]
    D --> A[Create assessment from immutable template version]
    FT --> A
    A --> SN[Persist composition and content snapshots]
    SN --> R{Runner}
    R -->|Authenticated| AR[All snapshot modules]
    R -->|Public token| PR[Legacy first-module public flow]
    AR --> RESP[Validated option or open-text responses]
    RESP --> SUB[Server-validated completion]
    SUB --> SCORE[Versioned normalized scoring]
    SCORE --> OUT[Results, PDF, CSV, shared report, progress]
```

## 1. Project and setting creation

1. An authenticated user opens `/projects/create`.
2. The user supplies a project name and one setting's type, category, country, region, and optional sub-region.
3. The selected category must belong to the selected setting type.
4. A `CUSTOM` setting can carry a user-defined label and a `uses_departments` flag.
5. The plan's active-project limit is checked.
6. A transaction creates a workspace-scoped project, its workspace-owned target/setting, and the project-target attachment.

The UI creates one setting initially, while the underlying schema still permits multiple targets per project.

## 2. Assessment creation

There are exactly two user-facing creation paths.

### Comprehensive Health Assessment

1. The controller loads published `COMPREHENSIVE` template versions compatible with the project's setting type.
2. The user selects one framework.
3. The framework's default areas are preselected. The user can remove areas that do not apply.
4. Department language is used only when the selected setting explicitly uses departments; otherwise the UI uses neutral area/module language.
5. The submitted template version and exclusions are revalidated on the server.

### Focused Health Assessment

1. The controller loads published `FOCUSED` template versions.
2. The user chooses one health programme, topic, or intervention template.
3. No department, standard-battery, grouped-module, or unrelated-topic picker is shown.
4. The selected focused template must resolve to exactly one module.

### Shared creation service

Both paths delegate to `AssessmentCreationService`. It verifies the published immutable template version, setting compatibility, composition rules, and plan limits; creates the assessment and scope rows; and stores immutable composition/content snapshots with provenance and hashes. Runtime content for template-created assessments is loaded from the snapshot rather than mutable catalogue questions.

Publishing stores both the SHA-256 hash and the exact hashed payload. New assessments are created from that stored payload, never by rebuilding a published version from the mutable catalogue.

The old default-battery/module-picker creation path is no longer used. The former PHSAI and school sample seeders are not part of normal database seeding. The repository's valid HIV focused content is seeded and published as a selectable template.

## 3. Authenticated runner

1. `/assessments/{assessment}/run` verifies project/workspace access.
2. The Livewire component locks the assessment identifier and independently rechecks authorization during actions.
3. For template-created assessments, questions, translations, options, and ordering come from the immutable assessment content snapshot.
4. The runner covers every in-scope module.
5. Option submissions are accepted only when the question is in scope and the option belongs to that question.
6. Open-text questions use the supported text-response path; unsupported response types cannot be published in templates.
7. Responses autosave. Authenticated responses have `respondent_id IS NULL`.
8. Submission is rejected on the server until all required scored questions are complete.

Remaining authenticated-runner work is limited to future response types and more granular consent policy. It is no longer a reason to retain invalid sample content.

## 4. Public respondent runner

1. A workspace user creates a token for an in-progress assessment; the token records its creator and supports expiry and revocation.
2. `/respond/{token}` validates the token and assessment state, then creates or resumes a durable `public_response_sessions` row.
3. Token usage count and last-use time are updated when a new respondent session begins.
4. All in-scope modules are loaded. Template-created assessments use the same immutable content snapshot as the authenticated runner.
5. Language selection is stored on the durable session. Consent is stored for every in-scope module that requires it.
6. Responses reference the durable session through a foreign key while retaining the legacy respondent UUID value for cohort separation and compatibility.
7. Question, option, and text mutations are revalidated against authoritative in-scope content.
8. Submit rechecks all required responses from stored data and persists `submitted_at`; remounting the link resumes the submitted state.

Public responses intentionally remain a separate voice cohort and are not blended into staff assessment scores. The remaining gap is a first-class cohort report and an approved retention/privacy policy, not response integrity or assessment coverage.

## 5. Submission and lifecycle

1. Authenticated submission verifies workspace ownership and required-response completeness.
2. The assessment becomes `COMPLETE`, receives `completed_at`, and all in-scope module rows become `COMPLETED`.
3. Scoring runs synchronously and stores the scoring algorithm/version used.
4. The same transaction persists an immutable structured report snapshot with schema version and SHA-256 content hash.
5. OWNER and ADMIN users receive a database notification and optional email.

The assessment content and composition are immutable through snapshots. Remaining lifecycle work includes a governed correction/reopen policy, harmonizing status terminology, and defining publication/archive transitions.

## 6. Scoring flow

1. Read all in-scope module IDs and authenticated responses.
2. For template-created assessments, read sub-index membership, question weights, option weights, and domain identity from the assessment snapshot. Legacy assessments use the live profile compatibility path.
3. Normalize option score scales to a canonical 0–100 range.
4. Calculate weighted sub-index results with explicit calibration states.
5. Aggregate domain and overall results and map the overall result to a maturity level.
6. Persist the scoring version with calculated scores so future algorithm changes remain distinguishable.

### Remaining scoring risks

- Domain weights and cross-module aggregation policy require an explicit governed formula.
- Public/respondent cohort scoring must remain separate from staff/assessor scoring unless a future approved evidence model says otherwise.
- Numeric, multi-select, ranking, observation, and corroboration models are postponed until their product need and scoring semantics are approved.

## 7. Results and reports

- Newly completed results, PDF, shared reports, and history read the immutable final report snapshot rather than mutable catalogue labels or score joins.
- The structured report snapshot preserves assessment identity, all included areas, setting/project labels, score and maturity, scoring version, domain/sub-index labels and values, completion time, schema version, and content hash.
- Historical comparisons require matching composition hashes. Legacy assessments without hashes fall back to exact sorted module-ID fingerprints.
- CSV lists every included module rather than only the first.
- Signed links are stateless temporary URLs; the dormant share-link table is not yet the active governance mechanism.

## Current implementation boundary

Template versions and assessment snapshots are now the creation/runtime boundary. Legacy sample data is not a compatibility contract. New work should strengthen this boundary, migrate or reseed valid content into it, and remove obsolete paths rather than weakening template validation to accommodate samples.
