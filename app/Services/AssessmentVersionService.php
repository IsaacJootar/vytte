<?php

namespace App\Services;

use App\Models\AssessmentCatalogueRelease;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating a new version of a published assessment.
 *
 * Published content is immutable, so a correction is always a new version. The predecessor
 * is left untouched: it stays published and in service, keeps its frozen payload and hash,
 * and continues to back every assessment and report already created from it.
 *
 * The builder deliberately does not retire the predecessor when the successor draft is
 * created. Retiring it at that moment would pull a working assessment out of service the
 * instant an author started a correction, leaving nothing publishable until the new version
 * was finished. Retirement happens when the successor is published instead. Advanced Tools
 * keeps its own immediate-supersede behaviour for expert use. See DEC-2026-07-19-015.
 */
class AssessmentVersionService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Copies a framework version's structure into a new draft linked by lineage.
     *
     * Question versions are referenced, never copied: the successor points at the same
     * immutable published versions until an author changes something.
     */
    public function cloneToDraft(DepartmentFrameworkVersion $framework, ?string $reviewNote = null): DepartmentFrameworkVersion
    {
        $framework->load(['sections.indicators', 'questionPlacements']);

        return DB::transaction(function () use ($framework, $reviewNote): DepartmentFrameworkVersion {
            $nextVersion = ((int) DepartmentFrameworkVersion::where('module_id', $framework->module_id)->max('version_number')) + 1;

            $successor = DepartmentFrameworkVersion::create([
                'module_id' => $framework->module_id,
                'framework_type' => $framework->framework_type,
                'version_number' => $nextVersion,
                'status' => DepartmentFrameworkVersion::STATUS_DRAFT,
                'display_name' => $framework->display_name,
                'description' => $framework->description,
                'purpose' => $framework->purpose,
                'source_authority' => $framework->source_authority,
                'source_url' => $framework->source_url,
                'license_code' => $framework->license_code,
                'methodology_notes' => $framework->methodology_notes,
                'source_summary' => $framework->source_summary,
                'review_notes' => $reviewNote ?: 'Successor draft created from v'.$framework->version_number.'.',
                'parent_version_id' => $framework->framework_version_id,
            ]);

            $sectionMap = [];
            foreach ($framework->sections as $section) {
                $sectionMap[$section->framework_section_id] = FrameworkSection::create([
                    'framework_version_id' => $successor->framework_version_id,
                    'section_code' => $section->section_code,
                    'section_name' => $section->section_name,
                    'purpose' => $section->purpose,
                    'display_order' => $section->display_order,
                ])->framework_section_id;
            }

            $indicatorMap = [];
            foreach ($framework->sections->flatMap->indicators as $indicator) {
                $indicatorMap[$indicator->framework_indicator_id] = FrameworkIndicator::create([
                    'framework_version_id' => $successor->framework_version_id,
                    'framework_section_id' => $sectionMap[$indicator->framework_section_id],
                    'indicator_code' => $indicator->indicator_code,
                    'indicator_name' => $indicator->indicator_name,
                    'description' => $indicator->description,
                    'display_order' => $indicator->display_order,
                ])->framework_indicator_id;
            }

            foreach ($framework->questionPlacements as $placement) {
                FrameworkQuestionPlacement::create([
                    'framework_version_id' => $successor->framework_version_id,
                    'framework_section_id' => $sectionMap[$placement->framework_section_id],
                    'framework_indicator_id' => $indicatorMap[$placement->framework_indicator_id],
                    'question_id' => $placement->question_id,
                    'question_version_id' => $placement->question_version_id,
                    'sub_index_id' => $placement->sub_index_id,
                    'display_order' => $placement->display_order,
                    'is_required' => $placement->is_required,
                    'applicability' => $placement->applicability,
                    'evidence_expectation' => $placement->evidence_expectation,
                    'weight' => $placement->weight,
                    'scoring_contribution' => $placement->scoring_contribution,
                    'criticality' => $placement->criticality,
                    'help_text' => $placement->help_text,
                    'local_display_text' => $placement->local_display_text,
                    'metadata' => $placement->metadata,
                ]);
            }

            return $successor;
        });
    }

    /**
     * Starts a new version from the builder. The predecessor stays published and usable.
     */
    public function startNewVersion(DepartmentFrameworkVersion $assessment, ?string $userId): DepartmentFrameworkVersion
    {
        if ($assessment->status !== DepartmentFrameworkVersion::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'version' => 'Only a published assessment can be given a new version.',
            ]);
        }

        if ($this->openDraftFor($assessment)) {
            throw ValidationException::withMessages([
                'version' => 'A newer draft of this assessment already exists. Finish or remove it before starting another.',
            ]);
        }

        $successor = $this->cloneToDraft($assessment);

        $this->audit->record('assessment.version.started', $successor, newValues: [
            'previous_framework_version_id' => $assessment->framework_version_id,
            'previous_version_number' => $assessment->version_number,
            'new_version_number' => $successor->version_number,
        ], userId: $userId);

        return $successor;
    }

    /**
     * Retires the predecessor once its successor is published, so workspaces select the
     * new version. Assessments and reports already created keep working: their content
     * lives in frozen snapshots, not in these records.
     */
    public function retirePredecessorOf(DepartmentFrameworkVersion $published, ?string $userId): void
    {
        $predecessor = $published->parent_version_id
            ? DepartmentFrameworkVersion::find($published->parent_version_id)
            : null;

        if (! $predecessor || $predecessor->status !== DepartmentFrameworkVersion::STATUS_PUBLISHED) {
            return;
        }

        $predecessor->update(['status' => DepartmentFrameworkVersion::STATUS_SUPERSEDED]);

        $this->audit->record('assessment.version.superseded', $predecessor->fresh(), [
            'status' => DepartmentFrameworkVersion::STATUS_PUBLISHED,
        ], [
            'status' => DepartmentFrameworkVersion::STATUS_SUPERSEDED,
            'successor_framework_version_id' => $published->framework_version_id,
        ], userId: $userId);

        // The release pinning the old version must retire too, or workspaces could still
        // start new assessments from superseded content.
        AssessmentCatalogueRelease::whereHas(
            'departmentFrameworkVersions',
            fn ($query) => $query->where('department_framework_versions.framework_version_id', $predecessor->framework_version_id)
        )->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->get()
            ->each(function (AssessmentCatalogueRelease $release) use ($userId): void {
                $release->update(['status' => AssessmentCatalogueRelease::STATUS_SUPERSEDED]);
                $this->audit->record('assessment.release.superseded', $release->fresh(), [
                    'status' => AssessmentCatalogueRelease::STATUS_PUBLISHED,
                ], [
                    'status' => AssessmentCatalogueRelease::STATUS_SUPERSEDED,
                ], userId: $userId);
            });
    }

    public function openDraftFor(DepartmentFrameworkVersion $assessment): ?DepartmentFrameworkVersion
    {
        return DepartmentFrameworkVersion::where('parent_version_id', $assessment->framework_version_id)
            ->where('status', DepartmentFrameworkVersion::STATUS_DRAFT)
            ->first();
    }
}
