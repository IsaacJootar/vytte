# Admin Publication Workflows

## Question version publication

1. A Vytte Platform Admin creates or edits a reusable question identity.
2. The system creates a draft question version.
3. A Platform Admin approves the draft.
4. A Platform Admin publishes the approved version.
5. The published version receives a content hash and becomes immutable.

## Framework publication

1. A framework version starts as a draft.
2. It must reference active departments/focused scopes, valid source metadata, sections, indicators, and exact published question versions.
3. Scored placements must map to the scoring profile.
4. A Platform Admin publishes through the framework publishing service.
5. The published framework receives a frozen payload, scoring version, content hash, publication timestamp, and publisher.

## Catalogue release publication

1. A catalogue release pins one or more published framework versions.
2. Comprehensive releases require a published facility profile.
3. Focused releases initially pin one focused framework.
4. Aggregation and collection configuration must pass validation.
5. A Platform Admin publishes through the catalogue publishing service.
6. The release becomes the official assessment creation entry point.

## Multi-respondent policy

Multi-respondent collection is disabled unless an immutable published catalogue explicitly enables it and defines the minimum completed respondent threshold, arithmetic-mean aggregation method, eligibility rules when applicable, and scoring profile version.
