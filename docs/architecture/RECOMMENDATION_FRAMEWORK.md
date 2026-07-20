# Recommendation Framework

Design only. **No generation is implemented in P4**, by instruction. This records the architecture that generation must fit, so that when it is built it is not retrofitted onto assumptions made now.

## Why the previous model was retired

`recommendation_rules` held one sub-index or measurement domain, one score threshold, and one templated sentence. That shape can express "if infection control scores below 60, say this". It cannot express any of the following, all of which the framework requires:

- the same score meaning different things under different analysis lenses;
- a recommendation triggered by a specific recorded answer rather than by an average;
- a recommendation that depends on the previous assessment;
- a recommendation suppressed because the evidence for it was never collected.

Extending the table would have carried its assumption forward. It was empty and unreferenced, so it was retired rather than adapted.

## Inputs

A recommendation may draw on any combination of:

| Input | Source | Available today |
| --- | --- | --- |
| Assessment objective | `assessment_objectives` | Yes |
| Analysis lens | `analysis_lenses` | Yes |
| Scores | `sub_index_scores`, `domain_scores` | Yes |
| Individual responses | `responses` | Yes |
| Pain points | `question_options.is_flagged_pain_point` | Yes |
| Critical failures | scoring calibration state | Yes |
| Evidence | `evidence_expectation` on placements | Partially — text only |
| Historical results | previous assessments of the same target | Yes |
| Benchmarks | peer results | No — requires a peer set |
| Risk level | derived | No |
| Context | facility profile, setting, location | Yes |

Two inputs are not available yet. A recommendation depending on them must state that it cannot be produced, rather than produce a weaker one silently.

## The rule that governs generation

**A recommendation must name the finding it came from.** A recommendation that cannot point at a specific score, response, pain point or trend is not a recommendation; it is generic advice, and generic advice in a health assessment report is worse than none because it borrows the authority of the assessment without its evidence.

This is the single constraint that any future generator — rule-based or AI — must satisfy.

## Why the same assessment yields different recommendation sets

The lens is an input, not a filter applied afterwards. Under **Risk**, a critical failure in oxygen supply outranks a broad service-delivery weakness. Under **Quality Improvement**, the same two swap places, because one is a single fixable item and the other is systemic. Neither ordering is wrong; they answer different questions.

## Recommendation types

Recorded for later use, not yet generated:

Operational · Clinical · Training · Policy · Infrastructure · Workforce · Governance · Quality Improvement

## Time horizons

Immediate Actions · Medium-term Actions · Long-term Actions

Horizon should be derived from effort and dependency, not from severity. A critical finding that needs a new building is not an immediate action, and presenting it as one makes the whole list less credible.

## What is deliberately not decided

- Whether generation is rule-based, AI-assisted, or both. The inputs and the naming constraint hold either way.
- Where generated recommendations are stored, and whether they are frozen into the report snapshot. The reproducibility contract suggests they must be, but that is a decision for the phase that builds generation.
- How a workspace edits or dismisses a recommendation.

These are recorded as open rather than guessed, because guessing them now would put assumptions into the schema that generation would inherit.
