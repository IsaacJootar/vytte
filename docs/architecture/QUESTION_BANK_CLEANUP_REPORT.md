# Question Bank Cleanup Report

Date: 2026-07-18

## Summary

The old PHSAI question bank remains fully removed.

No old PHSAI questions were deliberately retained.

The previous direct framework-question source model was replaced with the approved architecture:

- reusable question identity;
- immutable question version;
- framework section;
- framework indicator;
- framework-specific question placement;
- published framework payload;
- immutable assessment snapshot.

## Old PHSAI content

No PHSAI questions, seeders, fixtures, tests, services, UI, or routes remain.

No old PHSAI question was copied, adapted, or reused into the new governed model.

## Added tables

- `question_versions`
- `framework_sections`
- `framework_indicators`
- `framework_question_placements`
- `workspace_custom_assessment_designs`

## Changed tables

`department_framework_versions` now includes:

- `framework_type`;
- `purpose`;
- `methodology_notes`;
- `source_summary`;
- `review_notes`;
- `reviewed_by`;
- `approved_by`;
- `effective_date`.

## Added models

- `QuestionVersion`
- `FrameworkSection`
- `FrameworkIndicator`
- `FrameworkQuestionPlacement`
- `WorkspaceCustomAssessmentDesign`

## Added services

- `QuestionVersionPublishingService`
- `WorkspaceCustomAssessmentService`

## Changed services

- `FrameworkContentService` now builds published framework payloads from exact question-version placements.
- `DepartmentFrameworkPublishingService` now validates exact published question versions and placement-level scoring contracts.

## Added policies

- `QuestionVersionPolicy`
- `DepartmentFrameworkVersionPolicy`
- `WorkspaceCustomAssessmentDesignPolicy`

## Demonstration content counts

Clean seeded PostgreSQL counts:

- reusable question identities: 16;
- published question versions: 17;
- official framework versions: 5;
- official framework placements: 20;
- framework sections: 5;
- framework indicators: 20.

The extra question version is a deliberately published future version proving that new question versions do not mutate existing framework payloads or assessment snapshots.

## Tests added

`QuestionBankArchitectureTest` covers:

- creating/reusing question identities;
- publishing immutable question versions;
- preventing edits to published question versions;
- using one question version in multiple frameworks;
- framework-specific placement behavior;
- rejecting draft question versions during framework publication;
- future question versions not changing historical framework or assessment snapshots;
- snapshot freezing exact question text, options, evidence, and scoring payload;
- preventing customer users from managing official question versions;
- local custom sections excluded from official scoring and critical failures;
- workspace custom assessment creation and isolation;
- custom scoring not claiming official Vytte scoring.

## Remaining methodological decisions

- Production clinical methodology remains uncurated.
- Future curator UI needs to expose question version review and publication workflows.
- Future workspace custom assessment runner/report UX is still a product-design task.
- Domain architecture review is intentionally postponed.

## Remaining risk

The architecture is ready for governed content, but demo content remains demonstration-only and must not be presented as validated clinical methodology.

