# Response Type Contract

Declared database types and runnable/publishable types are different concepts. A template may publish only types with an implemented renderer, storage rule, validation rule, completeness rule, and scoring rule.

| Type | Authenticated runner | External runner | Storage | Scoring | Publishable |
|---|---|---|---|---|---|
| `SINGLE_SELECT` | Yes | Yes | `responses.value_option_id` | Frozen option weight | Yes |
| `LIKERT` | Yes | Yes | `responses.value_option_id` | Frozen option weight | Yes |
| `OPEN_ENDED` | Yes | Yes | `responses.value_text` | Must be unscored | Yes |
| Numeric types | No | No | Reserved `value_numeric` | Undefined | No |
| True multi-select | No | No | Reserved `response_options` | Undefined | No |
| Ranking | No | No | No active contract | Undefined | No |
| Observation | No | No | Dormant `observation_records` | Undefined | No |

Optional supporting evidence is stored in `responses.evidence_note`. It never satisfies answer completeness and never changes scoring.

Question and option identifiers are validated against the immutable assessment snapshot when present; legacy assessments validate against active in-scope content. Unsupported types must be rejected at template publication rather than fail during an assessment.

Adding a type requires:

1. authenticated and external renderers;
2. authoritative server validation;
3. unambiguous storage and update semantics;
4. completeness behavior;
5. snapshot serialization;
6. scoring semantics or an explicit unscored rule;
7. PDF/report presentation;
8. focused security and full regression tests.

