# Platform Admin Architecture

Platform administration is separated from workspace administration.

## Platform admins may manage or inspect

- Official content and publication state.
- Official question identities and versions.
- Framework versions.
- Facility profiles.
- Catalogue releases.
- Domain taxonomies and mappings.
- Scoring and aggregation policy metadata.
- Platform settings.
- Plan feature controls.
- Workspace overview.

## Workspace admins may manage

- Workspace users and roles within workspace boundaries.
- Workspace projects.
- Workspace assessment operations.
- Workspace local sections.
- Workspace custom assessments.
- Workspace-owned reports and share links.

## Immutable objects

Published official content, assessment snapshots, respondent score snapshots, and final reports are inspected, not edited in place.

## Current admin route additions

- `admin.domain-taxonomies.index`
- `admin.domain-taxonomies.show`
