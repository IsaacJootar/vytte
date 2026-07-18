# Question Group Rename and Cleanup Report

## Decision

The old structural grouping concept has been physically renamed to **Question Group**.

## Removed obsolete implementation

- Obsolete model removed: `App\Models\ModuleDomain`.
- Obsolete controller removed: `App\Http\Controllers\Admin\ModuleDomainController`.
- Obsolete table removed from fresh installs: `module_domains`.
- Obsolete question column removed from fresh installs: `questions.module_domain_id`.
- Obsolete active authorization/source references to the old structural naming removed.

## Current implementation

- Current table: `question_groups`.
- Current model: `App\Models\QuestionGroup`.
- Current question foreign key: `questions.question_group_id`.
- Current route family: `admin.question-groups.*`.
- Current import key: `question_groups`.
- Current UI label: Question Groups.

## Fresh PostgreSQL install result

Fresh migration and seed must create `question_groups`, must not create the former table, must create `questions.question_group_id`, and must not create the former question foreign-key column.

## Compatibility

The project is not in production, so no compatibility table, alias, feature flag, or dual-write layer is retained.
