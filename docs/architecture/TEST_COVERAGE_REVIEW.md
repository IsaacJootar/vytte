# Test Coverage Review

## Release rule

Only one full sequential PostgreSQL run is release evidence. Parallel or batched test runs may support
development but can hide ordering and shared-database failures.

The 2026-08-15 baseline audit at commit `81c8618` passed 736 tests and 2,709 assertions. Modules added
after that baseline must run focused tests during development and a new full sequential suite before
deployment; the final release commit and result belong in the deployment record.

## Strong coverage

- authentication, suspension, sessions, roles, workspace isolation, and invitations;
- Platform Admin and workspace navigation/authorization;
- question-bank identity/versioning, framework composition, catalogue releases, and immutable snapshots;
- Governance Studio authoring, branching, simulation, scoring models, contribution promotion, and
  independent review separation;
- single-select, multi-select, Likert, numeric, open-ended, response states, evidence, authenticated and
  public response collection;
- completion, multi-respondent aggregation, scoring/calibration, report snapshots, results, exports,
  shares, portfolio/progress, actions, and reporting intelligence;
- local question formats, optional local scoring, and published-score separation;
- official seed chain, methodology reachability, production preflight, throttles, health, and security
  headers;
- PostgreSQL-specific migration, integrity, and concurrency behavior.

## Remaining coverage gaps

| Priority | Gap | When required |
|---|---|---|
| HIGH | Plan-to-content catalogue entitlement | Before differentiated paid catalogue access |
| HIGH | Automated restore drill against a disposable PostgreSQL database | Before public beta promotion |
| HIGH | Payment ledger, webhook idempotency, reconciliation, and failure recovery | Before paid launch |
| MEDIUM | Dependency graph and visual version comparison | With those future admin tools |
| MEDIUM | External monitoring-provider integration | When a provider and alert destination are approved |
| MEDIUM | Jurisdiction-specific content-validation fixtures | With each governed national adaptation |
| LOW | Vision-level response types such as ranked/date/repeatable/computed | Only when implemented as complete vertical slices |

## Maintenance

Update this review when a gap becomes implemented or a new publishable response type is added. Do not
copy test counts into architecture contracts; counts naturally grow and are meaningful only beside a
commit and date.
