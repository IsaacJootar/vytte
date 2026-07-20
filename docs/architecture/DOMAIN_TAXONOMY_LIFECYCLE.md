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

## Publication workflow

Added in the P4 validation pass. Before this the services existed but nothing invoked them, so a measurement domain added to the master list stayed inert with no governed way to bring it into force.

`admin.domain-taxonomies.*`:

1. **Start a new version** — `DomainTaxonomyPublishingService::startNewVersion()` copies every definition from the version in force into a new draft, and adds a stub for any measurement domain that version does not define. Copying rather than editing is required: a published version is immutable.
2. Refine the wording on the draft.
3. **Publish** — validates, computes a content hash, and supersedes the previous published version so exactly one is ever in force.

Both steps are audited: `domain.taxonomy.version_started` and `domain.taxonomy.published`.

### Completeness rule

Publication **refuses** a version that leaves any measurement domain undefined. A domain with no definition in the version in force carries no scores and appears in no report, while still looking active in the domain list. That is how `FIN` was left when it was first added, and the rule exists so it cannot happen again.

### Effect on existing content

Superseding a version does not touch anything already published. Framework versions freeze their content at publication, and `domain_scores` records the taxonomy version and hash it was measured against, so past reports stay readable and reproducible. Only new indicator mappings use the version now in force.

Only one draft may be open per taxonomy at a time, otherwise publication would be ambiguous about which draft it promotes.
