# Preservation Register

## Purpose

This register identifies contracts that future work must preserve unless an explicit migration, compatibility, and audit decision replaces them.

## Core contracts

| Asset | Contract | Allowed extension boundary |
|---|---|---|
| Registration | User, workspace, OWNER membership, and active workspace are created atomically | Add onboarding after the transaction |
| Workspace authority | Membership resolves the active workspace before route binding | Add scoped parent queries and policies; never trust an active ID alone |
| Project setting | One target/setting per project | Keep the constrained junction; multi-target requires a new product decision |
| Identifiers | Mixed UUID, integer, string, and composite keys | Do not normalize for style |
| Two creation paths | Comprehensive setting assessment or one focused health scope | Add governed templates, never a generic battery/module picker |
| Published template version | Exact payload/hash is immutable | Publish a new version for corrections |
| Assessment snapshot | Content, composition, consent applicability, and scoring profile are frozen | Add versioned snapshot fields compatibly |
| Assessment lifecycle | `IN_PROGRESS -> COMPLETE`, terminal | Reopen/correction/archive requires explicit snapshot/audit semantics |
| Assessment composition | Included/excluded scope rows and reasons remain historical | Do not rewrite completed composition |
| Authenticated runner | One-question flow, autosave, localization, consent, server authority | Add supported renderers behind the same contract |
| External respondent link | Existing token URLs, durable sessions, consent, submitted responses, revocation audit | Add respondent roles inside the shared engine |
| Responses | Existing option/text/numeric/session references remain readable | Optional evidence remains response-bound; new types are additive |
| Evidence | Optional `evidence_note` attached to one response | No repository/file subsystem without privacy and retention approval |
| Scoring | Null means uncalibrated; canonical output is 0–100; algorithm version is stored | New formula requires a new version and fixtures |
| Completed scores | Never silently recalculated | Explicit versioned recalculation/migration only |
| Final report snapshot | Structured payload/hash is immutable | Regenerate formats from the snapshot |
| Result/export routes | Existing result, PDF, CSV, progress, comparison, and shared-report URLs remain valid | Add fields compatibly |
| Governed report links | Creator, expiry, revocation, use, and audit history are preserved | Add management UI around the same records |
| Legacy signed reports | Existing valid URLs remain readable | Do not issue new stateless links where governed links apply |
| Progress comparison | Compare only matching composition fingerprints | Expand fingerprint rules when customization semantics expand |
| Consent | Exact text, actor/session, and time remain historical | New consent versions do not rewrite prior records |
| Audit log | Audit records are immutable and avoid full secret tokens | Extend the event catalogue alongside sensitive actions |
| Billing/features | Plan checks supplement, never replace, authorization | Add feature keys only for true capabilities, not content categories |
| UI shell | Ocean Blue tokens, dark mode, desktop/mobile navigation, accessible components | Reuse before introducing variants |
| Localization | English/French fallback remains deterministic | New locales require allowlist and content coverage |
| Webhooks | Provider validation and CSRF exemptions remain tested | Provider changes require end-to-end tests |

## Dormant/reserved structures

The following remain reserved and must not be presented as implemented product behavior:

- `assessment_topic_scope`
- `response_options`
- `observation_records`
- `topic_scores`
- `project_domain_scores`
- `project_scores`
- `root_causes`
- `recommendation_rules`
- `recommendations`

The obsolete special respondent-aggregation table was intentionally retired and must not be recreated as a parallel reporting subsystem.

## Compatibility paths

- Legacy non-template assessments may read live catalogue content and use the legacy scoring/report builder.
- New template-created assessments must use immutable published payloads and assessment snapshots.
- New completed assessments must use immutable final report snapshots.
- Legacy data may be migrated only by an explicit, idempotent, auditable backfill with rollback and verification.

## Required regression coverage

- Cross-workspace rejection for project, assessment, runner, result, export, progress, share, token, and consent actions
- Question/option authority and locked Livewire identifiers
- Required-response completion enforcement
- Score normalization, calibration, version, and idempotence
- Published-content and final-report immutability
- Composition-safe comparisons
- Respondent/report token expiry and revocation
- SQLite fast-suite behavior and PostgreSQL release parity

## Worktree preservation

Uncommitted user changes are not part of an architecture migration merely because they are present. Work must stage only intended files and must not overwrite or silently absorb unrelated UI work.
