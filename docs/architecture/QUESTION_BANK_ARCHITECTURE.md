# Question Bank Architecture

Date: 2026-07-18

## Controlling model

Vytte official assessment content now follows this sequence:

Assessment purpose → framework → sections → indicators → question placements → question versions → responses and evidence → analysis → domains → findings and recommendations.

Questions are not generated from universal domains. Domains remain downstream analysis and scoring groupings.

## Official content ownership

Vytte owns official:

- reusable question identities;
- immutable question versions;
- framework sections;
- framework indicators;
- framework-specific question placements;
- department framework versions;
- focused framework versions;
- scoring rules;
- critical-failure rules;
- catalogue releases;
- publication and provenance.

Customer workspaces consume published official content. They cannot publish official questions, official framework versions, official focused assessments, or official catalogue releases.

## Data model

| Concept | Table/model | Purpose |
|---|---|---|
| Question identity | `questions` / `Question` | Conceptual reusable question identity and stable response key |
| Question version | `question_versions` / `QuestionVersion` | Immutable wording, response type, options, numeric config, numeric bands, methodology notes, and hash |
| Framework version | `department_framework_versions` / `DepartmentFrameworkVersion` | Official department or focused framework version |
| Framework section | `framework_sections` / `FrameworkSection` | Purpose-led framework grouping |
| Framework indicator | `framework_indicators` / `FrameworkIndicator` | Measurement objective inside a section |
| Framework placement | `framework_question_placements` / `FrameworkQuestionPlacement` | Exact question-version use inside a framework, with order, evidence, weight, criticality, and scoring behavior |
| Published framework payload | `department_framework_versions.published_payload` | Frozen rendered framework content |
| Assessment snapshot | `assessment_snapshots.payload` | Frozen exact assessment content used at runtime |

## Versioning rules

- Changing wording, response type, answer options, numeric config, or numeric bands creates a new `question_versions` row.
- Published question versions cannot be edited or deleted.
- Published framework versions pin exact `question_version_id` values.
- Published framework versions cannot be edited or deleted.
- Published catalogue releases pin exact framework-version IDs.
- Historical assessment snapshots are never recalculated from newer question or framework versions.

## Placement behavior

Each placement may define:

- section;
- indicator;
- order;
- required status;
- applicability;
- evidence expectation;
- score contribution;
- weight;
- criticality;
- help text;
- framework-specific display wording.

This allows the same reusable question version to appear in more than one framework while preserving different framework-specific purpose and reporting context.

## Runtime behavior

The runner and reports use immutable assessment snapshots. Response rows remain keyed to `question_id` for practical compatibility with the runner, while the snapshot stores:

- `question_version_id`;
- `question_version_number`;
- `question_version_hash`;
- rendered question text;
- canonical question text;
- response options;
- numeric config;
- evidence expectation;
- placement ID;
- section and indicator metadata;
- scoring profile and weights.

## Demonstration content

The demonstration dataset proves:

- 16 reusable question identities;
- 17 published question versions;
- one later published question version not used by existing published frameworks;
- 5 published official framework versions;
- 20 framework placements;
- one reusable question version placed in more than one framework;
- framework-specific display wording;
- a focused framework using governed question versions;
- comprehensive assessment composition using department frameworks;
- evidence expectations;
- scoring and critical-failure behavior.

Demo content is not validated production methodology.

