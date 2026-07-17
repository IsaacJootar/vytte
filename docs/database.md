# Vytte — Database Schema

Source: phsai_schema.sql v1.1 (42 tables). Laravel migrations must translate 1:1 — preserve all column names, constraints, CHECKs, and indexes exactly.

## Primary key convention

All tables use UUID primary keys via Laravel's `HasUuids` trait.
Non-standard PK names must be declared: `protected $primaryKey = 'workspace_id';`

## Table inventory (42 tables)

### Platform / reference tables (no workspace_id)

| Table | Purpose |
|---|---|
| `users` | Auth — email, password, platform_role (nullable: PLATFORM_ADMIN, CURATOR) |
| `workspaces` | Tenant root — name, slug, owner_id, plan, status |
| `workspace_members` | workspace_id + user_id + role (OWNER/ADMIN/MEMBER) |
| `workspace_invitations` | Pending email invites |
| `platform_settings` | Key-value store for platform-wide toggles (e.g. email.notifications_enabled) |
| `domains` | 7 PHSAI domains — pre-seeded, never per-tenant |
| `assessment_tiers` | BASIC / ENHANCED / COMPREHENSIVE |
| `target_types` | HOSPITAL, CLINIC, PHARMACY, etc. |
| `topics` | Cross-cutting topics (pre-seeded) |
| `question_types` | SINGLE_CHOICE, MULTIPLE_CHOICE, BOOLEAN, TEXT, NUMERIC, RATING |
| `maturity_levels` | 5 levels: CRITICAL → EXEMPLARY |
| `respondent_roles` | The roles that answer questions (pre-seeded) |
| `standards_registry` | External standards referenced by questions (pre-seeded) |

### Assessment framework (platform-level, no workspace_id)

| Table | Purpose |
|---|---|
| `assessment_module_definitions` | 23 named modules (OPD, ANC, HIVAW, etc.) — pre-seeded |
| `sub_indices` | 120 sub-indices linked to modules — pre-seeded (phsai_seed_sub_indices.sql) |
| `questions` | 528 questions — pre-seeded, score_weight NULL until calibrated |
| `question_options` | Answer options for structured questions |
| `question_standards` | Junction: question ↔ standards_registry |
| `question_respondent_roles` | Junction: question ↔ respondent_roles |

### Workspace tenant data (all have workspace_id FK)

| Table | Purpose |
|---|---|
| `projects` | A diagnostic initiative within a workspace |
| `targets` | The entity being assessed (a health facility) |
| `assessments` | A single diagnostic run — belongs to project |
| `assessment_modules` | Which modules are included in an assessment |
| `assessment_respondents` | People who will complete parts of the assessment |
| `responses` | One row per answered question per assessment |
| `response_flags` | Issues flagged during response review |
| `sub_index_scores` | Computed sub-index scores (null if NOT_CALIBRATED) |
| `domain_scores` | Computed domain scores |
| `facility_health_scores` | Overall facility score per assessment |
| `root_cause_analyses` | Top issues identified by the scoring engine |
| `recommendations` | Action items generated from root causes |
| `reports` | Generated diagnostic reports |
| `report_sections` | Individual sections of a report |
| `audit_logs` | Immutable activity log per workspace |

## Key columns by table

### users
```sql
id uuid PK
name varchar(255)
email varchar(255) UNIQUE NOT NULL
email_verified_at timestamp nullable
password varchar(255)
platform_role varchar(50) nullable CHECK IN ('PLATFORM_ADMIN','CURATOR')
active_workspace_id uuid nullable FK workspaces
remember_token varchar(100)
created_at, updated_at
```

### workspaces
```sql
workspace_id uuid PK
name varchar(255) NOT NULL
slug varchar(100) UNIQUE NOT NULL
owner_id uuid FK users NOT NULL
plan varchar(50) DEFAULT 'FREE'
status varchar(50) DEFAULT 'ACTIVE' CHECK IN ('ACTIVE','SUSPENDED','CANCELLED')
settings jsonb DEFAULT '{}'
created_at, updated_at
```

### workspace_members
```sql
id uuid PK
workspace_id uuid FK workspaces CASCADE NOT NULL
user_id uuid FK users CASCADE NOT NULL
role varchar(50) NOT NULL CHECK IN ('OWNER','ADMIN','MEMBER')
created_at, updated_at
UNIQUE (workspace_id, user_id)
```

### projects
```sql
project_id uuid PK
workspace_id uuid FK workspaces CASCADE NOT NULL
name varchar(255) NOT NULL
description text nullable
target_id uuid FK targets nullable  -- v1: set after target created
status varchar(50) DEFAULT 'ACTIVE'
created_by uuid FK users NOT NULL
created_at, updated_at
```

### targets
```sql
target_id uuid PK
workspace_id uuid FK workspaces CASCADE NOT NULL
project_id uuid FK projects CASCADE NOT NULL
name varchar(255) NOT NULL
type_id uuid FK target_types NOT NULL
location_state varchar(100) nullable
location_lga varchar(100) nullable
location_address text nullable
ownership varchar(50) nullable CHECK IN ('PUBLIC','PRIVATE','FAITH_BASED','NGO')
bed_capacity integer nullable
staff_count integer nullable
metadata jsonb DEFAULT '{}'
created_at, updated_at
```

### assessments
```sql
assessment_id uuid PK
workspace_id uuid FK workspaces CASCADE NOT NULL
project_id uuid FK projects CASCADE NOT NULL
target_id uuid FK targets NOT NULL
tier_id uuid FK assessment_tiers NOT NULL
name varchar(255) NOT NULL
description text nullable
status varchar(50) DEFAULT 'DRAFT' CHECK IN ('DRAFT','IN_PROGRESS','COMPLETED','ARCHIVED')
started_at timestamp nullable
completed_at timestamp nullable
created_by uuid FK users NOT NULL
created_at, updated_at
```

### assessment_module_definitions (platform-level, pre-seeded)
```sql
module_id uuid PK
domain_id uuid FK domains NOT NULL
name varchar(255) NOT NULL
code varchar(50) UNIQUE NOT NULL  -- e.g. 'OPD', 'ANC', 'HIVAW'
description text nullable
display_order integer DEFAULT 0
is_active boolean DEFAULT true
created_at, updated_at
```

### sub_indices (platform-level, pre-seeded)
```sql
sub_index_id uuid PK
module_id uuid FK assessment_module_definitions NOT NULL
name varchar(255) NOT NULL
code varchar(100) UNIQUE NOT NULL
description text nullable
display_order integer DEFAULT 0
created_at, updated_at
```

### questions (platform-level, pre-seeded, 528 rows)
```sql
question_id uuid PK
sub_index_id uuid FK sub_indices NOT NULL
module_id uuid FK assessment_module_definitions NOT NULL
question_code varchar(50) UNIQUE NOT NULL  -- e.g. 'OPD.D1.Q1'
question_text text NOT NULL
question_type_id uuid FK question_types NOT NULL
score_weight decimal(5,4) nullable  -- NULL until calibrated
score_band varchar(50) nullable
is_required boolean DEFAULT true
allows_notes boolean DEFAULT false
display_order integer DEFAULT 0
is_active boolean DEFAULT true
created_at, updated_at
```

### question_options
```sql
option_id uuid PK
question_id uuid FK questions CASCADE NOT NULL
option_text varchar(500) NOT NULL
option_value decimal(5,4) nullable
display_order integer DEFAULT 0
is_correct boolean DEFAULT false
created_at, updated_at
```

### responses
```sql
response_id uuid PK
workspace_id uuid FK workspaces CASCADE NOT NULL
assessment_id uuid FK assessments CASCADE NOT NULL
question_id uuid FK questions NOT NULL
respondent_id uuid FK assessment_respondents nullable
selected_option_ids uuid[] nullable  -- array of option_ids for multi-select
response_value text nullable
notes text nullable
is_flagged boolean DEFAULT false
flagged_reason text nullable
responded_at timestamp nullable
created_at, updated_at
UNIQUE (assessment_id, question_id)
```

### sub_index_scores
```sql
score_id uuid PK
workspace_id uuid FK workspaces CASCADE NOT NULL
assessment_id uuid FK assessments CASCADE NOT NULL
sub_index_id uuid FK sub_indices NOT NULL
raw_score decimal(8,4) nullable
normalized_score decimal(5,4) nullable  -- 0.0 to 1.0
calibration_status varchar(50) DEFAULT 'NOT_CALIBRATED' CHECK IN ('NOT_CALIBRATED','CALIBRATED','PARTIAL')
response_count integer DEFAULT 0
weighted_response_count integer DEFAULT 0
computed_at timestamp nullable
created_at, updated_at
UNIQUE (assessment_id, sub_index_id)
```

### domain_scores
```sql
score_id uuid PK
workspace_id uuid FK workspaces CASCADE NOT NULL
assessment_id uuid FK assessments CASCADE NOT NULL
domain_id uuid FK domains NOT NULL
score decimal(5,4) nullable
maturity_level_id uuid FK maturity_levels nullable
calibration_status varchar(50) DEFAULT 'NOT_CALIBRATED'
computed_at timestamp nullable
UNIQUE (assessment_id, domain_id)
```

### facility_health_scores
```sql
score_id uuid PK
workspace_id uuid FK workspaces CASCADE NOT NULL
assessment_id uuid FK assessments NOT NULL UNIQUE
overall_score decimal(5,4) nullable
maturity_level_id uuid FK maturity_levels nullable
calibration_status varchar(50) DEFAULT 'NOT_CALIBRATED'
computed_at timestamp nullable
```

### platform_settings
```sql
id uuid PK
key varchar(255) UNIQUE NOT NULL
value text nullable
type varchar(50) DEFAULT 'string' CHECK IN ('string','boolean','integer','json')
description text nullable
created_at, updated_at
```

## Seeds required

On `php artisan db:seed`, must populate:
1. `domains` — 7 PHSAI domains
2. `maturity_levels` — 5 levels
3. `assessment_tiers` — 3 tiers
4. `target_types` — facility types
5. `question_types` — 6 types
6. `respondent_roles` — named roles
7. `assessment_module_definitions` — 23 modules
8. `sub_indices` — 120 sub-indices (from phsai_seed_sub_indices.sql, corrected to v1.1 names)
9. `questions` — 528 questions with NULL score_weight (except HIVAW: 9 questions with real weights)
10. `question_options` — all options for all structured questions
11. `platform_settings` — `email.notifications_enabled` = false

## Migration order (respects FK dependencies)

1. users (modified: add platform_role, active_workspace_id — added after workspaces migration)
2. workspaces
3. workspace_members
4. workspace_invitations
5. platform_settings
6. domains
7. maturity_levels
8. assessment_tiers
9. target_types
10. topics
11. question_types
12. respondent_roles
13. standards_registry
14. assessment_module_definitions
15. sub_indices
16. questions
17. question_options
18. question_standards
19. question_respondent_roles
20. projects
21. targets (add project_id FK to projects after targets table)
22. assessments
24. assessment_modules
25. assessment_respondents
26. responses
27. response_flags
28. sub_index_scores
29. domain_scores
30. facility_health_scores
31. root_cause_analyses
32. recommendations
33. reports
34. report_sections
35. audit_logs
36. Add active_workspace_id FK to users (separate migration after workspaces created)

## Note on phsai_seed_sub_indices.sql

The seed file uses v1.0 column names: `section_id` and `sections`.
When writing the sub_indices seeder, correct to v1.1 names: `module_id` and `assessment_module_definitions`.
