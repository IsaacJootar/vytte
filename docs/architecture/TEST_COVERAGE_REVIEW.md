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

Latest verification: 401 tests, 1097 assertions passed. Full sequential PostgreSQL run, 2026-07-19, commit `65648e5`.

Earlier figures in this repository came from running test files in batches rather than one sequential suite. The first full sequential run found five failures that batched runs had not surfaced: three plan-limit tests asserting a replaced mechanism, and two domain tests broken by a demo seeder that silently produced no data. Record batched results as batched; they do not evidence a passing suite.

## Gaps

| Severity | Missing coverage | Recommendation |
| --- | --- | --- |
| HIGH | Plan-to-content entitlement tests for module/template/catalogue visibility. | Add tests once entitlement architecture is implemented. |
| HIGH | Full Platform Admin framework section/indicator/placement create/edit/reorder tests. | Add with framework editor UI. |
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

## Recommendation

Do not consider the platform production-ready until the HIGH coverage gaps match completed production features.
