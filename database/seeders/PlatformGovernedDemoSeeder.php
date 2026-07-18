<?php

namespace Database\Seeders;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\DomainDefinition;
use App\Models\DomainTaxonomy;
use App\Models\DomainTaxonomyVersion;
use App\Models\FacilityProfile;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkIndicatorDomainMapping;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\HealthDomain;
use App\Models\Question;
use App\Models\QuestionNumericBand;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Services\CataloguePublishingService;
use App\Services\DepartmentFrameworkPublishingService;
use App\Services\DomainTaxonomyPublishingService;
use App\Services\QuestionVersionPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformGovernedDemoSeeder extends Seeder
{
    public function run(): void
    {
        $modules = $this->seedDemoDepartments();
        $domainDefinitions = $this->seedDomainTaxonomy();
        $questionVersions = $this->seedQuestionBank($modules);
        $frameworks = $this->seedFrameworkVersions($modules, $questionVersions, $domainDefinitions);
        $profiles = $this->seedFacilityProfiles($modules);
        $this->seedCatalogueReleases($frameworks, $profiles);
    }

    private function seedDemoDepartments(): array
    {
        $definitions = [
            'DOPD' => ['Outpatient', 'Patient flow and routine outpatient service readiness.', 'SERV', false],
            'DPHM' => ['Pharmacy', 'Medicine availability and pharmacy service readiness.', 'SERV', false],
            'DLAB' => ['Laboratory', 'Essential laboratory readiness and safety.', 'SERV', false],
            'DMNH' => ['Mental Health', 'Basic mental health service readiness.', 'SERV', true],
        ];

        $modules = [];
        foreach ($definitions as $code => [$name, $description, $domainCode, $requiresConsent]) {
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
            $module->update(['requires_consent' => $requiresConsent]);
            $modules[$code] = $module;
            $this->ensureDemoScoringProfile($module, $description, $domainCode);
        }

        return $modules;
    }

    private function seedDomainTaxonomy(): array
    {
        $taxonomy = DomainTaxonomy::firstOrCreate(
            ['taxonomy_code' => 'VYTTE_HEALTH_ANALYTICAL_DOMAINS'],
            [
                'taxonomy_name' => 'Vytte Health Analytical Domains',
                'description' => 'Small governed universal analytical lens for official Vytte health assessment interpretation.',
                'status' => 'ACTIVE',
            ]
        );

        $version = DomainTaxonomyVersion::firstOrCreate(
            ['domain_taxonomy_id' => $taxonomy->domain_taxonomy_id, 'version_number' => 1],
            [
                'status' => DomainTaxonomyVersion::STATUS_DRAFT,
                'methodology_notes' => 'Demonstration-only taxonomy proving cross-cutting report analysis; domains do not generate questions.',
                'rejected_candidates' => [
                    'Equity and Inclusion' => 'Merged into governance, service access, and person-centredness until a tested equity methodology is approved.',
                    'Continuity, Referral and Coordination' => 'Merged into service delivery and access for the first taxonomy.',
                    'Resilience and Preparedness' => 'Merged into governance and resources until emergency-preparedness methodology requires a separate lens.',
                ],
            ]
        );

        $definitions = [
            'GOV' => [
                'Governance and Accountability',
                'Leadership, policies, oversight, accountability, and management processes that enable reliable service delivery.',
                'Required because governance weaknesses recur across departments without being owned by one department.',
            ],
            'WORK' => [
                'Workforce and Capability',
                'Staff availability, skills, role clarity, supervision, and capacity to perform the assessed service.',
                'Required because workforce constraints cut across clinical, community, and programme-focused assessments.',
            ],
            'SERV' => [
                'Service Delivery and Access',
                'Availability, reach, flow, continuity, and accessibility of the assessed health service.',
                'Required because Vytte needs a stable lens for whether services are actually available and usable.',
            ],
            'SAFE' => [
                'Safety and Quality',
                'Practices that protect clients, staff, and communities while improving reliability and quality of care.',
                'Required because safety and quality findings need cross-department visibility.',
            ],
            'RES' => [
                'Infrastructure, Equipment and Supplies',
                'Physical infrastructure, equipment, commodities, medicines, supplies, and utilities needed to deliver services.',
                'Required because resource readiness is a common bottleneck across departments and focused programmes.',
            ],
            'INFO' => [
                'Information, Measurement and Learning',
                'Records, indicators, measurement, reporting, feedback loops, and learning practices used for improvement.',
                'Required because Vytte reports must explain measurement and improvement capability without reviving old data-burden domains.',
            ],
            'PCOM' => [
                'Person-Centredness and Community Responsiveness',
                'Respectful, understandable, responsive, and community-aware service experience.',
                'Required because community and patient feedback should be interpreted through the normal assessment engine.',
            ],
        ];

        $mapped = [];
        foreach ($definitions as $order => $row) {
            $code = is_int($order) ? array_keys($definitions)[$order] : $order;
            [$name, $definition, $rationale] = $row;
            $domain = DB::table('domains')->where('domain_code', $code)->first();
            $mapped[$code] = DomainDefinition::firstOrCreate(
                ['domain_taxonomy_version_id' => $version->domain_taxonomy_version_id, 'domain_code' => $code],
                [
                    'domain_id' => $domain->domain_id,
                    'domain_name' => $name,
                    'definition' => $definition,
                    'rationale' => $rationale,
                    'display_order' => count($mapped) + 1,
                ]
            );
        }

        if ($version->status !== DomainTaxonomyVersion::STATUS_PUBLISHED) {
            app(DomainTaxonomyPublishingService::class)->publish($version);
        }

        return $mapped;
    }

    private function ensureDemoScoringProfile(AssessmentModule $module, string $description, string $domainCode): void
    {
        $domainId = DB::table('domains')->where('domain_code', $domainCode)->value('domain_id');

        DB::table('question_groups')->updateOrInsert(
            ['module_id' => $module->module_id, 'group_number' => 1],
            ['group_label' => 'DEMONSTRATION READINESS']
        );

        if (! DB::table('sub_indices')->where('module_id', $module->module_id)->where('acronym', $module->module_code.'R')->exists()) {
            DB::table('sub_indices')->insert([
                'module_id' => $module->module_id,
                'domain_id' => $domainId,
                'acronym' => $module->module_code.'R',
                'full_name' => $module->module_name.' Readiness Score',
                'description' => $description,
            ]);
        }
    }

    private function seedQuestionBank(array $modules): array
    {
        $singleSelectTypeId = DB::table('question_types')->where('type_code', 'SINGLE_SELECT')->value('type_id');
        $openEndedTypeId = DB::table('question_types')->where('type_code', 'OPEN_ENDED')->value('type_id');
        $numericTypeId = DB::table('question_types')->where('type_code', 'NUMERIC')->value('type_id');

        $versions = [];
        foreach ($modules as $code => $module) {
            $definitions = $this->questionDefinitionsFor($module, $singleSelectTypeId, $openEndedTypeId, $numericTypeId);
            foreach ($definitions as $definition) {
                $question = $this->upsertQuestionIdentity($module, $definition);
                $this->mirrorRuntimeQuestionShape($question, $definition);
                $versions[$definition['key']] = $this->publishQuestionVersion($question, 1, $definition);
            }
        }

        $shared = $versions['DOPD_PROCESS'];
        $futureVersion = QuestionVersion::firstOrCreate(
            ['question_id' => $shared->question_id, 'version_number' => 2],
            [
                'question_id' => $shared->question_id,
                'version_number' => 2,
                'status' => QuestionVersion::STATUS_APPROVED,
                'question_text' => 'Does the service routinely use its documented process during day-to-day operations?',
                'type_id' => $singleSelectTypeId,
                'options' => $shared->options,
                'numeric_config' => null,
                'numeric_bands' => [],
                'requires_observation' => false,
                'methodology_notes' => 'Demonstration-only future wording. Existing published frameworks intentionally remain pinned to version 1.',
                'source_summary' => 'Vytte demonstration content; not validated production methodology.',
                'approved_by' => null,
                'effective_date' => now()->toDateString(),
            ]
        );
        if ($futureVersion->status !== QuestionVersion::STATUS_PUBLISHED) {
            app(QuestionVersionPublishingService::class)->publish($futureVersion);
        }

        return $versions;
    }

    private function questionDefinitionsFor(AssessmentModule $module, int $singleSelectTypeId, int $openEndedTypeId, int $numericTypeId): array
    {
        $isSharedProcessQuestion = $module->module_code === 'DOPD';
        $processCode = $isSharedProcessQuestion ? 'DEMO.SERVICE_PROCESS' : "{$module->module_code}.DEMO.Q1";
        $processText = $isSharedProcessQuestion
            ? 'Does the service have a documented process available today?'
            : "Does {$module->module_name} have a documented service process available today?";

        return [
            [
                'key' => "{$module->module_code}_PROCESS",
                'code' => $processCode,
                'text' => $processText,
                'display_text' => "Does {$module->module_name} have a documented service process available today?",
                'type_id' => $singleSelectTypeId,
                'response_type' => 'SINGLE_SELECT',
                'is_scored' => true,
                'order' => 1,
                'indicator' => 'SERVICE_PROCESS',
                'evidence' => 'Optional: note where the process is documented or observed.',
                'options' => [
                    ['Yes, current and used', 100, false],
                    ['Partially available', 50, false],
                    ['No documented process', 0, $module->module_code === 'DOPD'],
                ],
            ],
            [
                'key' => "{$module->module_code}_SUPPLIES",
                'code' => "{$module->module_code}.DEMO.Q2",
                'text' => "Are minimum supplies for {$module->module_name} available at the time of assessment?",
                'display_text' => null,
                'type_id' => $singleSelectTypeId,
                'response_type' => 'SINGLE_SELECT',
                'is_scored' => true,
                'order' => 2,
                'indicator' => 'SUPPLIES',
                'evidence' => 'Optional: summarize observed supply availability.',
                'options' => [
                    ['Available and adequate', 100, false],
                    ['Available but inadequate', 50, false],
                    ['Not available', 0, false],
                ],
            ],
            [
                'key' => "{$module->module_code}_CONTEXT",
                'code' => "{$module->module_code}.DEMO.Q3",
                'text' => "Add any local context that would help interpret {$module->module_name} readiness.",
                'display_text' => null,
                'type_id' => $openEndedTypeId,
                'response_type' => 'OPEN_ENDED',
                'is_scored' => false,
                'order' => 3,
                'indicator' => 'CONTEXT',
                'evidence' => 'This answer provides useful context for analysis and reporting.',
                'options' => [],
            ],
            [
                'key' => "{$module->module_code}_VOLUME",
                'code' => "{$module->module_code}.DEMO.Q4",
                'text' => "Record the most relevant service-volume measure for {$module->module_name}.",
                'display_text' => null,
                'type_id' => $numericTypeId,
                'response_type' => 'NUMERIC',
                'is_scored' => false,
                'order' => 4,
                'indicator' => 'SERVICE_VOLUME',
                'evidence' => 'This numeric answer provides useful context for analysis and reporting.',
                'numeric_config' => ['unit' => 'count', 'min' => 0, 'max' => 365, 'step' => 1],
                'options' => [],
            ],
        ];
    }

    private function upsertQuestionIdentity(AssessmentModule $module, array $definition): Question
    {
        $questionGroupId = DB::table('question_groups')
            ->where('module_id', $module->module_id)
            ->where('group_number', 1)
            ->value('question_group_id');

        return Question::firstOrCreate(
            ['question_code' => $definition['code']],
            [
                'module_id' => $module->module_id,
                'question_group_id' => $questionGroupId,
                'question_number' => $definition['order'],
                'question_text' => $definition['text'],
                'type_id' => $definition['type_id'],
                'requires_observation' => false,
                'display_order' => $definition['order'],
                'is_active' => true,
                'is_scored' => $definition['is_scored'],
                'source' => 'DEMO_CURATED',
                'question_status' => 'APPROVED',
                'standard_alignment_status' => 'DEMO_CONTENT',
                'numeric_unit' => $definition['numeric_config']['unit'] ?? null,
                'numeric_min' => $definition['numeric_config']['min'] ?? null,
                'numeric_max' => $definition['numeric_config']['max'] ?? null,
                'numeric_step' => $definition['numeric_config']['step'] ?? null,
            ]
        );
    }

    private function publishQuestionVersion(Question $question, int $versionNumber, array $definition): QuestionVersion
    {
        $version = QuestionVersion::firstOrCreate(
            ['question_id' => $question->question_id, 'version_number' => $versionNumber],
            [
                'status' => QuestionVersion::STATUS_APPROVED,
                'question_text' => $definition['text'],
                'type_id' => $definition['type_id'],
                'options' => $this->optionPayload($question, $definition['options']),
                'numeric_config' => $definition['numeric_config'] ?? null,
                'numeric_bands' => $definition['numeric_bands'] ?? [],
                'requires_observation' => false,
                'methodology_notes' => 'Demonstration-only Vytte methodology fixture.',
                'source_summary' => 'Vytte demonstration content; not validated production methodology.',
                'approved_by' => null,
                'effective_date' => now()->toDateString(),
            ]
        );

        if ($version->status !== QuestionVersion::STATUS_PUBLISHED) {
            $version = app(QuestionVersionPublishingService::class)->publish($version);
        }

        return $version->fresh(['question', 'questionType']);
    }

    private function optionPayload(Question $question, array $options): array
    {
        return collect($options)->map(fn ($option, $index) => [
            'option_id' => QuestionOption::where('question_id', $question->question_id)
                ->where('option_order', $index + 1)
                ->value('option_id'),
            'option_key' => 'OPT'.($index + 1),
            'option_label' => $option[0],
            'option_order' => $index + 1,
            'score_weight' => $option[1],
            'critical_failure' => (bool) $option[2],
        ])->values()->all();
    }

    private function mirrorRuntimeQuestionShape(Question $question, array $definition): void
    {
        foreach ($definition['options'] as $optionOrder => [$label, $weight, $criticalFailure]) {
            QuestionOption::firstOrCreate(
                ['question_id' => $question->question_id, 'option_order' => $optionOrder + 1],
                [
                    'option_label' => $label,
                    'score_weight' => $weight,
                    'is_flagged_pain_point' => $criticalFailure,
                ]
            );
        }

        foreach ($definition['numeric_bands'] ?? [] as $bandIndex => $bandData) {
            QuestionNumericBand::firstOrCreate(
                ['question_id' => $question->question_id, 'band_order' => $bandIndex + 1],
                [
                    'min_value' => $bandData['min_value'] ?? null,
                    'max_value' => $bandData['max_value'] ?? null,
                    'score_weight' => (float) $bandData['score_weight'],
                ]
            );
        }
    }

    private function seedFrameworkVersions(array $modules, array $questionVersions, array $domainDefinitions): array
    {
        $frameworks = [];
        foreach ($modules as $code => $module) {
            $framework = $this->createFramework(
                $module,
                DepartmentFrameworkVersion::TYPE_DEPARTMENT,
                1,
                $module->module_name.' Demonstration Framework v1',
                'Demonstration-only official Vytte department framework used to exercise governed composition.',
            );

            $this->placeQuestions($framework, [
                [$questionVersions["{$code}_PROCESS"], $code === 'DOPD' ? "Does {$module->module_name} have a documented service process available today?" : null, 1, true, 'SERVICE_PROCESS'],
                [$questionVersions["{$code}_SUPPLIES"], null, 2, true, 'SUPPLIES'],
                [$questionVersions["{$code}_CONTEXT"], null, 3, false, 'CONTEXT'],
                [$questionVersions["{$code}_VOLUME"], null, 4, false, 'SERVICE_VOLUME'],
            ], $domainDefinitions);

            if ($framework->status !== DepartmentFrameworkVersion::STATUS_PUBLISHED) {
                $framework = app(DepartmentFrameworkPublishingService::class)->publish($framework);
            }
            $frameworks[$code] = $framework;
        }

        $focused = $this->createFramework(
            $modules['DMNH'],
            DepartmentFrameworkVersion::TYPE_FOCUSED,
            2,
            'Focused Mental Health Demonstration Framework v1',
            'Focused demonstration framework deliberately scoped to basic mental health readiness.',
        );
        $this->placeQuestions($focused, [
            [$questionVersions['DOPD_PROCESS'], 'Does Mental Health have a documented service process available today?', 1, true, 'SERVICE_PROCESS'],
            [$questionVersions['DMNH_SUPPLIES'], null, 2, true, 'SUPPLIES'],
            [$questionVersions['DMNH_CONTEXT'], null, 3, false, 'CONTEXT'],
            [$questionVersions['DMNH_VOLUME'], null, 4, false, 'SERVICE_VOLUME'],
        ], $domainDefinitions);
        if ($focused->status !== DepartmentFrameworkVersion::STATUS_PUBLISHED) {
            $focused = app(DepartmentFrameworkPublishingService::class)->publish($focused);
        }
        $frameworks['DMNH_FOCUSED'] = $focused;

        return $frameworks;
    }

    private function createFramework(AssessmentModule $module, string $type, int $versionNumber, string $name, string $description): DepartmentFrameworkVersion
    {
        return DepartmentFrameworkVersion::firstOrCreate(
            ['module_id' => $module->module_id, 'version_number' => $versionNumber],
            [
                'framework_type' => $type,
                'display_name' => $name,
                'description' => $description,
                'purpose' => $description,
                'source_authority' => 'Vytte demonstration content',
                'license_code' => 'DEMO-NOT-FOR-PRODUCTION',
                'methodology_notes' => 'Demo-only content proving reusable question versions and framework-specific placements.',
                'source_summary' => 'Internally curated demonstration fixture; not validated clinical methodology.',
                'provenance' => ['content_kind' => 'demonstration'],
                'critical_failure_rules' => ['uses_flagged_options' => true],
                'effective_date' => now()->toDateString(),
            ]
        );
    }

    private function placeQuestions(DepartmentFrameworkVersion $framework, array $placements, array $domainDefinitions): void
    {
        $section = FrameworkSection::firstOrCreate(
            ['framework_version_id' => $framework->framework_version_id, 'section_code' => 'DEMO_READINESS'],
            [
                'section_name' => 'Demonstration Readiness',
                'purpose' => 'Prove framework sections independent from later analysis domains.',
                'display_order' => 1,
            ]
        );

        $indicators = [];
        foreach ([
            'SERVICE_PROCESS' => 'Documented service process',
            'SUPPLIES' => 'Minimum service supplies',
            'CONTEXT' => 'Local interpretation context',
            'SERVICE_VOLUME' => 'Service volume context',
        ] as $code => $name) {
            $indicators[$code] = FrameworkIndicator::firstOrCreate(
                ['framework_version_id' => $framework->framework_version_id, 'indicator_code' => $code],
                [
                    'framework_section_id' => $section->framework_section_id,
                    'indicator_name' => $name,
                    'description' => 'Demonstration-only indicator.',
                    'display_order' => count($indicators) + 1,
                ]
            );
        }

        $indicatorDomainMap = [
            'SERVICE_PROCESS' => 'GOV',
            'SUPPLIES' => 'RES',
            'CONTEXT' => 'PCOM',
            'SERVICE_VOLUME' => 'INFO',
        ];
        foreach ($indicatorDomainMap as $indicatorCode => $domainCode) {
            FrameworkIndicatorDomainMapping::firstOrCreate(
                [
                    'framework_indicator_id' => $indicators[$indicatorCode]->framework_indicator_id,
                    'domain_definition_id' => $domainDefinitions[$domainCode]->domain_definition_id,
                ],
                [
                    'is_primary' => true,
                    'contribution_weight' => 1,
                    'rationale' => 'Demonstration mapping from indicator purpose to the governed analytical taxonomy.',
                ]
            );
        }

        $subIndexId = DB::table('sub_indices')
            ->where('module_id', $framework->module_id)
            ->where('acronym', $framework->module?->module_code.'R')
            ->value('sub_index_id');

        foreach ($placements as [$questionVersion, $displayText, $order, $scored, $indicatorCode]) {
            FrameworkQuestionPlacement::firstOrCreate(
                ['framework_version_id' => $framework->framework_version_id, 'display_order' => $order],
                [
                    'framework_section_id' => $section->framework_section_id,
                    'framework_indicator_id' => $indicators[$indicatorCode]->framework_indicator_id,
                    'question_id' => $questionVersion->question_id,
                    'question_version_id' => $questionVersion->question_version_id,
                    'sub_index_id' => $scored ? $subIndexId : null,
                    'is_required' => $scored,
                    'applicability' => ['demo' => true],
                    'evidence_expectation' => $indicatorCode === 'CONTEXT' || $indicatorCode === 'SERVICE_VOLUME'
                        ? 'This answer provides useful context for analysis and reporting.'
                        : 'Optional supporting evidence may be added to explain the answer.',
                    'weight' => 1,
                    'scoring_contribution' => $scored,
                    'criticality' => $indicatorCode === 'SERVICE_PROCESS' ? 'CRITICAL_IF_FLAGGED' : 'STANDARD',
                    'help_text' => 'Demonstration-only placement.',
                    'local_display_text' => $displayText,
                    'metadata' => ['demo_only' => true],
                ]
            );

            if ($scored) {
                DB::table('sub_index_questions')->insertOrIgnore([
                    'sub_index_id' => $subIndexId,
                    'question_id' => $questionVersion->question_id,
                    'weight' => 1,
                ]);
            }
        }
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
                'description' => 'Focused demonstration assessment that opens only the Mental Health focused framework.',
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
            ['DMNH_FOCUSED', 'REQUIRED', 'Mental Health', 1],
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
