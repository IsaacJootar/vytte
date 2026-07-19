> **ARCHIVED 2026-07-19.** Historical record. Accurate when written; describes a past state of the repository. Do not treat as current and do not rewrite. See `README.md` in this folder.

# Platform Admin Completion Audit

## Audit date

2026-07-18

## Starting state

The backend already contained governed content objects for question versions, department/focused framework versions, facility profiles, catalogue releases, scoring metadata, analytical-domain taxonomies, plans, settings, audit logs, workspaces, reports, and share links.

The Platform Admin UI was incomplete: it exposed dashboard, workspaces, modules, plan features, settings, geographic usage, and domain taxonomy pages, but not a complete official-content and operations control center.

## Required cleanup found

- The old structural table name was still physically present as the former structural grouping concept.
- Active source still had old platform-content role terminology in policies, middleware, user helper, and factory helper.
- Documentation still described a future content-governance UI rather than the now-approved Platform Admin model.

## Completed audit actions

- Confirmed the active source now uses `question_groups` and `question_group_id`.
- Confirmed fresh PostgreSQL migration creates `question_groups` and does not create the former table.
- Confirmed `questions.question_group_id` exists and the old column does not.
- Removed old platform-content role terminology from active authorization source.
- Added Platform Admin control-center pages for official content, operations, roles, sharing, and audit.
- Added tests covering Platform Admin access and key controls.

## Remaining verification

Run full sequential test batches, clean PostgreSQL migrate/seed, build, final reference audit, git status, commit, and push.
