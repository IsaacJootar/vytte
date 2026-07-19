# Data Model Audit

## Authority

The migration set is the schema source of truth. Current active assessment architecture is platform-governed catalogue composition.

## Table Groups

### Workspace and Platform

`users`, `workspaces`, `workspace_members`, `workspace_invitations`, `platform_settings`, `plan_features`, `audit_logs`, notifications, jobs, cache, and session tables.

### Taxonomy and Reference

`target_types`, `setting_types`, `target_type_setting_map`, `health_domains`, `assessment_module_health_domain`, `domains`, `domain_taxonomies`, `domain_taxonomy_versions`, `domain_definitions`, `framework_indicator_domain_mappings`, `framework_question_placement_domain_overrides`, `maturity_levels`, `assessment_tiers`, `question_types`, `standards_registry`, `topics`, `respondent_roles`.

### Official Vytte Content

`assessment_modules`, `question_groups`, `sub_indices`, `questions`, `question_options`, `question_numeric_bands`, `sub_index_questions`, `question_topics`, `question_drafts`, `question_translations`, `question_option_translations`.

### Governed Composition

`department_framework_versions`, `facility_profiles`, `facility_profile_departments`, `assessment_catalogue_releases`, `assessment_catalogue_department_versions`.

### Projects and Assessments

`targets`, `projects`, `project_targets`, `assessments`, `assessment_module_scope`, `assessment_snapshots`, `local_custom_sections`, `assessment_share_links`.

### Responses, Respondents, Scores, and Reports

`responses`, `assessment_respondent_tokens`, `public_response_sessions`, `respondent_consents`, `respondent_score_results`, `assessment_aggregation_results`, `sub_index_scores`, `domain_scores`, `assessment_scores`, `assessment_report_snapshots`.

### Reserved Tables

Respondent records, topic scope, multi-option response storage, observation records, topic/project score rollups, root-cause rows, recommendation rules, and recommendation rows are present but not current product authority.

The earlier assessment template tables are no longer present. They were dropped with the legacy template architecture and no code references them.

Reserved means present in the schema but not the authority for current assessment creation.

## Implemented Relationships

- Workspace to project: `projects.workspace_id`.
- Workspace to target: `targets.owner_workspace_id`.
- Project to target: `project_targets`, constrained to one target per project.
- Target to facility profile: nullable `targets.facility_profile_id`.
- Facility profile to department applicability: `facility_profile_departments`.
- Official department to framework versions: `department_framework_versions.module_id`.
- Catalogue release to pinned framework versions: `assessment_catalogue_department_versions`.
- Assessment to catalogue release: `assessments.catalogue_release_id`.
- Assessment to immutable snapshot: `assessment_snapshots.assessment_id`.
- Snapshot to catalogue/facility profile: `assessment_snapshots.catalogue_release_id` and `facility_profile_id`.
- Assessment to included/excluded departments: `assessment_module_scope`.
- Response to assessment/question/option/session: direct foreign keys plus nullable scalar answer columns.
- Final report to assessment: one `assessment_report_snapshots` row.

## Snapshot Authority

`assessment_snapshots` freezes:

- `payload`: exact composed content used by the runner and scorer;
- `composition_manifest`: catalogue release, release hash, facility profile, selected framework versions, selected hashes, and exclusions;
- `aggregation_policy`: frozen policy used by scoring/finalization;
- `collection_config`: respondent-collection settings and scoring-profile version.

An assessment snapshot is the runtime authority. Master content, future framework versions, and future catalogue releases do not change it.

## Local Custom Sections

`local_custom_sections` captures workspace-specific context. These rows are tenant-owned and assessment-bound. They do not enter official scoring and cannot mutate platform content.

## Integrity Findings

- The project model is one target per project.
- Assessment execution is `IN_PROGRESS -> COMPLETE`.
- Published department framework versions are immutable.
- Published catalogue releases are immutable.
- Response uniqueness separates staff answers from public-session answers.
- Numeric input configuration is frozen into published payloads and assessment snapshots.
- Scored numeric questions require scoring bands.
- Open-text questions are unscored contextual support.
- The default seed is repository-contained and includes demonstration governed content only.

## Current Counts

Counts are not architecture contracts. They describe only the current development seed and may change as governed content is curated.

## Release Gates

- PostgreSQL migration and concurrency parity.
- Advanced Platform Admin dependency graph and version comparison views.
- Production clinical content curation with source, licence, scoring, and review metadata.
