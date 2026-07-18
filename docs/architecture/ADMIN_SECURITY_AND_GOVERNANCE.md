# Admin Security and Governance

## Authorization

Platform admin routes require authenticated users with `platform_role = PLATFORM_ADMIN`.

Workspace routes remain scoped through workspace membership, route policies, and workspace-bound models.

## Audit logging

Consequential actions record audit events through `AuditService`, including:

- framework publication;
- catalogue publication;
- domain taxonomy publication;
- domain taxonomy supersession;
- assessment creation;
- multi-respondent finalization;
- final report generation.

## Privacy

Report sharing uses governed share links. Multi-respondent reports show aggregate results by default, while traceability remains available through authorized audit access.

## Immutable records

Published content and final snapshots are not edited in place. Changes require new versions.
