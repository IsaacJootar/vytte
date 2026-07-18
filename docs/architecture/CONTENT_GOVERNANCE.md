# Content Governance

## Authority

Vytte owns official assessment content.

Workspaces cannot publish official:

- departments;
- department framework versions;
- scoring methodologies;
- aggregation policies;
- catalogue releases;
- official questions or indicators.

Workspace users consume published Vytte content and may add local custom sections for local context only.

## Roles

- **Platform administrator:** manages platform configuration and may perform curator actions.
- **Curator:** prepares, validates, and publishes official Vytte framework and catalogue content.
- **Workspace owner/admin/member:** creates projects, runs assessments, manages workspace users, and consumes approved content.

## Department Framework Publication

A department framework version must define:

- one official department;
- version number;
- source authority and licence metadata;
- provenance where available;
- active questions with supported response types;
- answer options for option-based questions;
- numeric bounds and scoring bands for scored numeric questions;
- scoring profile mappings for every scored question;
- evidence and critical-failure metadata where applicable.

Publication freezes the exact payload and content hash. Published department framework versions cannot be edited or deleted.

## Facility Profiles

Vytte facility profiles define which departments are required, default, optional, or unavailable for a health facility type.

Facility profiles are official platform content. Workspaces select the profile that best matches the assessed facility.

## Catalogue Releases

A catalogue release maps:

- creation path;
- facility profile or focused health domain;
- exact published department framework versions;
- department applicability and order;
- aggregation policy;
- composition rules.

The system never resolves "latest" framework versions automatically. Every assessment resolves through a published catalogue release.

## Comprehensive Health Assessment

Comprehensive Health Assessment is a composition orchestrator. It owns no clinical questions.

It resolves the facility profile, loads the published catalogue release, applies required/default/optional department rules, and freezes one assessment snapshot.

## Focused Health Assessment

Focused assessments reuse official framework content without duplication. The first implemented focused flow resolves one published framework scope from a focused catalogue release.

## Local Custom Sections

Local custom sections:

- belong only to one workspace;
- cannot modify official content;
- cannot replace official questions;
- are visually and semantically local;
- are excluded from official scoring;
- may contain local notes, local questions, instructions, observations, and evidence prompts.

## Prohibited Publication

Official content cannot publish when it contains unsupported response types, missing provenance, missing licence metadata, empty question sets, option questions without choices, scored open text, scored numeric questions without bands, scored questions without scoring-profile membership, or scored options without weights.

Demonstration content must remain labelled as demonstration content and must not be promoted as production clinical methodology.
