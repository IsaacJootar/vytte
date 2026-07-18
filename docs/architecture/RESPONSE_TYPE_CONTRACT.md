# Response Type Contract

Declared database types and publishable types are different. Official Vytte content may publish only response types with a renderer, storage rule, validation rule, completeness rule, snapshot serialization, and scoring or explicit unscored behavior.

| Type | Authenticated runner | External runner | Storage | Scoring | Publishable |
|---|---|---|---|---|---|
| `SINGLE_SELECT` | Yes | Yes | `responses.value_option_id` | Frozen option weight | Yes |
| `LIKERT` | Yes | Yes | `responses.value_option_id` | Frozen option weight | Yes |
| `OPEN_ENDED` | Yes | Yes | `responses.value_text` | Must be unscored | Yes |
| `NUMERIC` | Yes | Yes | `responses.value_numeric` | Frozen numeric bands when scored; explicit unscored measurement otherwise | Yes |
| True multi-select | No | No | Reserved | Undefined | No |
| Ranking | No | No | No active contract | Undefined | No |
| Observation | No | No | No active contract | Undefined | No |

Optional supporting evidence is stored in `responses.evidence_note`. It never satisfies answer completeness and never changes scoring.

Numeric questions freeze unit, minimum, maximum, step, and scoring bands in the department framework version and assessment snapshot. Scored numeric questions cannot publish without bands; unscored measurements may omit bands. Band upper bounds are exclusive except for the final band, which includes its upper bound.

Question and option identifiers are validated against the immutable assessment snapshot. Unsupported types must be rejected during official content publication rather than fail during an assessment.

Adding a response type requires:

1. authenticated and external renderers;
2. authoritative server validation;
3. unambiguous storage and update semantics;
4. completeness behavior;
5. snapshot serialization;
6. scoring semantics or an explicit unscored rule;
7. PDF/report presentation;
8. focused security and full regression tests.
