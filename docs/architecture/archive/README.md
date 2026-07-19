# Archived Documentation

Everything in this folder is a **historical record**. It was accurate when written and describes a
past state of the repository.

**Do not treat these documents as current. Do not rewrite them to match today's code.** Correcting a
point-in-time record destroys the evidence it exists to preserve. If one of these documents is wrong
about today, that is expected; read the living documents in `docs/architecture/` instead.

Archived 2026-07-19 during the Phase A.5 documentation alignment.

## Why these were archived

The repository held roughly twenty point-in-time records alongside its living documents, with
nothing to distinguish them. Engineers were directed to trust all of them equally. That is how two
conflicting test counts, both wrong, came to appear in seven files, and how a false claim about the
schema survived in the first document a new engineer reads.

## Contents

### Verification records

Point-in-time verification runs. Their test counts are the origin of conflicting figures that
previously appeared in living documents. Note that all of them describe **batched** verification;
the first full sequential run happened later and found five failures.

- `FINAL_ADMIN_VERIFICATION.md`
- `FINAL_ARCHITECTURE_VERIFICATION.md`
- `FINAL_PRODUCTION_READINESS_AUDIT.md`

### Provenance records

- `CURRENT_QUESTION_BANK_STATE_AUDIT.md` — the record establishing that the earlier PHSAI question
  bank was not migrated, copied, adapted, or reused. The highest-value historical document here.
  One detail is already stale: the documentation residue it reports in the former
  `docs/modules/01-foundation.md` has since been removed. Preserved unedited.
- `QUESTION_BANK_CLEANUP_REPORT.md`
- `QUESTION_GROUP_RENAME_AND_CLEANUP_REPORT.md`
- `DOMAIN_CLEANUP_REPORT.md`, `DOMAIN_ARCHITECTURE_AUDIT.md` — why `module_domains` was removed. The
  engineering handover forbids reintroducing it; the reasoning lives here.

### Completed-phase records

- `PLATFORM_ADMIN_IMPLEMENTATION_REPORT.md`
- `PLATFORM_ADMIN_COMPLETION_AUDIT.md`
- `PLATFORM_ADMIN_CAPABILITY_AUDIT.md`
- `IMPLEMENTATION_PROGRESS.md` — a snapshot at commit `44f0186`
- `PHASE_21_RECOMMENDATION.md` — records an architecture that was considered and rejected
- `BUILD_PHASES.md` — the former `docs/phases.md`, the module-era build plan
- `MODULE_01_FOUNDATION.md` — the former `docs/modules/01-foundation.md`

### Superseded by merge

Content was folded into a living document; the originals are kept for history.

- `WORKSPACE_ADMIN_BOUNDARIES.md` — merged into `WORKSPACE_ADMIN_ARCHITECTURE.md`
- `PRODUCTION_GO_LIVE_CHECKLIST.md` — merged into `GO_LIVE_CHECKLIST.md`
