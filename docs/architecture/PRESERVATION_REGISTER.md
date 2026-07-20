# Preservation Register

## Purpose

This register identifies current contracts that future work must preserve unless an explicit migration and audit decision replaces them.

## Core Contracts

| Asset | Contract | Extension boundary |
|---|---|---|
| Registration | User, workspace, OWNER membership, and active workspace are created atomically | Add onboarding after the transaction |
| Workspace authority | Active workspace must be backed by membership | Add scoped parent queries and policies |
| Project setting | One assessed target per project | Multi-target projects require a new product decision |
| Facility profile | Health-facility projects select an official Vytte profile | Add profile-edit flow without changing existing assessment snapshots |
| Official department | `assessment_modules` is the official department catalogue | Add governed metadata without changing historical IDs |
| Department framework version | Published payload/hash is immutable | Publish a new version for corrections |
| Catalogue release | Published release pins exact framework versions and policy | Publish a new release for corrections |
| Comprehensive assessment | Composition orchestrator, not a question-owning template | Add facility profiles and catalogue releases |
| Focused assessment | One health domain/programme/topic/intervention | Add approved focused releases without unrelated checklists |
| Assessment snapshot | Payload, manifest, policy, collection config, and content hash are frozen. Enforced by a model guard that rejects updates. See DEC-2026-07-19-012 | Add versioned fields compatibly |
| Response key | Responses are keyed by question identity, so one identity appears at most once per assessment. See DEC-2026-07-19-011 | Re-keying to placements requires an explicit decision and a migration of responses, runners, scorers, and reports |
| Assessment lifecycle | `IN_PROGRESS -> COMPLETE`, terminal | Reopen/correction/archive requires explicit rules |
| Assessment module scope | Included/excluded rows remain historical | Do not rewrite completed composition |
| Runner | Reads immutable snapshot, validates every write | Add response types only with full contracts |
| Evidence | Optional note attached to one response | No separate evidence subsystem |
| Scoring | 0-100 canonical output, null means uncalibrated, version stored | New formulas require new versions and fixtures |
| Local custom sections | Workspace-local context only, excluded from official scoring | Future local scoring requires explicit methodology |
| Final report snapshot | Structured payload/hash is immutable | Regenerate formats from the snapshot |
| Governed share links | Creator, expiry, revocation, use count, and audit are preserved | Add management UI around the same records |
| Consent | Exact consent text, actor/session, and time remain historical | New consent versions do not rewrite prior records |
| Audit log | Audit records are immutable and avoid full secret tokens | Extend event catalogue with new sensitive actions |
| Billing/features | Plan checks supplement authorization | Add feature keys only for real capabilities |
| UI shell | Ocean Blue tokens, dark mode, responsive navigation | Reuse before introducing variants |
| Webhooks | Provider validation and CSRF exemptions remain tested | Provider changes require end-to-end tests |

## Reserved Structures

These tables are not current product authority:

- `assessment_topic_scope`
- `response_options`
- `observation_records`
- `topic_scores`
- `project_domain_scores`
- `project_scores`
- `root_causes`
- `recommendation_rules`
- `recommendations`

The earlier assessment template tables are no longer present. They were dropped with the legacy template architecture and no code references them.

Do not build new product behavior on reserved structures without a fresh design decision.

## Required Regression Coverage

- Cross-workspace rejection for project, assessment, runner, result, export, progress, share, token, and consent actions
- Facility profile resolution
- Department framework publication and immutability
- Catalogue release publication and immutability
- Required/default/optional department behavior
- Snapshot immutability
- Question/option authority
- Required-response completion enforcement
- Local custom section scoring exclusion
- Score normalization, calibration, critical failure, version, and idempotence
- Final-report immutability
- Respondent/report token expiry and revocation
- PostgreSQL migration, seeding, and test behavior

## Retired in P4 (2026-07-20)

| Artifact | Reason | Recovery |
| --- | --- | --- |
| `recommendations` | Single-threshold recommendation model superseded by the P4 recommendation framework. Empty and unreferenced. | `down()` on `2026_07_20_123000_retire_legacy_recommendation_tables` restores the original structure. |
| `recommendation_rules` | Same. Could not express objective, lens, evidence or history as inputs. | Same migration. |
| `root_causes` | Existed only to be referenced by `recommendations`. Root cause returns in P4 as an analysis lens over live results rather than a stored table of pre-computed causes. | Same migration. |

All three were empty at retirement. See `RECOMMENDATION_FRAMEWORK.md` for what replaced the model.

## Superseded wording

`DOMAIN_ARCHITECTURE.md` previously described measurement domains as "analytical lenses for interpretation, findings, recommendations, trends, and reporting". P4 reserves "lens" for the intelligence layer. The measurement domains themselves are unchanged; only the wording was retired. See `HEALTH_METHODOLOGY_ARCHITECTURE.md`.
