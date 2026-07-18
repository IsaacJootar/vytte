# Admin Security and Governance

## Role boundary

Vytte has two administration levels.

- **Vytte Platform Admin:** platform-wide methodology, official content, publication, roles, operational oversight, audit, and compliance.
- **Workspace Admin:** workspace-only people, assessments, respondents, reports, sharing, custom local content, and workspace settings.

`PLATFORM_ADMIN` is the only platform governance role used in active source. Older platform-content role terminology has been removed from active authorization paths.

## Authorization

All Platform Admin routes use `auth` plus `EnsurePlatformAdmin`. Workspace routes remain governed by workspace membership, route policies, and workspace-scoped models.

## Immutable content

Published question versions, framework versions, catalogue releases, assessment snapshots, respondent score results, aggregation results, and final reports are not edited in place.

## Audit logging

Consequential Platform Admin actions record audit events through `AuditService`, including:

- question-group create/update/archive;
- question-version approval/publication;
- framework publication;
- catalogue publication;
- platform-user role changes;
- workspace status changes;
- report share-link revocation;
- report link creation/view/revocation;
- assessment creation/finalization/report generation.

## Privacy

Platform Admin oversight pages expose operational metadata and governed artifact status. Shared reports remain governed through share-link controls. Multi-respondent reports show aggregate results by default; traceability of contributing sessions remains restricted to authorized audit access.

## Payments and plans

Plan-feature controls are administered through Platform Admin. Payment events are accepted through signed/verified provider webhooks and must not become a separate assessment-governance path.
