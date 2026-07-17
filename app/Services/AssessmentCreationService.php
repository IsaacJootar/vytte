<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentSnapshot;
use App\Models\AssessmentTemplateVersion;
use App\Models\AssessmentTier;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentCreationService
{
    public function __construct(private readonly TemplateContentService $content) {}

    public function create(
        Project $project,
        AssessmentTemplateVersion $version,
        array $selectedModuleIds = [],
        array $exclusionReasons = [],
        ?string $creatorId = null,
    ): Assessment {
        $version->load('template');
        $template = $version->template;
        $target = $project->targets()->first();

        if ($version->status !== 'PUBLISHED' || $template->status !== 'PUBLISHED') {
            throw ValidationException::withMessages(['template' => 'Only published template versions can start an assessment.']);
        }

        if (! $target) {
            throw ValidationException::withMessages(['target' => 'This project needs an assessment setting.']);
        }

        if ($template->creation_path === 'COMPREHENSIVE') {
            $targetSetting = DB::table('target_type_setting_map')
                ->where('target_type_code', $target->target_type_code)
                ->value('setting_type_code');

            if ($targetSetting !== $template->setting_type_code) {
                throw ValidationException::withMessages(['template' => 'This comprehensive framework is not designed for the selected setting.']);
            }
        }

        if (! is_array($version->published_payload)) {
            throw ValidationException::withMessages([
                'template' => 'This legacy template version has no immutable published content. Publish a new version before using it.',
            ]);
        }

        $availableIds = collect($version->published_payload)
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id);

        if ($template->creation_path === 'FOCUSED') {
            $selectedIds = $availableIds;
        } else {
            $selectedIds = collect($selectedModuleIds)->map(fn ($id) => (int) $id)->unique();
            if ($selectedIds->isEmpty() || $selectedIds->diff($availableIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['modules' => 'Choose at least one valid assessment area.']);
            }
        }

        $excludedIds = $availableIds->diff($selectedIds);
        foreach ($excludedIds as $moduleId) {
            if (blank($exclusionReasons[$moduleId] ?? null)) {
                throw ValidationException::withMessages(['exclusion_reasons' => 'Explain why each standard assessment area is excluded.']);
            }
        }

        $payload = collect($version->published_payload)
            ->whereIn('module_id', $selectedIds->all())
            ->sortBy('display_order')
            ->values()
            ->all();
        $hash = $this->content->hash($payload);
        $tierId = AssessmentTier::where('tier_code', 'TIER_1')->value('assessment_tier_id');

        return DB::transaction(function () use (
            $project, $target, $template, $version, $selectedIds, $excludedIds,
            $exclusionReasons, $payload, $hash, $tierId, $creatorId
        ) {
            $assessment = Assessment::create([
                'target_id' => $target->target_id,
                'project_id' => $project->project_id,
                'assessment_tier_id' => $tierId,
                'scope_type' => $template->creation_path === 'FOCUSED' ? 'FOCUSED' : 'FULL_TARGET',
                'creation_path' => $template->creation_path,
                'template_version_id' => $version->template_version_id,
                'composition_hash' => $hash,
                'status' => 'IN_PROGRESS',
                'publish_status' => 'DRAFT',
                'assessor_name' => auth()->user()?->name,
                'started_at' => now(),
            ]);

            foreach ($selectedIds as $moduleId) {
                AssessmentModuleScope::create([
                    'assessment_id' => $assessment->assessment_id,
                    'module_id' => $moduleId,
                    'in_scope' => true,
                    'is_category_default' => true,
                    'status' => 'PENDING',
                ]);
            }

            foreach ($excludedIds as $moduleId) {
                AssessmentModuleScope::create([
                    'assessment_id' => $assessment->assessment_id,
                    'module_id' => $moduleId,
                    'in_scope' => false,
                    'is_category_default' => true,
                    'exclusion_reason' => trim($exclusionReasons[$moduleId]),
                    'status' => 'EXCLUDED',
                ]);
            }

            AssessmentSnapshot::create([
                'assessment_id' => $assessment->assessment_id,
                'template_version_id' => $version->template_version_id,
                'creation_path' => $template->creation_path,
                'setting_type_code' => $template->setting_type_code,
                'health_domain_id' => $template->health_domain_id,
                'content_hash' => $hash,
                'is_customized' => $excludedIds->isNotEmpty(),
                'payload' => $payload,
                'created_by' => $creatorId,
                'created_at' => now(),
            ]);

            app(AuditService::class)->record(
                'assessment.created',
                $assessment,
                newValues: [
                    'creation_path' => $assessment->creation_path,
                    'template_version_id' => $assessment->template_version_id,
                    'composition_hash' => $assessment->composition_hash,
                ],
                userId: $creatorId,
            );

            return $assessment->fresh(['snapshot', 'moduleScope']);
        });
    }
}
