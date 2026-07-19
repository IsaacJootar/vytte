> **ARCHIVED 2026-07-19.** Historical record. Accurate when written; describes a past state of the repository. Do not treat as current and do not rewrite. See `README.md` in this folder.

# Production Go-Live Checklist

## Go-live status

Current status: **NOT READY**.

## Must complete before production

- [ ] Build Platform Admin framework editor for sections.
- [ ] Build Platform Admin framework editor for indicators.
- [ ] Build Platform Admin framework editor for question placements, ordering, weighting, and replacement.
- [ ] Build Platform Admin catalogue composition editor.
- [ ] Add supersession/archive UI for official question versions, frameworks, catalogue releases, facility profiles, and analytical-domain taxonomy versions.
- [ ] Implement Free/Pro/Agency plan-to-content entitlements.
- [ ] Gate module library and assessment creation by plan entitlements.
- [ ] Add subtle upgrade prompts where content/features are plan-gated.
- [ ] Complete or remove production claims for workspace custom assessments/local sections.
- [ ] Decide whether evidence remains text-only or implement secure evidence-file upload.
- [ ] Remove or explicitly justify every inactive/reserved schema table.
- [ ] Add explicit public-route rate limiting.
- [ ] Add production env validation/preflight.
- [ ] Configure real production mail.
- [ ] Configure queue workers and failed-job alerting.
- [ ] Configure backups and test restore.
- [ ] Configure logs/error reporting/monitoring.
- [ ] Configure production `APP_ENV=production`, `APP_DEBUG=false`, production `APP_URL`, secure session/cookie settings, and cache/routes/config.
- [ ] Re-run full sequential tests.
- [ ] Re-run clean PostgreSQL `migrate:fresh --seed --force`.
- [ ] Re-run frontend build.
- [ ] Perform final security review.

## Should complete before paid launch

- [ ] Add billing event ledger.
- [ ] Add webhook idempotency.
- [ ] Add subscription state model.
- [ ] Add invoice/receipt tracking.
- [ ] Add payment reconciliation dashboard.
- [ ] Add tests for billing idempotency and failure handling.

## May wait until after production MVP

- [ ] Dependency graph UI.
- [ ] Version comparison UI.
- [ ] External REST API.
- [ ] Partner portal.
- [ ] Mobile app API.
- [ ] Enterprise/Custom plan.

## Final conclusion

NOT YET READY FOR PRODUCTION
