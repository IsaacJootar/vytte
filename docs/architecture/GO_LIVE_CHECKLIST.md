# Public Beta Go-Live Checklist

## Must pass before public beta

- [x] PostgreSQL migrations run cleanly.
- [x] Beta plans exist as configurable records.
- [x] Payment processing is not exposed to customers.
- [x] Feature gates are server-side enforceable.
- [x] Platform Admin can create draft framework versions.
- [x] Platform Admin can add sections, indicators, and question placements to draft frameworks.
- [x] Platform Admin can create catalogue releases and pin published frameworks.
- [x] Workspace Admin can create workspace custom assessment designs.
- [x] Health endpoint exists at `/health`.
- [x] Public shared-report and respondent routes have explicit throttles.
- [x] Production preflight command exists.

## Still required before real public beta

- [ ] Complete question version editing UI for options and numeric bands.
- [ ] Complete framework and catalogue supersession/archival UI.
- [ ] Complete dependency checking before archive/supersession actions.
- [ ] Decide whether workspace custom assessment designs must be runnable in beta or clearly label them as design drafts only.
- [ ] Run full sequential PostgreSQL test suite after the new beta changes.
- [ ] Configure real production mail provider.
- [ ] Configure queue workers and failed-job monitoring.
- [ ] Configure deployment monitoring and incident response.
- [ ] Review every public page for customer-facing copy and empty states.
