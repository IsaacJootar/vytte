> **ARCHIVED 2026-07-19.** Historical record. Accurate when written; describes a past state of the repository. Do not treat as current and do not rewrite. See `README.md` in this folder.

# Platform Admin Capability Audit

## Complete and manageable

- Platform Admin gate through `EnsurePlatformAdmin`.
- Platform dashboard and official content control center.
- Workspace listing, detail inspection, status suspension, and reactivation.
- Platform user listing and Platform Admin role assignment.
- Platform settings.
- Plan feature configuration.
- Module/library viewing and limited department metadata management.
- Question-group list/create/show/edit/archive.
- Question identity list/create/show with first draft-version creation.
- Question activation and replacement draft-version creation.
- Question-version approval and publication.
- Framework-version inspection and publication.
- Catalogue-release inspection and publication.
- Facility-profile inspection.
- Scoring and aggregation policy visibility.
- Domain-taxonomy inspection UI.
- Assessment lifecycle oversight.
- Report share-link oversight and emergency revocation.
- Audit-log inspection.
- Multi-respondent finalization safeguards.

## Intentionally system-managed

- Migration history.
- Content hashes.
- Published framework payloads.
- Published catalogue release manifests.
- Immutable assessment snapshots.
- Respondent score snapshots.
- Immutable report snapshots.

## Remaining future improvements

- Rich framework editor UI for creating, editing, and reordering sections.
- Rich framework editor UI for creating, editing, and reordering indicators.
- Rich framework editor UI for placing, replacing, weighting, and reordering exact question versions inside indicators.
- Version comparison UI.
- Dependency graph UI.
- Rich editor UI for catalogue composition.
- Advanced payment/provider operations dashboard beyond current webhook and plan-feature controls.

## Security assessment

Workspace Admins cannot access Platform Admin routes. Platform Admins can inspect and govern official content and platform operations. Workspace-local content remains separated from official Vytte methodology and scoring.
