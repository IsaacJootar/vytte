# Platform Admin Capability Audit

## Complete and manageable

- Platform admin gate through `EnsurePlatformAdmin`.
- Workspace listing and detail inspection.
- Platform settings.
- Plan feature configuration.
- Module/library viewing and limited management.
- Question activation and text editing for legacy identity records.
- Governed framework publication through services.
- Question-version publication through services.
- Catalogue publication through services.
- Report-share creation/revocation in workspace flow.
- Multi-respondent finalization safeguards.
- Audit logging service for consequential publication/finalization/share actions.

## Implemented in this phase

- Domain taxonomy tables.
- Domain taxonomy publication/supersession service.
- Indicator-level analytical-domain mapping.
- Placement-level domain override support.
- Platform-admin domain taxonomy inspection UI.
- Frozen taxonomy metadata in assessment snapshot manifests and final reports.

## Backend-only or partially manageable

- Question-version drafting and supersession exist mostly in backend/service form.
- Framework section/indicator/placement management exists in backend/database/seed path, not a full curator UI.
- Facility profile and catalogue release management exists in backend/service/seed path, not a full curator UI.
- Scoring-profile and aggregation-policy management exists through frozen payload/configuration, not a full policy-editor UI.

## Intentionally system-managed

- Migration history.
- Immutable assessment snapshots.
- Immutable report snapshots.
- Content hashes.
- Published framework payloads.
- Respondent score snapshots.

## Missing but not safely completed as broad CRUD in this phase

- A full curator workbench for drafting all official content.
- Version comparison UI.
- Publication approval workflow UI.
- Dependency graph UI.
- Cross-workspace platform oversight dashboard with privacy-safe audit trails.

## Security assessment

Workspace admins cannot access platform admin routes. Platform admins can inspect domain taxonomies and mappings. Workspace-local content remains separated from official domain scoring.
