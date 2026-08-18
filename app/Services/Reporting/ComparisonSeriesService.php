<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * The one place "what counts as comparable" is decided.
 *
 * Comparability is proven by a frozen comparison_signature, never assumed from a superficially
 * similar composition_hash — composition_hash also varies with cosmetic data (exclusion-reason
 * text, catalogue publisher metadata) that has no bearing on whether two scores mean the same
 * thing. The one documented exception is historical data that predates comparison_signature
 * entirely: when neither run ever received one, a matching composition_hash is accepted as the
 * legacy equivalent.
 *
 * TrendService, PortfolioService, and ComparisonEligibilityService all read comparability through
 * this service rather than each computing their own rule, so "comparable" means the same thing
 * everywhere it is claimed.
 */
class ComparisonSeriesService
{
    public function signatureFor(Assessment $assessment): ?string
    {
        return $assessment->reportSnapshot?->comparison_signature ?? $assessment->snapshot?->comparison_signature;
    }

    public function sameSeries(Assessment $a, Assessment $b): bool
    {
        if ($a->assessment_id === $b->assessment_id) {
            return true;
        }

        $signatureA = $this->signatureFor($a);
        $signatureB = $this->signatureFor($b);

        if ($signatureA && $signatureB) {
            return hash_equals($signatureA, $signatureB);
        }

        if (! $signatureA && ! $signatureB && $a->composition_hash && $b->composition_hash) {
            return hash_equals($a->composition_hash, (string) $b->composition_hash);
        }

        return false;
    }

    /**
     * Every completed assessment for a project, oldest to newest, regardless of series. This is
     * the factual ledger; callers filter it to a compatible series before comparing anything.
     *
     * @return Collection<int, Assessment>
     */
    public function completedHistory(Project $project): Collection
    {
        return Assessment::where('project_id', $project->project_id)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['score.maturityLevel', 'snapshot', 'reportSnapshot'])
            ->orderBy('completed_at')
            ->get();
    }

    /**
     * Completed assessments for a project that share the latest run's comparison signature (or,
     * for pre-signature historical data, its composition hash), ordered oldest to newest.
     *
     * @return Collection<int, Assessment>
     */
    public function seriesFor(Project $project): Collection
    {
        $history = $this->completedHistory($project);
        $latest = $history->last();

        return $latest ? $this->seriesMatching($history, $latest) : collect();
    }

    /**
     * The series matching one specific reference assessment — not necessarily the project's
     * latest. Needed when viewing a report for an older run: its own comparable history is not
     * always the same set as "whatever the project's newest run happens to be."
     *
     * @return Collection<int, Assessment>
     */
    public function seriesMatching(Project|Collection $projectOrHistory, Assessment $reference): Collection
    {
        $history = $projectOrHistory instanceof Project
            ? $this->completedHistory($projectOrHistory)
            : $projectOrHistory;

        return $history->filter(fn (Assessment $assessment) => $this->sameSeries($assessment, $reference))->values();
    }

    /**
     * True when the project has completed assessments that predate or fall outside the current
     * compatible series — real history exists, it simply cannot be compared to the latest run
     * because the underlying methodology changed. Distinct from a true baseline, where no prior
     * assessment exists at all.
     */
    public function hasIncompatiblePriorHistory(Project $project): bool
    {
        $history = $this->completedHistory($project);

        return $history->count() > $this->seriesFor($project)->count();
    }
}
