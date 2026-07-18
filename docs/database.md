# Vytte Database Schema

## Authority

Laravel migrations are the database source of truth. PostgreSQL is the database authority for local development, automated tests, release verification, and production.

## Core Table Groups

### Platform and Workspace

- `users`
- `workspaces`
- `workspace_members`
- `workspace_invitations`
- `platform_settings`
- `plan_features`
- `audit_logs`

### Taxonomy and Reference

- `target_types`
- `setting_types`
- `target_type_setting_map`
- `health_domains`
- `assessment_module_health_domain`
- `domains`
- `domain_taxonomies`
- `domain_taxonomy_versions`
- `domain_definitions`
- `framework_indicator_domain_mappings`
- `framework_question_placement_domain_overrides`
- `maturity_levels`
- `assessment_tiers`
- `question_types`
- `standards_registry`
- `topics`
- `respondent_roles`

### Official Assessment Content

- `assessment_modules`
- `question_groups`
- `sub_indices`
- `questions`
- `question_options`
- `question_numeric_bands`
- `sub_index_questions`
- `question_topics`
- `question_drafts`
- `question_translations`
- `question_option_translations`

### Governed Composition

- `department_framework_versions`
- `facility_profiles`
- `facility_profile_departments`
- `assessment_catalogue_releases`
- `assessment_catalogue_department_versions`

### Project and Assessment Runtime

- `targets`
- `projects`
- `project_targets`
- `assessments`
- `assessment_module_scope`
- `assessment_snapshots`
- `local_custom_sections`
- `assessment_share_links`

### Response, Scoring, and Reports

- `responses`
- `assessment_respondent_tokens`
- `public_response_sessions`
- `respondent_consents`
- `respondent_score_results`
- `assessment_aggregation_results`
- `sub_index_scores`
- `domain_scores`
- `assessment_scores`
- `assessment_report_snapshots`

## Key Relationships

- A workspace owns projects and targets.
- A project has exactly one assessed target through `project_targets`.
- A health-facility target may reference one official `facility_profile`.
- A facility profile defines required/default/optional departments.
- A department has immutable published framework versions.
- A catalogue release pins exact framework versions and aggregation policy.
- An assessment references the selected catalogue release.
- An assessment snapshot freezes the composed payload, manifest, and policy.
- Responses are validated against the frozen snapshot.
- Final reports are immutable structured snapshots.

## Important Columns

### `department_framework_versions`

- `framework_version_id`
- `module_id`
- `version_number`
- `status`
- `display_name`
- `source_authority`
- `license_code`
- `scoring_version`
- `content_hash`
- `published_payload`
- `published_at`
- `published_by`

### `facility_profiles`

- `facility_profile_id`
- `profile_code`
- `profile_name`
- `setting_type_code`
- `status`
- `display_order`

### `assessment_catalogue_releases`

- `catalogue_release_id`
- `release_code`
- `release_name`
- `creation_path`
- `facility_profile_id`
- `health_domain_id`
- `status`
- `aggregation_policy`
- `composition_rules`
- `content_hash`
- `published_at`
- `published_by`

### `assessment_snapshots`

- `snapshot_id`
- `assessment_id`
- `catalogue_release_id`
- `facility_profile_id`
- `creation_path`
- `setting_type_code`
- `health_domain_id`
- `content_hash`
- `composition_manifest`
- `aggregation_policy`
- `payload`
- `collection_config`

## Seed Data

Default seeding creates:

- platform settings;
- reference taxonomy;
- a small demonstration governed catalogue;
- plan features;
- demo users, workspaces, projects, and example assessments.

The demonstration catalogue is not production clinical methodology.

## Verification

Current local checks:

- full PHPUnit suite passes;
- disposable PostgreSQL `migrate:fresh --seed` passes;
- production frontend build passes.

Before release, run the same migration and test suite against PostgreSQL.
