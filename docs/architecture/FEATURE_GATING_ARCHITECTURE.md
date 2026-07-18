# Feature Gating Architecture

Feature access is enforced through `PlanService`.

## Server-side rules

- Controllers must check `PlanService::workspaceCanAccess($workspace, $featureKey)` before performing gated actions.
- UI-only hiding is never sufficient.
- Unknown feature keys return false.
- Legacy plan codes are normalized before access checks.

## Current beta behavior

All active beta plans are seeded with all available features enabled:

- projects
- assessments
- respondent collection
- shareable public links
- shareable report links
- reports
- PDF export
- unwatermarked PDF export
- CSV export
- progress and maturity tracking
- team members
- workspace custom assessments
- local sections
- localization
- module library
- notifications
- audit logs

## Locked feature experience

The `plan-gate` component displays a professional locked state when a future plan configuration disables a feature. It shows the current plan, the required plan, and a subtle upgrade-ready action without payment processing.
