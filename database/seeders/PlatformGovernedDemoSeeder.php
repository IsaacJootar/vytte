<?php

namespace Database\Seeders;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FacilityProfile;
use App\Models\HealthDomain;
use App\Services\CataloguePublishingService;
use App\Services\DepartmentFrameworkPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformGovernedDemoSeeder extends Seeder
{
    public function run(): void
    {
        $modules = $this->seedDemoDepartments();
        $frameworks = $this->seedFrameworkVersions($modules);
        $profiles = $this->seedFacilityProfiles($modules);
        $this->seedCatalogueReleases($frameworks, $profiles);
    }

    private function seedDemoDepartments(): array
    {
        $definitions = [
            'DOPD' => ['Outpatient', 'Patient flow and routine outpatient service readiness.', 'CQ'],
            'DPHM' => ['Pharmacy', 'Medicine availability and pharmacy service readiness.', 'CQ'],
            'DLAB' => ['Laboratory', 'Essential laboratory readiness and safety.', 'CQ'],
            'DMNH' => ['Mental Health', 'Basic mental health service readiness.', 'CQ'],
        ];

        $modules = [];
        foreach ($definitions as $code => [$name, $description, $domainCode]) {
            $module = AssessmentModule::firstOrCreate(
                ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => $code],
                [
                    'module_name' => $name,
                    'primary_respondent' => 'Facility staff',
                    'estimated_duration_minutes' => 8,
                    'data_collection_methods' => 'Interview and observation',
                    'is_active' => true,
                ]
            );
            $modules[$code] = $module;
            $this->seedDemoQuestions($module, $description, $domainCode);
        }

        return $modules;
    }

    private function seedDemoQuestions(AssessmentModule $module, string $description, string $domainCode): void
    {
        $singleSelectTypeId = DB::table('question_types')->where('type_code', 'SINGLE_SELECT')->value('type_id');
        $openEndedTypeId = DB::table('question_types')->where('type_code', 'OPEN_ENDED')->value('type_id');
        $domainId = DB::table('domains')->where('domain_code', $domainCode)->value('domain_id');

        $moduleDomainId = DB::table('module_domains')
            ->where('module_id', $module->module_id)
            ->where('domain_number', 1)
            ->value('module_domain_id');
        if (! $moduleDomainId) {
            $moduleDomainId = DB::table('module_domains')->insertGetId([
                'module_id' => $module->module_id,
                'domain_number' => 1,
                'domain_label' => 'DEMONSTRATION READINESS',
            ], 'module_domain_id');
        }

        $subIndexId = DB::table('sub_indices')
            ->where('module_id', $module->module_id)
            ->where('acronym', $module->module_code.'R')
            ->value('sub_index_id');
        if (! $subIndexId) {
            $subIndexId = DB::table('sub_indices')->insertGetId([
                'module_id' => $module->module_id,
                'domain_id' => $domainId,
                'acronym' => $module->module_code.'R',
                'full_name' => $module->module_name.' Readiness Score',
                'description' => $description,
            ], 'sub_index_id');
        }

        $questions = [
            [
                'code' => "{$module->module_code}.DEMO.Q1",
                'text' => "Does {$module->module_name} have a documented service process available today?",
                'type_id' => $singleSelectTypeId,
                'is_scored' => true,
                'order' => 1,
                'options' => [
                    ['Yes, current and used', 100, false],
                    ['Partially available', 50, false],
                    ['No documented process', 0, $module->module_code === 'DOPD'],
                ],
            ],
            [
                'code' => "{$module->module_code}.DEMO.Q2",
                'text' => "Are minimum supplies for {$module->module_name} available at the time of assessment?",
                'type_id' => $singleSelectTypeId,
                'is_scored' => true,
                'order' => 2,
                'options' => [
                    ['Available and adequate', 100, false],
                    ['Available but inadequate', 50, false],
                    ['Not available', 0, false],
                ],
            ],
            [
                'code' => "{$module->module_code}.DEMO.Q3",
                'text' => "Add any local context that would help interpret {$module->module_name} readiness.",
                'type_id' => $openEndedTypeId,
                'is_scored' => false,
                'order' => 3,
                'options' => [],
            ],
        ];

        foreach ($questions as $index => $definition) {
            $questionId = DB::table('questions')->where('question_code', $definition['code'])->value('question_id');
            if (! $questionId) {
                $questionId = (string) Str::uuid();
                DB::table('questions')->insert([
                    'question_id' => $questionId,
                    'module_id' => $module->module_id,
                    'module_domain_id' => $moduleDomainId,
                    'question_number' => $index + 1,
                    'question_code' => $definition['code'],
                    'question_text' => $definition['text'],
                    'type_id' => $definition['type_id'],
                    'requires_observation' => false,
                    'display_order' => $definition['order'],
                    'is_active' => true,
                    'is_scored' => $definition['is_scored'],
                    'source' => 'DEMO_CURATED',
                    'question_status' => 'APPROVED',
                    'standard_alignment_status' => 'DEMO_CONTENT',
                ]);
            }

            foreach ($definition['options'] as $optionOrder => [$label, $weight, $criticalFailure]) {
                $exists = DB::table('question_options')
                    ->where('question_id', $questionId)
                    ->where('option_order', $optionOrder + 1)
                    ->exists();
                if (! $exists) {
                    DB::table('question_options')->insert([
                        'question_id' => $questionId,
                        'option_label' => $label,
                        'option_order' => $optionOrder + 1,
                        'score_weight' => $weight,
                        'is_flagged_pain_point' => $criticalFailure,
                    ]);
                }
            }

            if ($definition['is_scored']) {
                DB::table('sub_index_questions')->insertOrIgnore([
                    'sub_index_id' => $subIndexId,
                    'question_id' => $questionId,
                    'weight' => 1,
                ]);
            }
        }
    }

    private function seedFrameworkVersions(array $modules): array
    {
        $frameworks = [];
        foreach ($modules as $code => $module) {
            $version = DepartmentFrameworkVersion::firstOrCreate(
                ['module_id' => $module->module_id, 'version_number' => 1],
                [
                    'display_name' => $module->module_name.' Demonstration Framework v1',
                    'description' => 'Demonstration-only official Vytte framework used to exercise the governed composition architecture.',
                    'source_authority' => 'Vytte demonstration content',
                    'license_code' => 'DEMO-NOT-FOR-PRODUCTION',
                    'provenance' => ['content_kind' => 'demonstration'],
                    'critical_failure_rules' => ['uses_flagged_options' => true],
                ]
            );

            if ($version->status !== DepartmentFrameworkVersion::STATUS_PUBLISHED) {
                $version = app(DepartmentFrameworkPublishingService::class)->publish($version);
            }
            $frameworks[$code] = $version;
        }

        return $frameworks;
    }

    private function seedFacilityProfiles(array $modules): array
    {
        $profiles = [];
        $profileDefinitions = [
            'CLINIC' => ['Clinic', 'Small outpatient-focused health facility.', ['DOPD' => 'REQUIRED', 'DPHM' => 'DEFAULT', 'DLAB' => 'DEFAULT', 'DMNH' => 'OPTIONAL']],
            'PRIMARY_HEALTH_CENTRE' => ['Primary Health Centre', 'Primary-care facility profile for demonstration use.', ['DOPD' => 'REQUIRED', 'DPHM' => 'DEFAULT', 'DLAB' => 'OPTIONAL', 'DMNH' => 'OPTIONAL']],
            'GENERAL_HOSPITAL' => ['General Hospital', 'General hospital profile for demonstration use.', ['DOPD' => 'REQUIRED', 'DPHM' => 'DEFAULT', 'DLAB' => 'DEFAULT', 'DMNH' => 'DEFAULT']],
        ];

        foreach ($profileDefinitions as $order => $definition) {
            [$code, [$name, $description, $departmentMap]] = [$order, $definition];
            $profile = FacilityProfile::firstOrCreate(
                ['profile_code' => $code],
                [
                    'profile_name' => $name,
                    'setting_type_code' => 'HEALTH_FACILITY',
                    'description' => $description,
                    'status' => FacilityProfile::STATUS_PUBLISHED,
                    'display_order' => count($profiles) + 1,
                ]
            );

            $sync = [];
            $displayOrder = 1;
            foreach ($departmentMap as $moduleCode => $applicability) {
                $sync[$modules[$moduleCode]->module_id] = [
                    'applicability' => $applicability,
                    'display_order' => $displayOrder++,
                    'removal_allowed' => $applicability !== 'REQUIRED',
                ];
            }
            $profile->departments()->syncWithoutDetaching($sync);
            $profiles[$code] = $profile->fresh('departments');
        }

        return $profiles;
    }

    private function seedCatalogueReleases(array $frameworks, array $profiles): void
    {
        $clinicRelease = AssessmentCatalogueRelease::firstOrCreate(
            ['release_code' => 'DEMO_CLINIC_COMPREHENSIVE_V1'],
            [
                'release_name' => 'Demo Clinic Comprehensive Health Assessment',
                'description' => 'Demonstration catalogue release composed from pinned department framework versions.',
                'creation_path' => 'COMPREHENSIVE',
                'facility_profile_id' => $profiles['CLINIC']->facility_profile_id,
                'aggregation_policy' => [
                    'method' => 'MEAN_OF_SCORED_SUB_INDICES',
                    'critical_failures' => [
                        'enabled' => true,
                        'option_score_at_or_below' => 0,
                        'overall_score' => 'ZERO',
                    ],
                ],
                'composition_rules' => ['latest_resolution' => 'forbidden'],
            ]
        );
        $this->attachFrameworks($clinicRelease, [
            ['DOPD', 'REQUIRED', 'Outpatient', 1],
            ['DPHM', 'DEFAULT', 'Pharmacy', 2],
            ['DLAB', 'DEFAULT', 'Laboratory', 3],
            ['DMNH', 'OPTIONAL', 'Mental Health', 4],
        ], $frameworks);
        if ($clinicRelease->status !== AssessmentCatalogueRelease::STATUS_PUBLISHED) {
            app(CataloguePublishingService::class)->publish($clinicRelease);
        }

        $mentalHealthId = HealthDomain::where('domain_code', 'MENTAL_HEALTH')->value('health_domain_id');
        $focusedRelease = AssessmentCatalogueRelease::firstOrCreate(
            ['release_code' => 'DEMO_MENTAL_HEALTH_FOCUSED_V1'],
            [
                'release_name' => 'Demo Focused Mental Health Assessment',
                'description' => 'Focused demonstration assessment that opens only the Mental Health framework.',
                'creation_path' => 'FOCUSED',
                'health_domain_id' => $mentalHealthId,
                'aggregation_policy' => [
                    'method' => 'MEAN_OF_SCORED_SUB_INDICES',
                    'critical_failures' => ['enabled' => false],
                ],
                'composition_rules' => ['latest_resolution' => 'forbidden'],
            ]
        );
        $this->attachFrameworks($focusedRelease, [
            ['DMNH', 'REQUIRED', 'Mental Health', 1],
        ], $frameworks);
        if ($focusedRelease->status !== AssessmentCatalogueRelease::STATUS_PUBLISHED) {
            app(CataloguePublishingService::class)->publish($focusedRelease);
        }
    }

    private function attachFrameworks(AssessmentCatalogueRelease $release, array $rows, array $frameworks): void
    {
        $sync = [];
        foreach ($rows as [$moduleCode, $applicability, $areaLabel, $displayOrder]) {
            $framework = $frameworks[$moduleCode];
            $sync[$framework->framework_version_id] = [
                'module_id' => $framework->module_id,
                'applicability' => $applicability,
                'display_order' => $displayOrder,
                'area_label' => $areaLabel,
            ];
        }

        $release->departmentFrameworkVersions()->syncWithoutDetaching($sync);
    }
}
