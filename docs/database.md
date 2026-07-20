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
- `question_versions`
- `question_options`
- `question_numeric_bands`
- `sub_index_questions`
- `question_topics`
- `question_drafts`
- `question_translations`
- `question_option_translations`

`questions` holds the reusable question identity. `question_versions` holds the immutable published wording, response type, options, numeric configuration, numeric bands, methodology metadata, and content hash. A framework references an exact `question_version_id`, never the identity alone.

### Governed Composition

- `department_framework_versions`
- `framework_sections`
- `framework_indicators`
- `framework_question_placements`
- `facility_profiles`
- `facility_profile_departments`
- `assessment_catalogue_releases`
- `assessment_catalogue_department_versions`

`framework_question_placements` binds one exact published question version into a framework section and indicator, carrying display order, required state, evidence expectation, weight, scoring contribution, criticality, and framework-specific display wording.

### Plans and Workspace Content

- `subscription_plans`
- `plan_features`
- `workspace_custom_assessment_designs`

### Project and Assessment Runtime

- `targets`
- `projects`
- `project_targets`
- `assessments`
- `assessment_module_scope`
- `assessment_snapshots`
- `local_custom_sections`
- `assessment_share_links`

### Methodology Layer

Sits above the assessment platform. Adds no column to questions, frameworks, snapshots, scoring or reporting.

- `methodology_versions`
- `assessment_objectives`
- `health_areas`
- `analysis_lenses`
- `insight_categories`
- `assessment_templates`
- `objective_recommendations`
- `objective_presets`

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

### Reserved Tables

Present in the schema but not current product authority. Do not build new behavior on them without a fresh design decision. `DATA_MODEL_AUDIT.md` and `PRESERVATION_REGISTER.md` carry the same list.

- `assessment_topic_scope`
- `respondents`
- `response_options`
- `observation_records`
- `topic_scores`
- `project_domain_scores`
- `project_scores`

`root_causes`, `recommendation_rules` and `recommendations` were retired in P4. See the preservation register and `RECOMMENDATION_FRAMEWORK.md`.

### Framework Tables

Standard Laravel infrastructure, listed for completeness: `migrations`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `password_reset_tokens`.

## Key Relationships

- A workspace owns projects and targets.
- A project has exactly one assessed target through `project_targets`.
- A health-facility target may reference one official `facility_profile`.
- A facility profile defines required/default/optional departments.
- A department has immutable published framework versions.
- A framework version owns sections and indicators, and places exact published question versions through `framework_question_placements`.
- A question identity in `questions` may have many immutable rows in `question_versions`, linked in sequence by `parent_version_id`.
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

### `users`

Columns relevant to platform governance:

- `user_id`
- `name`
- `email`
- `account_type`
- `platform_role` — `PLATFORM_ADMIN` or null. Separate from workspace role.
- `suspended_at` — null when active. Set, sign-in is blocked and existing sessions are ended.
- `suspension_reason` — required whenever `suspended_at` is set. Shown to the person at sign-in.
- `active_workspace_id`
- `theme`, `locale`

Suspension is additive: no row is removed and no membership, authored content, or history is affected. See DEC-2026-07-19-021.

### `workspaces`

- `workspace_id`
- `name`
- `workspace_type`
- `slug`
- `plan`
- `status` — `ACTIVE`, `SUSPENDED` (on hold), or `ARCHIVED` (closed). Enforced by `EnsureWorkspaceIsActive`, not merely recorded.
- `settings`

### `sessions`

Server-side session storage. Required for `SessionRevocationService` to end sessions when access is removed; the service is inert on any other session driver.

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

### Methodology Layer Columns

- `methodology_versions` — the governed container. Published versions are immutable and carry a content hash.
- `assessment_objectives` — why an assessment is run. Purposes only.
- `health_areas` — subdivisions of `health_domains`.
- `analysis_lenses` — how results are interpreted. Holds no score.
- `insight_categories` — the shape a finding takes. `polarity` and `is_diagnostic`.
- `assessment_templates` — official starting points. `scope_type` is ENTERPRISE or FOCUSED.
- `objective_recommendations` — what an objective suggests. Suggestions only.
- `objective_presets` — saved starting combinations.

Retired in P4: `recommendations`, `recommendation_rules`, `root_causes`. See the preservation register.

See `HEALTH_METHODOLOGY_ARCHITECTURE.md`.
