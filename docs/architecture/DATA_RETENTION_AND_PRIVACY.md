# Data Retention and Privacy Boundary

## Current behavior

Vytte currently preserves assessment responses, consent records, external respondent sessions, audit events, immutable report snapshots, and revoked link records. There is no automated retention/deletion job.

Revoking a respondent or report link prevents future use; it does not delete previously submitted assessment data or audit history.

## Data minimization

- External sessions use opaque identifiers and do not require a respondent name.
- Consent stores the exact text, actor/session reference, and timestamp.
- Evidence is an optional text note attached to a response; no general file repository exists.
- Audit records store event metadata and token/link prefixes or record IDs, not full secret tokens.
- Geographic admin views remain aggregate and must not expose respondent identity.

## Production gate

Before production collection from patients, citizens, caregivers, minors, or other external respondents, the deployment owner must approve:

- lawful purpose and consent wording;
- retention periods by data class;
- deletion/anonymization procedure;
- subject-access and correction handling where applicable;
- evidence-note sensitivity guidance;
- backup retention;
- incident-response ownership;
- country/jurisdiction requirements.

Until that policy is configured, Vytte must not add file evidence, respondent profiling, or new personally identifying fields.

## Immutable records

Completed report snapshots and audit events are intentionally immutable. A future deletion policy must define whether legal deletion removes the whole assessment, irreversibly anonymizes respondent-linked records, or retains a minimal non-identifying audit tombstone. That decision must be made before automated deletion is implemented.
