# Data Model Audit

## Audit basis

The migration set is the schema source of truth. Forty-one migration files produce 67 application/framework tables after the obsolete `corroboration_gaps` subsystem is retired. A migrated SQLite database contains those tables plus Laravel's `migrations` table.

This is not a production-data audit. Local row counts are included only to show that repository documentation and the current seeded development state differ.

## Actual table inventory

### Framework and identity

`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`

### Workspace and platform governance

`workspaces`, `workspace_members`, `workspace_invitations`, `platform_settings`, `plan_features`, `audit_logs`

### Taxonomy and reference data

`target_types`, `target_categories`, `setting_types`, `target_type_setting_map`, `health_domains`, `assessment_module_health_domain`, `domains`, `domain_weights`, `maturity_levels`, `assessment_tiers`, `question_types`, `standards_registry`, `topics`, `respondent_roles`

### Module and question library

`assessment_modules`, `target_category_default_modules`, `module_domains`, `sub_indices`, `questions`, `question_options`, `question_numeric_bands`, `sub_index_questions`, `question_topics`, `question_drafts`, `question_translations`, `question_option_translations`

### Template and immutable content

`assessment_templates`, `assessment_template_versions`, `assessment_template_version_modules`, `assessment_snapshots`

### Projects, targets, and assessments

`targets`, `respondents`, `projects`, `project_targets`, `assessments`, `assessment_share_links`, `assessment_module_scope`, `assessment_topic_scope`, `assessment_respondent_tokens`, `public_response_sessions`, `respondent_consents`

### Responses, scoring, and outputs

`responses`, `response_options`, `observation_records`, `sub_index_scores`, `domain_scores`, `topic_scores`, `assessment_scores`, `project_domain_scores`, `project_scores`, `assessment_report_snapshots`, `root_causes`, `recommendation_rules`, `recommendations`

## Key implemented relationships

- Workspace to project: `projects.workspace_id`.
- Workspace to target: `targets.owner_workspace_id`.
- Project to target: the retained `project_targets` junction is constrained to one target per project for the approved v1 workflow.
- Assessment to project and target: direct foreign keys, with no `workspace_id` on the assessment.
- Assessment to module: composite-key `assessment_module_scope`.
- Module to questionnaire section: `module_domains`.
- Module/question: direct `questions.module_id` and optional `module_domain_id`.
- Question/sub-index: many-to-many `sub_index_questions`.
- Sub-index/global scoring domain: `sub_indices.domain_id`.
- Response to assessment/question/option: direct foreign keys. New public responses also have an enforced `public_response_session_id`; the legacy mixed-purpose `respondent_id` remains for compatibility.
- Optional evidence support is the nullable `responses.evidence_note`; it remains bound to the exact question response rather than becoming a separate evidence entity.
- Scores to assessment: composite assessment/sub-index/respondent-type, assessment/domain, and one assessment-score row.

## Documentation-to-schema discrepancy register

| ID | Documented model | Actual model | Risk | Safest disposition |
|---|---|---|---|---|
| DM-01 | 42 tables | 67 application/framework tables after current migrations | Resolved documentation | This inventory and migrations are authoritative; do not use historical fixed counts as an architecture contract. |
| DM-02 | UUID primary keys on all tables | Mixed UUID, integer, string, and composite keys | High | Preserve current keys; future template tables may use UUIDs without normalizing legacy keys. |
| DM-03 | `workspaces.owner_id` | No owner column; ownership is a membership role | Medium | Preserve membership-based ownership and document it; any owner column would duplicate authority. |
| DM-04 | `workspace_members.id`, timestamps | Composite `(workspace_id,user_id)` primary key plus `joined_at` | Medium | Preserve composite key; review Eloquent write semantics in Phase 22. |
| DM-05 | `assessment_module_definitions` catalogue and tenant `assessment_modules` join | Catalogue is `assessment_modules`; join is `assessment_module_scope` | Critical | Treat existing names as compatibility contracts. New template relations must reference the actual catalogue table. |
| DM-06 | One project has one target through a project FK | Equivalent invariant implemented through the retained junction plus a unique `project_id` index | Resolved | Keep the junction for compatibility; postpone multi-target behavior. |
| DM-07 | Target has `workspace_id` and `project_id` | Target has `owner_workspace_id`; project link is a junction | High | Use adapters/documentation; do not rename or add redundant ownership fields without approval. |
| DM-08 | Assessment has `workspace_id`, `tier_id`, name, description, created-by | Assessment has project/target, `assessment_tier_id`, scope and publication fields; no workspace/name/description/creator | High | Derive tenancy through project for now; template work must not assume documented columns exist. |
| DM-09 | Statuses `DRAFT`, `IN_PROGRESS`, `COMPLETED`, `ARCHIVED` | Assessment execution is `IN_PROGRESS -> COMPLETE`; area and template states are separate | Resolved | Follow `LIFECYCLE_STATE_MACHINE.md`; preserve the table-specific persisted values. |
| DM-10 | Module belongs to a global domain | Catalogue module does not; module has local `module_domains`, while sub-indices map to global `domains` | High | Preserve the distinction and rename only in documentation/UI language until a governed taxonomy decision. |
| DM-11 | Sub-index has code/name/display order and UUID key | Integer key with acronym/full name, module and global-domain links | Medium | Build future template mapping against actual identifiers; avoid normalization migration. |
| DM-12 | Question directly belongs to one sub-index and carries `score_weight` | Question belongs to module/section; sub-index is many-to-many; option carries score | Critical | Preserve flexible pivot. Define scoring-version semantics before reuse/versioning. |
| DM-13 | Options use `option_text`, `option_value`, and correctness | Options use `option_label`, `score_weight`, order, and pain flag | High | Treat current option rows and values as scoring contracts. |
| DM-14 | Response stores selected UUID arrays, generic value, notes, flags, workspace | Response stores scalar option/text/numeric values plus an optional inline `evidence_note`; multi-select storage remains inactive | Partially resolved | Keep evidence response-bound; add response types only with explicit renderer/storage contracts. |
| DM-15 | Unique response per assessment/question | Resolved for staff responses by a partial unique index; public responses retain respondent-specific uniqueness | Resolved | Preserve PostgreSQL parity tests for the partial index. |
| DM-16 | Respondent is an assessment respondent record | New public responses reference durable `public_response_sessions`; legacy rows may contain an unenforced session UUID in `respondent_id` | Legacy-only | Preserve old values; all new public writes must populate the session FK. |
| DM-17 | `response_flags` table | No response-flags table; flag columns are also absent | Medium | Postpone response review/flagging until explicitly designed. |
| DM-18 | Rich normalized `sub_index_scores` with workspace and counts | 0-100 `score`, respondent type, calibration status; no workspace/counts | High | Preserve score rows; version new algorithms rather than overwriting historical values. |
| DM-19 | Domain score includes maturity and workspace | Maturity exists only on `assessment_scores`; domain rows have score/status | Medium | Document actual behavior; do not duplicate maturity until a reporting requirement exists. |
| DM-20 | `facility_health_scores` | `assessment_scores` | Critical | Keep `assessment_scores` as the compatibility table. |
| DM-21 | `root_cause_analyses` | `root_causes`; active application does not use it | Medium | Leave dormant schema untouched until recommendations are approved. |
| DM-22 | Persisted `reports` and `report_sections` | Implemented as one immutable structured `assessment_report_snapshots` payload per completed assessment rather than mutable normalized sections | Resolved by equivalent model | Regenerate PDF/shared formats from the versioned payload; do not introduce parallel report authority. |
| DM-23 | `platform_settings.id` UUID | Auto-increment integer | Low | Preserve current key; consumers use the unique setting key. |
| DM-24 | Three assessment tiers | Seeder creates two | Medium | Decide whether tier three was removed or remains required before template applicability rules. |
| DM-25 | Seven domains | Seeder creates eight, including non-operational Clinical & Service Quality | Medium | Preserve all rows; govern operational vs clinical taxonomy explicitly. |
| DM-26 | 120 sub-indices and 528 questions | Current local seed has 4 sub-indices and 601 questions | Critical | Treat counts as dataset-version metadata, not architecture constants. Make seeding portable and reproducible before relying on counts. |

All discrepancy dispositions above require no Phase 21 implementation. The approval-gated choices are recorded in `DECISION_LOG.md`.

## Constraint and integrity findings

### Workspace isolation

Projects and targets carry direct workspace ownership. Workspace resolution verifies membership before route binding; Project and Assessment policies protect active routes; downstream authority is derived through the project/assessment relationship. New tenant-owned routes must follow the same scoped-query plus policy pattern.

### Response uniqueness

A partial unique index now enforces one staff response per assessment/question when `respondent_id IS NULL`. Respondent-specific responses retain the original composite uniqueness rule. PostgreSQL parity remains an operational verification item while local Docker is unavailable.

### Composite-key Eloquent models

`AssessmentModuleScope`, `DomainScore`, and `SubIndexScore` declare only one component as the Eloquent primary key. Reads work for current query patterns, but instance updates/deletes can target more rows than intended. Prefer explicit composite predicates until tested.

### Public response identity

`public_response_sessions` now owns anonymous participation state and is linked to new responses and consents by foreign keys. The older `respondent_id` column remains mixed-purpose for backward compatibility, but the public runner no longer relies on it as its only identity relationship.

### Mutable shared content

Published template versions store their exact content payload. New assessments own immutable content/scoring snapshots, and newly completed assessments own immutable final-report snapshots. Legacy non-template assessments retain a live-content compatibility path and must not be silently rewritten.

### Seed reproducibility

The default seed path is repository-contained. The former external PHSAI document dependency and incomplete school sample seeder were removed. Seed counts remain dataset metadata rather than architecture promises; governed content still requires source, licence, and review metadata.

### Score-scale inconsistency

The scorer normalizes 0–1 option scales to canonical 0–100 output and stores an algorithm version. Published template assessments read frozen option weights and mappings. PostgreSQL parity and independently curated scoring fixtures remain required release checks.

## Migrated but inactive structures

`assessment_topic_scope`, `response_options`, `observation_records`, `topic_scores`, `project_domain_scores`, `project_scores`, `root_causes`, `recommendation_rules`, and `recommendations` remain inactive/reserved. `audit_logs` and `assessment_share_links` are active governed infrastructure. The unused special-aggregation `corroboration_gaps` table is retired by migration.

## Local development snapshot

| Record type | Local count |
|---|---:|
| Workspaces / users | 4 / 4 |
| Projects / targets / assessments | 11 / 11 / 9 |
| Assessment modules | 27 |
| Domains | 8 |
| Assessment tiers | 2 |
| Sub-indices | 4 |
| Questions | 601 |
| Question options | 1,808 |
| Responses | 16 |
| Plan-feature rows | 24 |

These counts are not a production baseline and must not be used to infer production migration state.
