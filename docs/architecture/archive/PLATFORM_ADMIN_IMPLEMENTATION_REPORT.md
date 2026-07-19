> **ARCHIVED 2026-07-19.** Historical record. Accurate when written; describes a past state of the repository. Do not treat as current and do not rewrite. See `README.md` in this folder.

# Platform Admin Implementation Report

## Completed

- Built a polished Vytte Platform Admin control center.
- Added official content dashboard for governed Vytte content.
- Added question-group list/create/show/edit/archive workflows.
- Added reusable question identity list/create/show workflows.
- Added question-version list/show/approve/publish workflows using the governed publishing service.
- Added framework-version list/show/publish workflows using the governed publishing service.
- Added catalogue-release list/show/publish workflows using the governed publishing service.
- Added facility-profile list/show views.
- Added scoring and aggregation policy visibility.
- Added platform-user role assignment for `PLATFORM_ADMIN`.
- Added workspace status control for Platform Admin suspension/reactivation.
- Added assessment lifecycle oversight.
- Added report share-link oversight and emergency revocation.
- Added audit-log review.
- Expanded the Platform Admin sidebar and dashboard.
- Removed active source use of the old platform-content role terminology.

## Existing controls preserved

- Plan-feature controls remain in `admin.plan-features.*`.
- Platform settings remain in `admin.settings.*`.
- Payment webhooks remain handled by Paystack and Flutterwave webhook controllers.
- Workspace-level team, invitation, assessment, respondent, report, and share-link workflows remain outside Platform Admin methodology control.

## Explicitly pending

- Full framework editor UI for Platform Admins to create, edit, and reorder framework sections.
- Full framework editor UI for Platform Admins to create, edit, and reorder indicators.
- Full framework editor UI for Platform Admins to place, replace, weight, and reorder exact question versions.
- Version comparison UI for old/new question, framework, and catalogue versions.
- Dependency graph UI showing which catalogue releases, frameworks, indicators, placements, and assessments depend on each content object.
- Deeper payment-provider operations dashboard for payment events, failures, subscriptions, and reconciliation.

## Governance behavior

The UI does not bypass publication services. Question versions, framework versions, and catalogue releases still pass through validation and immutable publication logic before becoming official content.

## Tests added or updated

- Added `tests/Feature/PlatformAdminControlCenterTest.php`.
- Updated `tests/Feature/AdminTest.php` for the new Platform Admin dashboard title.

## Verification status

- Batched full test-tree verification passed: 401 tests, 1,015 assertions.
- Clean PostgreSQL migration/seed passed.
- Schema rename verification passed.
- Frontend build passed.
- Active-source reference audit passed.
- Commit and push are pending final git review.
