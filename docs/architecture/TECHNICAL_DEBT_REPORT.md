# Technical Debt Report

## Summary

The codebase is organized and the core architecture is coherent, but production debt remains around incomplete operational controls, inactive schema, and incomplete UI coverage for backend-supported capabilities.

## Findings

| Severity | Debt | Recommendation |
| --- | --- | --- |
| CRITICAL | Official framework editing is not fully self-service. | Build Platform Admin editor UI for sections, indicators, placements, ordering, weighting, and validation. |
| CRITICAL | Paid-plan content entitlement is missing. | Add plan/module/template/catalogue binding tables, UI, and enforcement. |
| HIGH | Catalogue composition is not fully self-service. | Add release composition UI and tests. |
| HIGH | Workspace custom assessments are backend-supported but not fully user-facing. | Complete UI/lifecycle or remove production claim. |
| HIGH | Inactive/reserved tables remain in schema. | Remove or justify non-authoritative tables before production. |
| HIGH | Production operations are not codified. | Add backup, monitoring, queue, mail, logging, incident, and release runbooks. |
| MEDIUM | Payment architecture is partial. | Add billing ledger, idempotency, subscriptions, invoices, and reconciliation before paid production. |
| MEDIUM | Public route throttles need explicit policies. | Add route-specific throttles. |
| MEDIUM | Evidence remains text-only. | Keep as text support or implement secure file lifecycle. |
| FUTURE ENHANCEMENT | Dependency graph and version comparison are absent. | Build after production blockers are resolved. |

## Resolved

| Debt | Resolution |
| --- | --- |
| Assessment snapshot immutability was convention only. | Enforced by a model guard rejecting updates, with tests. See DEC-2026-07-19-012. |
| Governance dependency counting double-counted snapshots and scanned every snapshot in PHP. | Counting is correct and runs in the database. |
| The question-version editor silently discarded `critical_failure` and `option_key` on save. | Stored values are carried forward. An author-facing control for `critical_failure` does not yet exist. |
| `SUPPORTED_ALGORITHM_VERSIONS` advertised a scoring version with no implementation. | Removed. |
| The demo dataset silently seeded nothing after the plan rename, so a fresh install had no demo assessments, scores, or reports. | Seeder identifiers aligned; a missing demo account now warns instead of failing silently. |
| The full sequential test suite had never been run. | It runs and passes. Earlier green claims came from batched runs. |

## Outstanding, added since the original report

| Severity | Debt | Recommendation |
| --- | --- | --- |
| MEDIUM | `PlatformSettingController` renders and persists `plan.free_projects`, `plan.free_assessments_per_project`, and `plan.pro_projects`, which `PlanService` no longer reads. The controls have no effect. | Decide whether to remove the controls or restore the behavior. Recorded as known drift under DEC-2026-07-18-010. |
| MEDIUM | `domain_scores` has no `respondent_type`, while `sub_index_scores` does. Both staff and aggregate runs upsert into one keyspace. | Latent rather than live. Resolve in a hardening pass. |
| MEDIUM | Nothing prevents re-scoring a `COMPLETE` assessment, though the scoring contract states results are never silently recalculated. | Enforce the contract or amend it. |
| MEDIUM | Multi-respondent aggregation copies domain metadata, including `questions_answered`, from the first contributing session. | Fix before any content enables multi-respondent collection. |
| LOW | Assessment snapshot deletion is not guarded, unlike report snapshots. | Proposed follow-up; requires updating shared test fixtures. |

## Codebase scan notes

- Active source is clear of old Platform Admin role terminology.
- Active source is clear of old structural question-group table/model naming.
- The cleanup report intentionally names removed legacy artifacts.
- Route list contains no first-party API endpoints yet.
- Laravel placeholder text in framework config is not app debt, but production env values must be real.
