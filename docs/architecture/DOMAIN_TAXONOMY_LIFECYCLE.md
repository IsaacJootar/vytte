# Domain Taxonomy Lifecycle

## Tables

- `domain_taxonomies`
- `domain_taxonomy_versions`
- `domain_definitions`
- `framework_indicator_domain_mappings`
- `framework_question_placement_domain_overrides`

## Lifecycle

1. Vytte creates or selects a taxonomy.
2. Vytte drafts a taxonomy version.
3. Vytte defines domain definitions for that version.
4. Vytte maps framework indicators, and optional placement overrides, to definitions from a published taxonomy version.
5. The taxonomy version is published and receives a content hash.
6. Published taxonomy versions are immutable.
7. Future changes require a new taxonomy version.
8. A published version may be superseded or archived, not edited.

## Immutability

Published taxonomy versions cannot have methodology notes, definitions, or hash fields changed in place. Historical assessments freeze taxonomy version IDs and hashes in their assessment snapshot manifest and final report payload.

## Administration

Platform admins can inspect domain taxonomy versions and mappings in the platform admin area. Workspace admins may view results that use official domains but cannot mutate official taxonomy content.
