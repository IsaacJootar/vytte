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
 * a health domain, and fixes the aggregation policy. This seeder turns the 15 published
 * official frameworks into 15 catalogue releases a customer can choose when creating an
 * assessment.
 *
 * Comprehensive releases pin a whole-facility framework against a facility profile.
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
            if (AssessmentCatalogueRelease::where('release_code', $spec['code'])
                ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)->exists()) {
                return false;
            }

            $framework = DepartmentFrameworkVersion::where('display_name', $spec['framework'])
                ->where('license_code', 'VYTTE-OFFICIAL')
                ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
                ->first();

            if (! $framework) {
                $this->command?->warn("Framework not found for release {$spec['code']}: {$spec['framework']}.");

                return false;
            }

            $attributes = [
                'release_name' => $spec['name'],
                'description' => $spec['description'],
                'creation_path' => $spec['path'],
                'aggregation_policy' => self::AGGREGATION,
                'composition_rules' => ['latest_resolution' => 'forbidden'],
            ];

            if ($spec['path'] === 'COMPREHENSIVE') {
                $profile = FacilityProfile::where('profile_code', $spec['profile'])->first();
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

            $release = AssessmentCatalogueRelease::firstOrCreate(['release_code' => $spec['code']], $attributes);

            $release->departmentFrameworkVersions()->syncWithoutDetaching([
                $framework->framework_version_id => [
                    'module_id' => $framework->module_id,
                    'applicability' => 'REQUIRED',
                    'display_order' => 1,
                    'area_label' => $spec['name'],
                ],
            ]);

            $publishing->publish($release->fresh());

            return true;
        });
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function comprehensive(): array
    {
        return [
            ['code' => 'VYTTE_HOSPITAL_READINESS_V1', 'name' => 'Hospital Operational Readiness', 'description' => 'Whole-hospital readiness assessment.', 'path' => 'COMPREHENSIVE', 'profile' => 'GENERAL_HOSPITAL', 'framework' => 'Hospital Operational Readiness'],
            ['code' => 'VYTTE_PHC_ASSESSMENT_V1', 'name' => 'Primary Healthcare Facility Assessment', 'description' => 'General assessment of a primary healthcare facility.', 'path' => 'COMPREHENSIVE', 'profile' => 'PRIMARY_HEALTH_CENTRE', 'framework' => 'Primary Healthcare Facility Assessment'],
        ];
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
