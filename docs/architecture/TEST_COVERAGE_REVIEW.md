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

Latest batched verification: 401 tests, 1,015 assertions passed.

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

## Recommendation

Do not consider the platform production-ready until the HIGH coverage gaps match completed production features.
