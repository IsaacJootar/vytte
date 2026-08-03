# Response Type Contract

Declared database types and publishable types are different. Official Vytte content may publish only response types with a renderer, storage rule, validation rule, completeness rule, snapshot serialization, and scoring or explicit unscored behavior.

| Type | Authenticated runner | External runner | Storage | Scoring | Publishable |
|---|---|---|---|---|---|
| `SINGLE_SELECT` | Yes | Yes | `responses.value_option_id` | Frozen option weight | Yes |
| `MULTI_SELECT` | Yes | Yes | `responses.typed_value.option_ids` | Frozen mean of selected option weights | Yes |
| `LIKERT` | Yes | Yes | `responses.value_option_id` | Frozen option weight | Yes |
| `OPEN_ENDED` | Yes | Yes | `responses.value_text` | Must be unscored | Yes |
| `NUMERIC` | Yes | Yes | `responses.value_numeric` | Frozen numeric bands when scored; explicit unscored measurement otherwise | Yes |
| Ranking | No | No | No active contract | Undefined | No |
| Observation | No | No | No active contract | Undefined | No |

Optional supporting evidence is stored in `responses.evidence_note`. It never satisfies answer completeness and never changes scoring.

Every response also has an explicit state. `ANSWERED` carries the typed value. `NOT_APPLICABLE`, `UNKNOWN`, `NOT_ASSESSED`, `NOT_OBSERVED`, and `DECLINED` carry no answer value but satisfy collection completeness by recording why a direct answer was not supplied. `MISSING` remains incomplete. A scored `NOT_APPLICABLE` item is removed from that response set's denominator; the other non-answer states remain missing for scoring and make the result partial rather than being converted to zero.

`responses.typed_value` is the canonical extensible envelope for new response types. Scalar option, text, and numeric answers continue to populate their established columns for compatibility and also write an equivalent typed envelope. Multi-select stores a sorted, unique list of valid frozen option identifiers and uses the mean of their frozen instrument-specific scores. The score rule is versioned with the instrument; a future aggregation method requires a new scoring-model version.

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
