# Go-Live Checklist

This is the single go-live checklist. It merges the former `GO_LIVE_CHECKLIST.md` and
`PRODUCTION_GO_LIVE_CHECKLIST.md`, which overlapped and contradicted each other on whether framework
content management was complete. Beta and production are separate gates and are kept as separate
sections rather than separate documents.

Status: **NOT READY FOR PUBLIC BETA.**

## Complete

- [x] PostgreSQL migrations run cleanly.
- [x] Beta plans exist as configurable records.
- [x] Payment processing is not exposed to customers.
- [x] Feature gates are server-side enforceable.
- [x] Platform Admin can create draft framework versions.
- [x] Platform Admin can create catalogue releases and pin published frameworks.
- [x] Workspace Admin can create workspace custom assessment designs.
- [x] Health endpoints exist at `/up` and `/health`.
- [x] Public shared-report and respondent routes have explicit throttles.
- [x] Production preflight command exists, covering presence of required configuration.
- [x] Full sequential PostgreSQL test suite passes.
- [x] Assessment snapshot immutability is enforced in code and covered by tests.
- [x] Governance dependency counting is correct and bounded.

Framework sections, indicators, and question placements are reachable through routes but are not
yet manageable through a complete authoring interface. They are listed under Required Before Beta
rather than as complete. The previous checklist marked them complete while `ARCHITECTURE_GAPS`
GAP-02 and `TECHNICAL_DEBT_REPORT` recorded the same capability as critical and incomplete.

## Required before public beta

- [ ] Complete Platform Admin framework authoring: sections, indicators, placements, ordering, and weighting.
- [ ] Complete catalogue composition authoring.
- [ ] Complete framework and catalogue supersession/archival interfaces.
- [ ] Author a defensible official content library with source authority and licence metadata.
- [ ] Enforce the content review chain: distinct author, reviewer, and approver.
- [ ] Decide whether workspace custom assessment designs must be runnable in beta, or label them clearly as design drafts only.
- [ ] Configure real production mail provider.
- [ ] Configure queue workers and failed-job monitoring.
- [ ] Configure backups and test a restore.
- [ ] Configure deployment monitoring and incident response.
- [ ] Extend preflight to assert production values, not only presence.
- [ ] Review every public page for customer-facing copy and empty states.
- [ ] Verify the full journey end to end on production-like infrastructure with real content.

## Required before production

- [ ] Implement plan-to-content entitlements using current plan codes: `STARTER`, `PROFESSIONAL`, `ORGANIZATION`.
- [ ] Gate module library and assessment creation by plan entitlements.
- [ ] Complete or withdraw production claims for workspace custom assessments and local sections.
- [ ] Decide whether evidence remains text-only or implement a secure evidence-file lifecycle.
- [ ] Remove or explicitly justify every reserved schema table.
- [ ] Configure `APP_ENV=production`, `APP_DEBUG=false`, production `APP_URL`, secure session and cookie settings, and cached config, routes, and views.
- [ ] Re-run full sequential tests.
- [ ] Re-run clean PostgreSQL `migrate:fresh --seed --force`.
- [ ] Re-run frontend build.
- [ ] Perform a final security review.

## Required before paid launch

- [ ] Add billing event ledger.
- [ ] Add webhook idempotency.
- [ ] Add subscription state model.
- [ ] Add invoice and receipt tracking.
- [ ] Add payment reconciliation.
- [ ] Add tests for billing idempotency and failure handling.

## May wait until after production MVP

- [ ] Dependency graph interface.
- [ ] Version comparison interface.
- [ ] External REST API.
- [ ] Partner portal.
- [ ] Mobile app API.
- [ ] Enterprise/custom plan.
