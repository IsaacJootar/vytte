# Vytte Analytical Domain Architecture

## Decision

Vytte uses one small platform-governed universal analytical-domain taxonomy.

Domains are analytical lenses for interpretation, findings, recommendations, trends, and reporting. They are not departments, facility services, framework sections, indicators, question banks, question generators, scoring profiles, or workspace tags.

## Final universal taxonomy

1. Governance and Accountability (`GOV`)
2. Workforce and Capability (`WORK`)
3. Service Delivery and Access (`SERV`)
4. Safety and Quality (`SAFE`)
5. Infrastructure, Equipment and Supplies (`RES`)
6. Information, Measurement and Learning (`INFO`)
7. Person-Centredness and Community Responsiveness (`PCOM`)

Rejected/merged candidates:

- Equity and Inclusion is merged into governance, access, and person-centredness until a validated equity methodology requires a separate domain.
- Continuity, Referral and Coordination is merged into Service Delivery and Access for the first taxonomy.
- Resilience and Preparedness is merged into governance and resources until emergency-preparedness methods require a separate domain.

## Mapping level

Default mapping level is framework indicator to analytical domain.

Placement-level overrides are supported for exceptional cases where the same indicator-level mapping would misrepresent a specific question placement.

Question identity is deliberately not the main mapping level because the same reusable question can serve different analytical purposes in different frameworks.

## Assessment flow relationship

Assessment purpose -> published framework version -> sections -> indicators -> question placements -> responses/evidence -> official scoring -> analytical domains -> findings/recommendations/reports.

Domains never generate questions and never decide which departments are selected.

## Comprehensive assessments

Comprehensive assessments preserve department, framework, section, indicator, question, and evidence provenance. Domain results combine scored contributions across selected departments only after the assessment content is assembled and frozen.

## Focused assessments

Focused assessments use only domains relevant to the focused framework. They do not display or force every universal domain.

## Workspace content

Workspace-local sections do not affect official domain scores. Workspace custom assessments may use private categories/themes, but they do not produce official Vytte domain scores or official Vytte Health Scores.
