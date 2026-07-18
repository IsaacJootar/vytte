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

## Codebase scan notes

- Active source is clear of old Platform Admin role terminology.
- Active source is clear of old structural question-group table/model naming.
- The cleanup report intentionally names removed legacy artifacts.
- Route list contains no first-party API endpoints yet.
- Laravel placeholder text in framework config is not app debt, but production env values must be real.
