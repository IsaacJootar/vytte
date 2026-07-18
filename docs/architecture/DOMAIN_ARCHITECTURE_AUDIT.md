# Domain Architecture Audit

## Current-state finding

The repository now separates domain concepts by purpose.

1. `health_domains` identifies focused assessment topics such as Mental Health, HIV, WASH, and Patient Experience.
2. `domains` and the domain-taxonomy tables define analytical reporting domains.
3. `question_groups` groups questions inside official departments or focused scopes.

## Domain-related tables and migrations

- `health_domains` and `assessment_module_health_domain` support focused assessment selection.
- `domains`, `domain_taxonomies`, `domain_taxonomy_versions`, and `domain_definitions` support governed analytical-domain taxonomies.
- `framework_indicator_domain_mappings` and `framework_question_placement_domain_overrides` map framework indicators/placements into analytical domains.
- `domain_scores` and `project_domain_scores` store calculated analytical reporting outputs.
- `question_groups` is structural question organization only and must not be used as an analytical-domain source.

## Current behavior

- Domains do not own questions.
- Domains do not generate questions.
- Domains do not compose comprehensive or focused assessments.
- Framework indicators and placements map to analytical domains for reporting.
- Assessment snapshots and final reports freeze taxonomy metadata.
- Platform Admins can inspect taxonomy and mapping coverage.

## Product rule

Do not introduce another “domain” concept. New content should choose one of the existing purposes: focused health topic, analytical reporting domain, or structural question group.
