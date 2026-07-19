# Vytte Architecture — Index

This file is an index. The authoritative architecture documents live in `docs/architecture/`.

It previously duplicated a summary of the current architecture, which meant two documents competed
to describe the same system and drifted apart. Content now has one home.

## Start here

| Question | Document |
|---|---|
| What is the implemented architecture? | `docs/architecture/CURRENT_ARCHITECTURE.md` |
| How does an assessment run end to end? | `docs/architecture/CURRENT_ASSESSMENT_FLOW.md` |
| How is official question content structured? | `docs/architecture/QUESTION_BANK_ARCHITECTURE.md` |
| What is the schema? | `docs/database.md`, and the migrations, which are authoritative |
| What states exist and what transitions are legal? | `docs/architecture/LIFECYCLE_STATE_MACHINE.md` |
| How is scoring defined? | `docs/architecture/SCORING_CONTRACT.md` |
| What governs publication of official content? | `docs/architecture/CONTENT_GOVERNANCE.md` |
| Which decisions control the design? | `docs/architecture/DECISION_LOG.md` |
| What must future work preserve? | `docs/architecture/PRESERVATION_REGISTER.md` |
| What is known to be missing? | `docs/architecture/ARCHITECTURE_GAPS.md` |

## Governance and administration

- `docs/architecture/PLATFORM_ADMIN_ARCHITECTURE.md`
- `docs/architecture/WORKSPACE_ADMIN_ARCHITECTURE.md`
- `docs/architecture/ADMIN_ROLE_AND_PERMISSION_MATRIX.md`
- `docs/architecture/ADMIN_PUBLICATION_WORKFLOWS.md`
- `docs/architecture/ADMIN_SECURITY_AND_GOVERNANCE.md`
- `docs/architecture/ADMIN_OPERATIONS_RUNBOOK.md`

## Content and domains

- `docs/architecture/DOMAIN_ARCHITECTURE.md`
- `docs/architecture/DOMAIN_TAXONOMY_LIFECYCLE.md`
- `docs/architecture/DOMAIN_SCORING_AND_REPORTING.md`
- `docs/architecture/OFFICIAL_ASSESSMENT_CONTENT_LIFECYCLE.md`
- `docs/architecture/RESPONSE_TYPE_CONTRACT.md`
- `docs/architecture/DATASET_MANIFEST.md`

## Readiness and operations

- `docs/architecture/GO_LIVE_CHECKLIST.md`
- `docs/architecture/OPERATIONS_READINESS.md`
- `docs/architecture/SECURITY_REVIEW.md`
- `docs/architecture/TECHNICAL_DEBT_REPORT.md`
- `docs/architecture/TEST_COVERAGE_REVIEW.md`
- `docs/architecture/VYTTE_BETA_READINESS_REPORT.md`

## Historical records

`docs/architecture/archive/` holds point-in-time records: verification runs, cleanup reports, and
completed-phase audits. They were accurate when written and describe past states. Do not treat them
as current, and do not rewrite them.
