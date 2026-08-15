# Go-Live Checklist

## Current status

**Private beta operational; public/paid launch remains conditional.**

The application and official beta library are live. “Operational” does not mean independently
clinically validated for every jurisdiction, nor does it mean paid-launch operations are complete.

## Application and data complete

- [x] Laravel 13/PHP 8.3 and PostgreSQL migrations are current.
- [x] Exactly two assessment creation paths are enforced.
- [x] Official production seed excludes demo accounts and demo catalogue fixtures.
- [x] Official framework and catalogue creation, editing, publication, supersession, and archival exist.
- [x] Governance Studio exposes the nine labelled authoring steps.
- [x] Published questions, frameworks, releases, snapshots, scoring models, completed scores, and final
  reports are immutable historical contracts.
- [x] Supported response types have authenticated/public rendering, validation, persistence,
  completeness, snapshot, reporting, and scored/unscored behavior.
- [x] Local questions cannot alter the published score or benchmark.
- [x] Public respondent/report routes are throttled and token governed.
- [x] Production preflight asserts production environment, debug, URL, database, queue, mail, and disk.
- [x] Health endpoints exist at `/up` and `/health`.
- [x] Web responses carry CSP, HSTS, frame, MIME, referrer, opener, and permissions headers.
- [x] Full sequential PostgreSQL testing is the release gate.

## Production operations required for every release

- [ ] Fresh pre-deployment Vytte backup created and verified.
- [ ] Vytte queue service active and failed-job count checked.
- [ ] Vytte scheduler timer active.
- [ ] Vytte daily backup timer active and next run visible.
- [ ] Latest database dump passes `pg_restore --list`.
- [ ] Production commit equals GitHub `master`.
- [ ] Migrations, caches, assets, login, `/up`, `/health`, and security headers verified.
- [ ] Recent Laravel/Apache logs checked without exposing secrets.

These boxes are operational observations and must be checked again on each deployment; they are not
permanent code claims.

## Required before public beta promotion

- [ ] Assign real independent source, subject, methodology, scoring, and field-test reviewers to the
  official instruments and record evidence-backed decisions.
- [ ] Complete at least one jurisdiction-specific clinical/regulatory review for the launch market.
- [ ] Run and record a backup restore drill into an isolated temporary database and path.
- [ ] Configure uptime, failed-job, backup-age, certificate-expiry, disk, and application-error alerts
  with a named recipient.
- [ ] Name the incident owner and publish the internal escalation path.
- [ ] Review the complete signed-in journey on production with ordinary workspace and Platform Admin
  accounts at desktop and 375px mobile widths.
- [ ] Decide whether one catalogue entitlement serves all beta plans or approve plan-to-content
  entitlements before selling differentiated access.
- [ ] Keep workspace custom assessment designs explicitly labelled as non-runnable drafts unless they
  are promoted through the governed instrument lifecycle.

## Required before paid launch

- [ ] Billing event ledger and webhook idempotency.
- [ ] Subscription state, invoice/receipt tracking, reconciliation, refund, and failure handling.
- [ ] Payment-provider production keys, webhook monitoring, and financial incident ownership.
- [ ] Contractual privacy, retention, clinical-content, and support terms reviewed for launch markets.

## Explicitly deferred

- Dependency graph visualization and rich version comparison.
- Generic external REST API, partner portal, and mobile API.
- Advanced response types that do not yet satisfy the full response contract.
- Cross-methodology ranking where comparison signatures do not match.
