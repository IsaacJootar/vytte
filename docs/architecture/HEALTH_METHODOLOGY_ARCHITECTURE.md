# Health Methodology Architecture

The official health knowledge model. Introduced in P4.

## Position

The methodology layer sits **above** the assessment platform. It adds no column to, and changes no behaviour of, questions, frameworks, catalogue releases, snapshots, scoring or reporting. An assessment that never references an objective behaves exactly as it did before P4, and a test pins that.

This is deliberate. The platform below is already governed, versioned and immutable. Reaching into it to add methodology would have put two authorities over the same rows.

## Vocabulary

Three terms were previously conflated. They are now separate and must stay separate.

| Term | Table | What it is | Holds a score? |
| --- | --- | --- | --- |
| **Health Domain** | `health_domains` | The subject being assessed: HIV, Malaria, WASH, Nutrition | No |
| **Health Area** | `health_areas` | A subdivision of a health domain: Antiretroviral Treatment, Cold Chain | No |
| **Measurement Domain** | `domains` | A dimension scores roll up into: Governance, Workforce, Service Delivery | **Yes** — `domain_scores` |
| **Analysis Lens** | `analysis_lenses` | How results are read: Performance, Risk, Trend, Executive Summary | No |

Before P4, `DOMAIN_ARCHITECTURE.md` described Measurement Domains as "analytical lenses". That wording is retired. The distinguishing test: *Executive Summary* is a valid Analysis Lens and could never be a Measurement Domain, because nothing rolls up into it.

## Entities

| Entity | Table | Purpose |
| --- | --- | --- |
| Methodology Version | `methodology_versions` | The governed container. Everything below belongs to exactly one. |
| Assessment Objective | `assessment_objectives` | Why an assessment is being run. Purposes only. |
| Health Area | `health_areas` | Subdivision of an existing health domain. |
| Analysis Lens | `analysis_lenses` | How results are interpreted. |
| Insight Category | `insight_categories` | The shape a finding takes in a report. |
| Assessment Template | `assessment_templates` | An official starting point. |
| Objective Recommendation | `objective_recommendations` | What an objective suggests. Suggestions only. |
| Objective Preset | `objective_presets` | A saved starting combination. |

## Objectives are purposes, never subjects

"Malaria" is not an objective; a Baseline applied to Malaria is. Subjects live in health domains.

Had both carried "Malaria", a user would not know which to pick and objective mapping would become circular. A test asserts no health-domain subject appears in the objective catalogue.

The familiar name is preserved through an **Objective Preset**: "Malaria Baseline Assessment" preselects the Baseline objective, the relevant health domains, a template and a set of lenses. A preset is a saved combination, not a third entity.

## One version, published together

Objectives recommend lenses, templates and areas. Versioning each independently would allow publishing an objective that points at a lens which does not exist, and the reader would see an empty recommendation with no explanation.

`MethodologyPublishingService` therefore refuses to publish a version whose recommendations do not resolve within that same version, and computes a content hash over the whole contents. A report citing a methodology version can be traced to the exact objectives, lenses and categories in force when it was produced — the same reproducibility contract the question and framework layers hold.

Health domains, measurement domains and evidence types are referenced but not validated at publication, because they have their own lifecycle outside the methodology version.

## Recommendations are never mandatory

Nothing in `objective_recommendations` restricts what an author may build or publish. The builder, publication validation and scoring profile remain the only authorities. A recommendation shortens the path to a sensible starting point; disregarding it costs nothing.

The catalogue is deliberately sparse. A recommendation an author disagrees with wastes their time, so only confident pairings are recorded.

## Analysis lenses and why one assessment yields several reports

A lens holds no score and changes no score. It selects and orders what has already been measured. The same completed assessment can therefore produce:

- a **Risk** report leading with critical failures and pain points, ignoring the average;
- a **Performance** report leading with the same results as achievement against expectation;
- an **Executive Summary** reducing both to what leadership must know.

None of these recalculates anything. This is what makes multiple valid readings possible without duplicated scoring logic.

Two lenses have preconditions and must say so rather than render empty: **Trend** requires at least two completed assessments of the same target, and **Benchmarking** requires a peer set.

## Insight categories and pain points

`polarity` records whether a finding is good, bad or neutral news, so a report can lead with what matters instead of an arbitrary order.

`is_diagnostic` marks categories that point at a cause rather than describing a symptom. **Pain Points** is the first of these, and it is not new: the platform already flags pain points at option level through `question_options.is_flagged_pain_point`, and already records critical failures during scoring. P4 promotes that existing signal to a reportable finding. This matters because a pain point is traceable to an exact recorded answer, whereas a low score is an average that can hide the specific thing that is wrong.

## Enterprise and focused assessments

`assessment_templates.scope_type` is `ENTERPRISE` or `FOCUSED`. The difference is breadth only. Both run through the same builder, scoring, reporting and diagnostics with no duplicated logic, exactly as the existing `creation_path` distinction already does for catalogue releases.

## Retired

`recommendations`, `recommendation_rules` and `root_causes` were removed in P4. They encoded a single-threshold model — one sub-index, one score cut-off, one templated sentence — which cannot express the combination the recommendation framework requires. All three were empty and unreferenced. See `RECOMMENDATION_FRAMEWORK.md` and the preservation register.
