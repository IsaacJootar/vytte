# Content Governance

## Roles

- **Platform administrator:** manages platform configuration and may perform curator actions.
- **Curator:** reviews and publishes assessment template versions.
- **Workspace owner/admin/member:** uses published templates inside authorized workspaces; does not publish global standard content.

## Draft to publication

1. Create or update mutable catalogue content for a draft.
2. Declare whether the template is comprehensive or focused.
3. Declare the setting for a comprehensive template or one health domain for a focused template.
4. Record source authority, source reference where available, and licence code.
5. Attach the intended modules and ordering.
6. Validate supported response types, active questions, scoring mappings, and option weights.
7. Publish through the curator-protected route.
8. Store the exact immutable payload and SHA-256 content hash.

Publication records publisher, time, and an audit event. A published version cannot be edited or deleted. Corrections require a new version.

## Standard versus customized content

- Standard templates are governed platform content.
- Comprehensive assessment exclusions create an assessment-owned customized snapshot; they do not mutate the standard version.
- Future question edits/additions must produce a labeled derivative or new governed version and revalidate comparability.
- Focused assessments always begin with one matching published scope.

## Prohibited publication

A version cannot publish when it has missing provenance/licence metadata, no active questions, unsupported response types, option questions without choices, invalid numeric bounds, scored numeric questions without frozen bands, scored open text, missing option weights, or scored questions absent from the scoring profile.

Sample seed content does not bypass these rules. Invalid samples should be removed or curated rather than weakening publication validation.

## Audit and review

The audit log records template-version publication. Future review/approval stages may extend the draft workflow, but must not make published versions mutable or create another template authority.
