<?php

namespace App\Services;

use App\Models\MethodologyVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a methodology version and freezes it.
 *
 * Publication computes a content hash over everything in the version, so a report that
 * cites a methodology version can be traced back to the exact objectives, lenses and
 * categories in force when it was produced. That is the same reproducibility contract
 * the question and framework layers already hold themselves to.
 *
 * Refuses to publish a methodology that would be internally broken — an objective
 * recommending a lens that does not exist would present the reader with an empty
 * recommendation and no explanation.
 */
class MethodologyPublishingService
{
    /**
     * The version in force: the newest published one, or the working draft if none is
     * published yet. Beta runs on the draft, which is why this falls back rather than
     * returning null and leaving every methodology screen empty.
     */
    public static function currentVersion(): ?MethodologyVersion
    {
        return MethodologyVersion::where('status', MethodologyVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->first()
            ?? MethodologyVersion::orderByDesc('version_number')->first();
    }

    public function publish(MethodologyVersion $version, ?string $publishedBy = null): MethodologyVersion
    {
        return DB::transaction(function () use ($version, $publishedBy): MethodologyVersion {
            $version = MethodologyVersion::whereKey($version->methodology_version_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($version->status !== MethodologyVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'publish' => 'Only a draft methodology version can be published.',
                ]);
            }

            $this->assertInternallyConsistent($version);

            $previous = MethodologyVersion::where('status', MethodologyVersion::STATUS_PUBLISHED)
                ->where('version_number', '<', $version->version_number)
                ->get();

            foreach ($previous as $old) {
                $old->update(['status' => MethodologyVersion::STATUS_SUPERSEDED]);
            }

            $version->update([
                'status' => MethodologyVersion::STATUS_PUBLISHED,
                'content_hash' => $this->contentHash($version),
                'published_at' => now(),
                'published_by' => $publishedBy,
            ]);

            return $version->fresh();
        });
    }

    /**
     * Every recommendation must point at something that exists in this same version.
     *
     * @throws ValidationException
     */
    private function assertInternallyConsistent(MethodologyVersion $version): void
    {
        $lensCodes = $version->analysisLenses()->pluck('lens_code')->all();
        $templateCodes = $version->templates()->pluck('template_code')->all();
        $areaCodes = $version->healthAreas()->pluck('area_code')->all();

        $known = [
            'ANALYSIS_LENS' => $lensCodes,
            'TEMPLATE' => $templateCodes,
            'HEALTH_AREA' => $areaCodes,
        ];

        $broken = [];

        $recommendations = DB::table('objective_recommendations')
            ->join('assessment_objectives', 'assessment_objectives.assessment_objective_id', '=', 'objective_recommendations.assessment_objective_id')
            ->where('assessment_objectives.methodology_version_id', $version->methodology_version_id)
            ->select('assessment_objectives.objective_code', 'objective_recommendations.recommends_type', 'objective_recommendations.recommends_ref')
            ->get();

        foreach ($recommendations as $recommendation) {
            $valid = $known[$recommendation->recommends_type] ?? null;

            // HEALTH_DOMAIN, MEASUREMENT_DOMAIN and EVIDENCE_TYPE reference entities that
            // live outside this version and have their own lifecycle, so they are not
            // checked here.
            if ($valid === null) {
                continue;
            }

            if (! in_array($recommendation->recommends_ref, $valid, true)) {
                $broken[] = $recommendation->objective_code.' recommends '
                    .strtolower(str_replace('_', ' ', $recommendation->recommends_type))
                    .' "'.$recommendation->recommends_ref.'", which is not in this methodology version.';
            }
        }

        if ($broken !== []) {
            throw ValidationException::withMessages(['publish' => $broken]);
        }
    }

    /**
     * A stable fingerprint of everything the version contains.
     */
    private function contentHash(MethodologyVersion $version): string
    {
        $payload = [
            'objectives' => $version->objectives()->orderBy('objective_code')
                ->get(['objective_code', 'objective_name', 'objective_group', 'description'])->toArray(),
            'health_areas' => $version->healthAreas()->orderBy('area_code')
                ->get(['area_code', 'area_name', 'health_domain_id'])->toArray(),
            'analysis_lenses' => $version->analysisLenses()->orderBy('lens_code')
                ->get(['lens_code', 'lens_name', 'question_it_answers'])->toArray(),
            'insight_categories' => $version->insightCategories()->orderBy('category_code')
                ->get(['category_code', 'category_name', 'polarity', 'is_diagnostic'])->toArray(),
            'templates' => $version->templates()->orderBy('template_code')
                ->get(['template_code', 'template_name', 'scope_type'])->toArray(),
            'presets' => $version->presets()->orderBy('preset_code')
                ->get(['preset_code', 'preset_name', 'template_code'])->toArray(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
