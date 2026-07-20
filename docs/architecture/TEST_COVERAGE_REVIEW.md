# Test Coverage Review

## Current test strength

The test suite is broad and recently verified in batches:

- Platform Admin control center;
- admin access;
- module library;
- question-bank architecture;
- platform-governed composition;
- domain architecture;
- assessment creation/running/submission;
- scoring;
- results;
- exports and share links;
- multi-respondent scoring;
- public respondent runner;
- consent capture;
- billing;
- plan features;
- settings;
- team;
- workspace isolation;
- projects;
- dashboard;
- geography;
- localization;
- notifications;
- profile/theme;
- auth;
- unit smoke tests.

Latest verification: 557 tests, 1677 assertions passed. Full sequential PostgreSQL run, 2026-07-20, on the working tree at commit `a749040` plus the account-notification change.

Previous verification: 401 tests, 1097 assertions passed. Full sequential PostgreSQL run, 2026-07-19, commit `65648e5`.

Earlier figures in this repository came from running test files in batches rather than one sequential suite. The first full sequential run found five failures that batched runs had not surfaced: three plan-limit tests asserting a replaced mechanism, and two domain tests broken by a demo seeder that silently produced no data. Record batched results as batched; they do not evidence a passing suite.

## Gaps

| Severity | Missing coverage | Recommendation |
| --- | --- | --- |
| HIGH | Plan-to-content entitlement tests for module/template/catalogue visibility. | Add tests once entitlement architecture is implemented. |
| MEDIUM | Advanced Tools framework section/indicator/placement create/edit/reorder tests. The Assessment Builder path is covered; the raw governance views are not. | Add if Advanced Tools remains a supported path. |
| MEDIUM | Respondent-link and report-share-link sharing UI is covered for invitations only. The equivalent persistence tests for the other two link types are not written. | Add alongside the next change to those screens. |
| HIGH | Catalogue composition create/edit/supersede/archive tests. | Add with catalogue editor UI. |
| HIGH | Workspace custom assessment UI and lifecycle tests. | Add or remove from production claim. |
| MEDIUM | Public route rate-limit tests. | Add after throttles. |
| MEDIUM | Payment webhook idempotency/reconciliation tests. | Add before paid launch. |
| MEDIUM | Production preflight/operations tests. | Add command tests for required env/config. |
| LOW | Dependency graph and version comparison tests. | Add with future admin tooling. |

## Closed gaps

| Coverage | Where |
| --- | --- |
| Assessment snapshot immutability. | `PlatformGovernedCompositionTest` covers rejected mutation of payload, composition manifest, aggregation policy, collection config and content hash. |
| Governance dependency counting. | `PlatformGovernedCompositionTest` covers catalogue-release snapshot counting and question-version snapshot references. The service previously had no tests. |
| Option `critical_failure` round-trip through the admin editor. | `PlatformAdminControlCenterTest`. |
| Legacy plan-code normalization to current plan limits. | `ConfigurabilityTest`. |
| Assessment Builder: sections, questions, scoring, evidence, review, publication, versioning, and the publication-bypass guard. | `AssessmentBuilderTest`. |
| Workspace suspension actually blocking members, and closure doing the same. | `PlatformOperationsTest`. |
| Account suspension blocking sign-in, carrying its reason, and reactivation restoring access. | `PlatformOperationsTest`. |
| Session revocation on user and workspace suspension, asserted against the database session driver production uses. | `PlatformOperationsTest`. |
| Self-suspension and last-platform-admin guards. | `PlatformOperationsTest`. |
| Notifications staying in-app when email is off and adding mail when the switch is on. | `PlatformOperationsTest`. |
| Platform Health reporting a configured Resend transport with no API key. | `PlatformOperationsTest`. |
| Invitation links surviving a page load, WhatsApp share presence, token replacement on re-issue, and old links ceasing to work. | `TeamTest`. |
| Plain language: no storage vocabulary rendered as visible text on Assessments in Use. | `PlatformOperationsTest`. |
| Module library search, pagination and status filtering. | `AdminTest`. |
| A flashed success message actually being rendered by the page it lands on. | `AdminTest`. |

## Recommendation

Do not consider the platform production-ready until the HIGH coverage gaps match completed production features.
