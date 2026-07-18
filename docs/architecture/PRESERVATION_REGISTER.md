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
| Assessment snapshot | Payload, manifest, policy, and collection config are frozen | Add versioned fields compatibly |
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

- earlier template tables
- `assessment_topic_scope`
- `response_options`
- `observation_records`
- `topic_scores`
- `project_domain_scores`
- `project_scores`
- `root_causes`
- `recommendation_rules`
- `recommendations`

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
- SQLite fast-suite behavior and PostgreSQL release parity
