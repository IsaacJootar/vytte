# Workspace Admin Architecture

## Purpose

Workspace Admins govern local workspace operations only.

## Allowed controls

- manage workspace members and invitations;
- manage projects;
- create and run assessments;
- manage respondents and respondent links;
- manage report sharing inside their workspace;
- create local sections and workspace custom assessments;
- update workspace settings.

## Not allowed

Workspace Admins cannot modify:

- official Vytte departments;
- official facility profiles;
- official question groups;
- official question identities or immutable versions;
- official framework versions;
- official catalogue releases;
- official scoring profiles or aggregation policy;
- analytical-domain taxonomies;
- platform roles;
- platform settings;
- plan-feature definitions.

## Specific boundaries

Merged from the former `WORKSPACE_ADMIN_BOUNDARIES.md`, which duplicated this document.

Workspace Admins may:

- manage workspace members through existing team controls;
- create projects;
- create official assessments from published catalogue releases;
- include or exclude allowable departments during comprehensive assessment creation, with a reason where one is required;
- run assessments and submit responses;
- manage respondent links where permitted;
- finalize authorized multi-respondent collections;
- create and revoke workspace-owned report share links.

Workspace Admins may not:

- create official analytical domains;
- edit domain taxonomy versions;
- map indicators or placements to official domains;
- edit published question versions;
- edit published framework versions;
- rewrite immutable assessment snapshots or final report snapshots;
- make workspace custom content count as official Vytte scoring or benchmarking.

## Relationship to Platform Admin

Workspace Admins consume published official content through the assessment engine. Platform Admins govern the official content and platform operations that make that content available.
