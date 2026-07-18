<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentSnapshot;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateVersion;
use App\Models\AssessmentTier;
use App\Models\FacilityProfile;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentCreationService
{
    public function __construct(private readonly TemplateContentService $content) {}

    public function createFromCatalogue(
        Project $project,
        AssessmentCatalogueRelease $release,
        array $selectedModuleIds = [],
        array $exclusionReasons = [],
        ?string $creatorId = null,
    ): Assessment {
        $release->load(['facilityProfile', 'departmentFrameworkVersions.module']);
        $target = $project->targets()->first();

        if ($release->status !== AssessmentCatalogueRelease::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['catalogue_release' => 'Only published Vytte catalogue releases can start an assessment.']);
        }

        if (! $target) {
            throw ValidationException::withMessages(['target' => 'This project needs an assessment setting.']);
        }

        if ($release->creation_path === 'COMPREHENSIVE') {
            if (! $release->facilityProfile || $release->facilityProfile->status !== FacilityProfile::STATUS_PUBLISHED) {
                throw ValidationException::withMessages(['facility_profile' => 'This catalogue release has no published facility profile.']);
            }

            $targetSetting = DB::table('target_type_setting_map')
                ->where('target_type_code', $target->target_type_code)
                ->value('setting_type_code');

            if ($targetSetting !== $release->facilityProfile->setting_type_code) {
                throw ValidationException::withMessages(['facility_profile' => 'This facility profile is not designed for the selected setting.']);
            }

            if ($target->facility_profile_id && $target->facility_profile_id !== $release->facility_profile_id) {
                throw ValidationException::withMessages(['facility_profile' => 'This catalogue release does not match the facility profile saved on the project.']);
            }
        }

        $frameworks = $release->departmentFrameworkVersions
            ->sortBy(fn ($version) => (int) $version->pivot->display_order)
            ->values();
        if ($frameworks->isEmpty()) {
            throw ValidationException::withMessages(['catalogue_release' => 'This catalogue release does not contain any published departments.']);
        }

        $availableIds = $frameworks->pluck('module_id')->map(fn ($id) => (int) $id)->values();
        $requiredIds = $frameworks
            ->filter(fn ($version) => $version->pivot->applicability === 'REQUIRED')
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $defaultIds = $frameworks
            ->filter(fn ($version) => in_array($version->pivot->applicability, ['REQUIRED', 'DEFAULT'], true))
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($release->creation_path === 'FOCUSED') {
            $selectedIds = $availableIds;
        } else {
            $selectedIds = collect($selectedModuleIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            if ($selectedIds->isEmpty()) {
                $selectedIds = $defaultIds;
            }
            if ($selectedIds->diff($availableIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['departments' => 'Choose valid departments from the Vytte catalogue release.']);
            }
            if ($requiredIds->diff($selectedIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['departments' => 'Required departments cannot be removed from this facility profile.']);
            }
        }

        $excludedIds = $availableIds->diff($selectedIds)->values();
        foreach ($frameworks->whereIn('module_id', $excludedIds->all()) as $framework) {
            if ($framework->pivot->applicability === 'DEFAULT' && blank($exclusionReasons[$framework->module_id] ?? null)) {
                throw ValidationException::withMessages(['exclusion_reasons' => 'Explain why each default department is excluded.']);
            }
        }

        $payload = [];
        $manifestDepartments = [];
        $questionIds = [];
        foreach ($frameworks->whereIn('module_id', $selectedIds->all()) as $framework) {
            if (! is_array($framework->published_payload)) {
                throw ValidationException::withMessages(['catalogue_release' => 'A referenced department framework has no frozen payload.']);
            }

            $modulePayload = $framework->published_payload;
            $modulePayload['display_order'] = (int) $framework->pivot->display_order;
            $modulePayload['area_label'] = $framework->pivot->area_label ?: $modulePayload['module_name'];
            $modulePayload['framework_version_id'] = $framework->framework_version_id;
            $modulePayload['framework_version_number'] = (int) $framework->version_number;
            $modulePayload['framework_content_hash'] = $framework->content_hash;
            $modulePayload['applicability'] = $framework->pivot->applicability;

            foreach ($modulePayload['questions'] ?? [] as $question) {
                if (isset($questionIds[$question['question_id']])) {
                    throw ValidationException::withMessages(['catalogue_release' => 'Duplicate questions cannot be composed into one assessment.']);
                }
                $questionIds[$question['question_id']] = true;
            }

            $payload[] = $modulePayload;
            $manifestDepartments[] = [
                'module_id' => (int) $framework->module_id,
                'module_code' => $framework->module?->module_code,
                'framework_version_id' => $framework->framework_version_id,
                'framework_version_number' => (int) $framework->version_number,
                'framework_content_hash' => $framework->content_hash,
                'applicability' => $framework->pivot->applicability,
                'display_order' => (int) $framework->pivot->display_order,
            ];
        }

        $excludedManifest = $frameworks->whereIn('module_id', $excludedIds->all())
            ->map(fn ($framework) => [
                'module_id' => (int) $framework->module_id,
                'module_code' => $framework->module?->module_code,
                'framework_version_id' => $framework->framework_version_id,
                'framework_content_hash' => $framework->content_hash,
                'applicability' => $framework->pivot->applicability,
                'exclusion_reason' => trim((string) ($exclusionReasons[$framework->module_id] ?? 'Not selected')),
            ])->values()->all();

        $manifest = [
            'catalogue_release_id' => $release->catalogue_release_id,
            'catalogue_release_code' => $release->release_code,
            'catalogue_content_hash' => $release->content_hash,
            'facility_profile_id' => $release->facility_profile_id,
            'facility_profile_code' => $release->facilityProfile?->profile_code,
            'selected_department_versions' => $manifestDepartments,
            'excluded_department_versions' => $excludedManifest,
        ];
        $hash = $this->content->hash([
            'payload' => $payload,
            'composition_manifest' => $manifest,
            'aggregation_policy' => $release->aggregation_policy,
        ]);
        $tierId = AssessmentTier::where('tier_code', 'TIER_1')->value('assessment_tier_id');

        return DB::transaction(function () use (
            $project, $target, $release, $selectedIds, $excludedIds, $exclusionReasons,
            $payload, $manifest, $hash, $tierId, $creatorId, $frameworks
        ) {
            $assessment = Assessment::create([
                'target_id' => $target->target_id,
                'project_id' => $project->project_id,
                'assessment_tier_id' => $tierId,
                'scope_type' => $release->creation_path === 'FOCUSED' ? 'FOCUSED' : 'FULL_TARGET',
                'creation_path' => $release->creation_path,
                'catalogue_release_id' => $release->catalogue_release_id,
                'composition_hash' => $hash,
                'status' => Assessment::STATUS_IN_PROGRESS,
                'publish_status' => Assessment::PUBLISH_DRAFT,
                'assessor_name' => auth()->user()?->name,
                'started_at' => now(),
            ]);

            foreach ($frameworks->whereIn('module_id', $selectedIds->all()) as $framework) {
                AssessmentModuleScope::create([
                    'assessment_id' => $assessment->assessment_id,
                    'module_id' => $framework->module_id,
                    'in_scope' => true,
                    'is_category_default' => in_array($framework->pivot->applicability, ['REQUIRED', 'DEFAULT'], true),
                    'status' => AssessmentModuleScope::STATUS_PENDING,
                ]);
            }

            foreach ($frameworks->whereIn('module_id', $excludedIds->all()) as $framework) {
                AssessmentModuleScope::create([
                    'assessment_id' => $assessment->assessment_id,
                    'module_id' => $framework->module_id,
                    'in_scope' => false,
                    'is_category_default' => in_array($framework->pivot->applicability, ['REQUIRED', 'DEFAULT'], true),
                    'exclusion_reason' => trim((string) ($exclusionReasons[$framework->module_id] ?? 'Not selected')),
                    'status' => AssessmentModuleScope::STATUS_EXCLUDED,
                ]);
            }

            AssessmentSnapshot::create([
                'assessment_id' => $assessment->assessment_id,
                'catalogue_release_id' => $release->catalogue_release_id,
                'facility_profile_id' => $release->facility_profile_id,
                'creation_path' => $release->creation_path,
                'setting_type_code' => $release->facilityProfile?->setting_type_code,
                'health_domain_id' => $release->health_domain_id,
                'content_hash' => $hash,
                'is_customized' => $excludedIds->isNotEmpty(),
                'composition_manifest' => $manifest,
                'aggregation_policy' => $release->aggregation_policy,
                'payload' => $payload,
                'collection_config' => [
                    'allows_multi_respondent' => false,
                    'scoring_profile_version' => ScoringService::ALGORITHM_VERSION,
                ],
                'created_by' => $creatorId,
                'created_at' => now(),
            ]);

            app(AuditService::class)->record(
                'assessment.created',
                $assessment,
                newValues: [
                    'creation_path' => $assessment->creation_path,
                    'catalogue_release_id' => $assessment->catalogue_release_id,
                    'composition_hash' => $assessment->composition_hash,
                ],
                userId: $creatorId,
            );

            return $assessment->fresh(['snapshot', 'moduleScope']);
        });
    }

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

        if ($version->status !== AssessmentTemplateVersion::STATUS_PUBLISHED || $template->status !== AssessmentTemplate::STATUS_PUBLISHED) {
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
                'status' => Assessment::STATUS_IN_PROGRESS,
                'publish_status' => Assessment::PUBLISH_DRAFT,
                'assessor_name' => auth()->user()?->name,
                'started_at' => now(),
            ]);

            foreach ($selectedIds as $moduleId) {
                AssessmentModuleScope::create([
                    'assessment_id' => $assessment->assessment_id,
                    'module_id' => $moduleId,
                    'in_scope' => true,
                    'is_category_default' => true,
                    'status' => AssessmentModuleScope::STATUS_PENDING,
                ]);
            }

            foreach ($excludedIds as $moduleId) {
                AssessmentModuleScope::create([
                    'assessment_id' => $assessment->assessment_id,
                    'module_id' => $moduleId,
                    'in_scope' => false,
                    'is_category_default' => true,
                    'exclusion_reason' => trim($exclusionReasons[$moduleId]),
                    'status' => AssessmentModuleScope::STATUS_EXCLUDED,
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
                'collection_config' => [
                    'allows_multi_respondent' => (bool) $version->allows_multi_respondent,
                    'scoring_profile_version' => $version->scoring_version,
                    'minimum_completed_respondents' => $version->allows_multi_respondent
                        ? (int) $version->minimum_completed_respondents
                        : null,
                    'aggregation_method' => $version->allows_multi_respondent
                        ? $version->aggregation_method
                        : null,
                    'respondent_eligibility_rules' => $version->respondent_eligibility_rules ?? [],
                ],
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
