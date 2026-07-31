<?php

namespace Database\Seeders;

use App\Models\AssessmentCatalogueRelease;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FacilityProfile;
use App\Models\HealthDomain;
use App\Services\CataloguePublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The official catalogue releases.
 *
 * A published framework is not yet selectable by a customer. A catalogue release is what
 * makes it selectable — it pins an exact framework version, ties it to a facility type or
 * a health domain, and fixes the aggregation policy. This seeder turns published official
 * frameworks into catalogue releases a customer can choose when creating an assessment.
 *
 * Comprehensive releases compose department frameworks against a facility profile.
 * Focused releases pin a single-subject framework against a health domain. Both publish
 * through CataloguePublishingService, so each receives the same validation and content
 * hash as one created by hand.
 */
class OfficialCatalogueSeeder extends Seeder
{
    private const AGGREGATION = [
        'method' => 'MEAN_OF_SCORED_SUB_INDICES',
        'critical_failures' => [
            'enabled' => true,
            'option_score_at_or_below' => 0,
            'overall_score' => 'ZERO',
        ],
    ];

    public function run(): void
    {
        $publishing = app(CataloguePublishingService::class);
        $published = 0;
        $skipped = 0;

        foreach (self::comprehensive() as $spec) {
            $this->release($spec, $publishing) ? $published++ : $skipped++;
        }

        foreach (self::focused() as $spec) {
            $this->release($spec, $publishing) ? $published++ : $skipped++;
        }

        $this->command?->info("Official catalogue releases: {$published} published, {$skipped} skipped.");
    }

    private function release(array $spec, CataloguePublishingService $publishing): bool
    {
        return DB::transaction(function () use ($spec, $publishing): bool {
            $existingRelease = AssessmentCatalogueRelease::where('release_code', $spec['code'])->first();
            if ($existingRelease && $existingRelease->status !== AssessmentCatalogueRelease::STATUS_DRAFT) {
                return false;
            }

            $attributes = [
                'release_name' => $spec['name'],
                'description' => $spec['description'],
                'creation_path' => $spec['path'],
                'aggregation_policy' => self::AGGREGATION,
                'composition_rules' => ['latest_resolution' => 'forbidden'],
            ];

            $profile = null;
            if ($spec['path'] === 'COMPREHENSIVE') {
                $profile = FacilityProfile::where('profile_code', $spec['profile'])->with('departments')->first();
                if (! $profile) {
                    return false;
                }
                $attributes['facility_profile_id'] = $profile->facility_profile_id;
            } else {
                $domainId = HealthDomain::where('domain_code', $spec['domain'])->value('health_domain_id');
                if (! $domainId) {
                    return false;
                }
                $attributes['health_domain_id'] = $domainId;
            }

            $departmentSpecs = ($spec['compose_profile'] ?? false) && $profile
                ? $this->departmentSpecsFor($profile)
                : ($spec['departments'] ?? [[
                    'framework' => $spec['framework'],
                    'applicability' => 'REQUIRED',
                    'label' => $spec['name'],
                ]]);
            $frameworks = collect($departmentSpecs)->map(function (array $department) use ($spec): array {
                $framework = DepartmentFrameworkVersion::where('display_name', $department['framework'])
                    ->where('license_code', 'VYTTE-OFFICIAL')
                    ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
                    ->first();

                if (! $framework) {
                    throw new \RuntimeException("Framework not found for release {$spec['code']}: {$department['framework']}.");
                }

                return ['framework' => $framework, 'spec' => $department];
            });

            if (isset($spec['parent'])) {
                $attributes['parent_release_id'] = AssessmentCatalogueRelease::where('release_code', $spec['parent'])
                    ->value('catalogue_release_id');
            }

            $release = AssessmentCatalogueRelease::firstOrCreate(['release_code' => $spec['code']], $attributes);

            $sync = [];
            foreach ($frameworks->values() as $index => $row) {
                $framework = $row['framework'];
                $department = $row['spec'];
                $sync[$framework->framework_version_id] = [
                    'module_id' => $framework->module_id,
                    'applicability' => $department['applicability'],
                    'display_order' => $index + 1,
                    'area_label' => $department['label'],
                ];
            }
            $release->departmentFrameworkVersions()->syncWithoutDetaching($sync);

            $publishing->publish($release->fresh());

            if (isset($spec['parent'])) {
                $parent = AssessmentCatalogueRelease::where('release_code', $spec['parent'])->first();
                if ($parent?->status === AssessmentCatalogueRelease::STATUS_PUBLISHED) {
                    $parent->update(['status' => AssessmentCatalogueRelease::STATUS_SUPERSEDED]);
                }
            }

            return true;
        });
    }

    /**
     * Compose the content-ready intersection of a facility profile and the reusable
     * official department-framework catalogue.
     *
     * @return list<array{framework: string, applicability: string, label: string}>
     */
    private function departmentSpecsFor(FacilityProfile $profile): array
    {
        $frameworkNames = self::departmentFrameworkNames();

        return $profile->departments
            ->filter(fn ($department) => isset($frameworkNames[$department->module_code]))
            ->map(fn ($department) => [
                'framework' => $frameworkNames[$department->module_code],
                'applicability' => $department->pivot->applicability,
                'label' => $department->module_name,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    private static function departmentFrameworkNames(): array
    {
        return [
            'GOV' => 'Leadership & Governance Department Framework',
            'INF' => 'Infrastructure, Utilities & Infection Prevention Department Framework',
            'WSHF' => 'Water, Sanitation & Hygiene Department Framework',
            'HRM' => 'Human Resource Management Department Framework',
            'REC' => 'Records & Health Information Management Department Framework',
            'QAS' => 'Quality & Patient Safety Department Framework',
            'ANC' => 'Antenatal Care Department Framework',
            'IMM' => 'Child Health & Immunization Department Framework',
            'NUT' => 'Nutrition Services Department Framework',
            'PHM' => 'Pharmacy Department Framework',
            'COM' => 'Community Health & Outreach Department Framework',
            'LAB' => 'Laboratory Department Framework',
            'HTB' => 'HIV, TB & PMTCT Services Department Framework',
            'MAL' => 'Malaria Services Department Framework',
            'MNH' => 'Mental Health Services Department Framework',
            'OPD' => 'Outpatient Department Framework',
            'REF' => 'Referral Management Department Framework',
            'IPD' => 'In-Patient Ward Department Framework',
            'EMR' => 'Emergency & Accident Unit Department Framework',
            'THR' => 'Theatre & Surgical Services Department Framework',
            'ICU' => 'Intensive & High Dependency Care Department Framework',
            'RAD' => 'Radiology & Imaging Department Framework',
            'BLB' => 'Blood Bank & Transfusion Services Department Framework',
            'LBD' => 'Labour & Delivery Department Framework',
            'PNC' => 'Postnatal Care Department Framework',
            'FP' => 'Family Planning Department Framework',
            'SRH' => 'Sexual & Reproductive Health Department Framework',
            'NCD' => 'Non-Communicable Disease Services Department Framework',
            'ADO' => 'Adolescent & Youth Health Department Framework',
            'OLD' => 'Older People & Geriatric Care Department Framework',
            'REH' => 'Rehabilitation Services Department Framework',
            'PAL' => 'Palliative & End-of-Life Care Department Framework',
            'ORL' => 'Oral Health & Dental Services Department Framework',
            'EYE' => 'Eye Health Services Department Framework',
            'AMR' => 'Antimicrobial Stewardship Department Framework',
            'OBR' => 'Outbreak Preparedness & Response Department Framework',
            'NTD' => 'Neglected Tropical Disease Services Department Framework',
            'DIS' => 'Disability & Inclusion Department Framework',
            'HPR' => 'Health Promotion & Education Department Framework',
            'ENV' => 'Environment & Climate Resilience Department Framework',
            'OCC' => 'Staff Health & Safety Department Framework',
            'FIN' => 'Finance, Billing & Insurance Claims Department Framework',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function comprehensive(): array
    {
        $releases = [
            ['code' => 'VYTTE_HOSPITAL_READINESS_V1', 'name' => 'Hospital Operational Readiness', 'description' => 'Whole-hospital readiness assessment.', 'path' => 'COMPREHENSIVE', 'profile' => 'GENERAL_HOSPITAL', 'framework' => 'Hospital Operational Readiness'],
            [
                'code' => 'VYTTE_HOSPITAL_READINESS_V2',
                'parent' => 'VYTTE_HOSPITAL_READINESS_V1',
                'name' => 'General Hospital Comprehensive Assessment',
                'description' => 'Comprehensive hospital assessment composed from reusable department frameworks.',
                'path' => 'COMPREHENSIVE',
                'profile' => 'GENERAL_HOSPITAL',
                'compose_profile' => true,
            ],
            ['code' => 'VYTTE_PHC_ASSESSMENT_V1', 'name' => 'Primary Healthcare Facility Assessment', 'description' => 'General assessment of a primary healthcare facility.', 'path' => 'COMPREHENSIVE', 'profile' => 'PRIMARY_HEALTH_CENTRE', 'framework' => 'Primary Healthcare Facility Assessment'],
            [
                'code' => 'VYTTE_PHC_ASSESSMENT_V2',
                'parent' => 'VYTTE_PHC_ASSESSMENT_V1',
                'name' => 'Primary Healthcare Facility Assessment',
                'description' => 'Comprehensive PHC assessment composed from reusable department frameworks.',
                'path' => 'COMPREHENSIVE',
                'profile' => 'PRIMARY_HEALTH_CENTRE',
                'compose_profile' => true,
            ],
            [
                'code' => 'VYTTE_CLINIC_ASSESSMENT_V1',
                'name' => 'Clinic Comprehensive Assessment',
                'description' => 'Comprehensive clinic assessment composed from reusable department frameworks.',
                'path' => 'COMPREHENSIVE',
                'profile' => 'CLINIC',
                'compose_profile' => true,
            ],
            [
                'code' => 'VYTTE_HOSPITAL_READINESS_V3',
                'parent' => 'VYTTE_HOSPITAL_READINESS_V2',
                'name' => 'General Hospital Comprehensive Assessment',
                'description' => 'Comprehensive hospital assessment with the complete approved department library.',
                'path' => 'COMPREHENSIVE',
                'profile' => 'GENERAL_HOSPITAL',
                'compose_profile' => true,
            ],
            [
                'code' => 'VYTTE_PHC_ASSESSMENT_V3',
                'parent' => 'VYTTE_PHC_ASSESSMENT_V2',
                'name' => 'Primary Healthcare Facility Assessment',
                'description' => 'Comprehensive PHC assessment with the complete approved service library.',
                'path' => 'COMPREHENSIVE',
                'profile' => 'PRIMARY_HEALTH_CENTRE',
                'compose_profile' => true,
            ],
            [
                'code' => 'VYTTE_CLINIC_ASSESSMENT_V2',
                'parent' => 'VYTTE_CLINIC_ASSESSMENT_V1',
                'name' => 'Clinic Comprehensive Assessment',
                'description' => 'Comprehensive clinic assessment with the complete approved service library.',
                'path' => 'COMPREHENSIVE',
                'profile' => 'CLINIC',
                'compose_profile' => true,
            ],
        ];

        $existingProfiles = ['GENERAL_HOSPITAL', 'PRIMARY_HEALTH_CENTRE', 'CLINIC'];
        foreach (OfficialReferenceSeeder::facilityProfiles() as $code => [$name, $settingType]) {
            if ($settingType !== 'HEALTH_FACILITY' || in_array($code, $existingProfiles, true)) {
                continue;
            }

            $releases[] = [
                'code' => "VYTTE_{$code}_COMPREHENSIVE_V1",
                'name' => "{$name} Comprehensive Assessment",
                'description' => "Comprehensive assessment composed for a {$name} from approved reusable department frameworks.",
                'path' => 'COMPREHENSIVE',
                'profile' => $code,
                'compose_profile' => true,
            ];
        }

        return $releases;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function focused(): array
    {
        return [
            ['code' => 'VYTTE_IPC_V1', 'name' => 'Infection Prevention & Control Assessment', 'description' => 'Focused IPC assessment.', 'path' => 'FOCUSED', 'domain' => 'INFECTION_PREVENTION', 'framework' => 'Infection Prevention & Control Assessment'],
            ['code' => 'VYTTE_WASH_V1', 'name' => 'WASH in Health Care Facilities', 'description' => 'Focused WASH assessment.', 'path' => 'FOCUSED', 'domain' => 'WASH', 'framework' => 'WASH in Health Care Facilities'],
            ['code' => 'VYTTE_HIV_V1', 'name' => 'HIV Programme Assessment', 'description' => 'Focused HIV programme assessment.', 'path' => 'FOCUSED', 'domain' => 'HIV', 'framework' => 'HIV Programme Assessment'],
            ['code' => 'VYTTE_TB_V1', 'name' => 'TB Programme Assessment', 'description' => 'Focused TB programme assessment.', 'path' => 'FOCUSED', 'domain' => 'TUBERCULOSIS', 'framework' => 'TB Programme Assessment'],
            ['code' => 'VYTTE_MALARIA_V1', 'name' => 'Malaria Programme Assessment', 'description' => 'Focused malaria programme assessment.', 'path' => 'FOCUSED', 'domain' => 'MALARIA', 'framework' => 'Malaria Programme Assessment'],
            ['code' => 'VYTTE_IMMUNIZATION_V1', 'name' => 'Immunization Programme Assessment', 'description' => 'Focused immunization programme assessment.', 'path' => 'FOCUSED', 'domain' => 'IMMUNIZATION', 'framework' => 'Immunization Programme Assessment'],
            ['code' => 'VYTTE_MATERNAL_V1', 'name' => 'Maternal & Newborn Care Assessment', 'description' => 'Focused maternal and newborn assessment.', 'path' => 'FOCUSED', 'domain' => 'MATERNAL_HEALTH', 'framework' => 'Maternal & Newborn Care Assessment'],
            ['code' => 'VYTTE_CHILD_V1', 'name' => 'Child Health Assessment', 'description' => 'Focused child health assessment.', 'path' => 'FOCUSED', 'domain' => 'CHILD_HEALTH', 'framework' => 'Child Health Assessment'],
            ['code' => 'VYTTE_NUTRITION_V1', 'name' => 'Nutrition Programme Assessment', 'description' => 'Focused nutrition assessment.', 'path' => 'FOCUSED', 'domain' => 'NUTRITION', 'framework' => 'Nutrition Programme Assessment'],
            ['code' => 'VYTTE_MENTAL_V1', 'name' => 'Mental Health Services Assessment', 'description' => 'Focused mental health assessment.', 'path' => 'FOCUSED', 'domain' => 'MENTAL_HEALTH', 'framework' => 'Mental Health Services Assessment'],
            ['code' => 'VYTTE_LAB_V1', 'name' => 'Laboratory Services Assessment', 'description' => 'Focused laboratory assessment.', 'path' => 'FOCUSED', 'domain' => 'LABORATORY', 'framework' => 'Laboratory Services Assessment'],
            ['code' => 'VYTTE_PHARMACY_V1', 'name' => 'Pharmacy & Medicines Assessment', 'description' => 'Focused pharmacy assessment.', 'path' => 'FOCUSED', 'domain' => 'PHARMACY', 'framework' => 'Pharmacy & Medicines Assessment'],
            ['code' => 'VYTTE_EMERGENCY_V1', 'name' => 'Emergency Care Assessment', 'description' => 'Focused emergency care assessment.', 'path' => 'FOCUSED', 'domain' => 'EMERGENCY_CARE', 'framework' => 'Emergency Care Assessment'],
        ];
    }
}
