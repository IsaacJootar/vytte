<?php

namespace Database\Seeders;

use App\Models\AssessmentModule;
use App\Models\FacilityProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Official reference data for the production knowledge base.
 *
 * Extends what `ReferenceDataSeeder` establishes: the departments needed to carry the
 * official framework library, the facility types Vytte supports, and which departments
 * each facility type is expected to have.
 *
 * Official content only. Nothing here is demonstration data, and nothing here depends on
 * `PlatformGovernedDemoSeeder`, which is excluded from the official seed.
 */
class OfficialReferenceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedDepartments();
            $this->seedFacilityProfiles();
            $this->mapProfilesToDepartments();
        });
    }

    /**
     * Departments the framework library needs.
     *
     * A framework belongs to exactly one department, so every subject that gets its own
     * official framework needs one. `ReferenceDataSeeder` already covers the clinical
     * core; these are the additions.
     *
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function additionalDepartments(): array
    {
        return [
            // Facility-wide. Carries the cross-cutting frameworks that are not owned by
            // any single clinical department.
            'FAC' => ['Whole Facility', 'Facility Manager · Medical Director · Matron', 45],
            'GOV' => ['Leadership & Governance', 'Facility Manager · Board Member · Medical Director', 25],
            'QAS' => ['Quality & Patient Safety', 'Quality Focal Person · Matron · Medical Director', 30],
            'HPR' => ['Health Promotion & Education', 'Health Promotion Officer · CHEW', 18],
            'ENV' => ['Environment & Climate Resilience', 'Facility Manager · Maintenance Officer', 20],
            'OCC' => ['Staff Health & Safety', 'Safety Focal Person · Matron · HR Officer', 18],
            'DIS' => ['Disability & Inclusion', 'Facility Manager · Matron', 15],

            // Facility WASH. The existing WASH module targets schools; health facility
            // WASH is assessed against different expectations.
            'WSHF' => ['Water, Sanitation & Hygiene', 'Facility Manager · Cleaner Supervisor · IPC Focal Person', 25],

            // Disease and programme services.
            'MAL' => ['Malaria Services', 'Medical Officer · Nurse · Laboratory Scientist', 20],
            'NCD' => ['Non-Communicable Disease Services', 'Medical Officer · Nurse · Pharmacist', 22],
            'NTD' => ['Neglected Tropical Disease Services', 'Medical Officer · Community Health Officer', 15],
            'AMR' => ['Antimicrobial Stewardship', 'Pharmacist · Medical Officer · Laboratory Scientist', 18],
            'OBR' => ['Outbreak Preparedness & Response', 'Surveillance Focal Person · Medical Officer', 22],

            // Population groups.
            'SRH' => ['Sexual & Reproductive Health', 'Nurse · Midwife · Medical Officer', 20],
            'ADO' => ['Adolescent & Youth Health', 'Nurse · CHEW · Adolescent Focal Person', 18],
            'OLD' => ['Older People & Geriatric Care', 'Nurse · Medical Officer', 15],

            // Clinical services assessed in their own right.
            'REH' => ['Rehabilitation Services', 'Physiotherapist · Occupational Therapist', 18],
            'PAL' => ['Palliative & End-of-Life Care', 'Nurse · Medical Officer', 15],
            'ORL' => ['Oral Health & Dental Services', 'Dentist · Dental Therapist', 15],
            'EYE' => ['Eye Health Services', 'Ophthalmic Nurse · Optometrist', 15],
        ];
    }

    /**
     * The facility types Vytte supports.
     *
     * Setting type governs whether departments apply at all. A hospital is assessed
     * department by department; a community programme is not, which is why they carry
     * different setting types rather than being forced into one shape.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function facilityProfiles(): array
    {
        return [
            // Hospitals, broadly by level of care.
            'NATIONAL_REFERRAL_HOSPITAL' => ['National Referral Hospital', 'HEALTH_FACILITY', 'Highest level of referral, usually with specialist and teaching functions.'],
            'TEACHING_HOSPITAL' => ['Teaching Hospital', 'HEALTH_FACILITY', 'Hospital with a formal training and academic role alongside clinical services.'],
            'REGIONAL_HOSPITAL' => ['Regional Hospital', 'HEALTH_FACILITY', 'Serves a region and receives referrals from district level.'],
            'DISTRICT_HOSPITAL' => ['District Hospital', 'HEALTH_FACILITY', 'First referral hospital for a district, with inpatient and surgical capacity.'],
            'GENERAL_HOSPITAL' => ['General Hospital', 'HEALTH_FACILITY', 'General inpatient and outpatient hospital services.'],
            'SPECIALIST_HOSPITAL' => ['Specialist Hospital', 'HEALTH_FACILITY', 'Concentrates on one clinical speciality.'],
            'MATERNITY_HOSPITAL' => ['Maternity Hospital or Unit', 'HEALTH_FACILITY', 'Dedicated maternal and newborn care.'],
            'MENTAL_HEALTH_HOSPITAL' => ['Mental Health Facility', 'HEALTH_FACILITY', 'Dedicated mental health inpatient or outpatient care.'],
            'REHABILITATION_CENTRE' => ['Rehabilitation Centre', 'HEALTH_FACILITY', 'Physiotherapy, occupational therapy and assistive products.'],

            // Primary and community level.
            'PRIMARY_HEALTH_CENTRE' => ['Primary Health Centre', 'HEALTH_FACILITY', 'Primary care with basic maternal, child and outpatient services.'],
            'HEALTH_CENTRE' => ['Health Centre', 'HEALTH_FACILITY', 'Larger primary facility, sometimes with limited inpatient beds.'],
            'HEALTH_POST' => ['Health Post or Dispensary', 'HEALTH_FACILITY', 'Smallest fixed facility, often single-staffed.'],
            'CLINIC' => ['Clinic', 'HEALTH_FACILITY', 'Outpatient clinic, public or private.'],
            'MOBILE_CLINIC' => ['Mobile Clinic', 'HEALTH_FACILITY', 'Services delivered from a vehicle or temporary site.'],

            // Diagnostic and support services.
            'LABORATORY' => ['Laboratory', 'HEALTH_FACILITY', 'Standalone or reference laboratory.'],
            'DIAGNOSTIC_CENTRE' => ['Diagnostic and Imaging Centre', 'HEALTH_FACILITY', 'Imaging and diagnostic services outside a hospital.'],
            'BLOOD_BANK' => ['Blood Bank or Transfusion Centre', 'HEALTH_FACILITY', 'Blood collection, screening, storage and distribution.'],
            'PHARMACY' => ['Pharmacy', 'HEALTH_FACILITY', 'Community or facility pharmacy.'],
            'DENTAL_FACILITY' => ['Dental Facility', 'HEALTH_FACILITY', 'Oral health and dental services.'],
            'EYE_CARE_FACILITY' => ['Eye Care Facility', 'HEALTH_FACILITY', 'Vision, refractive and eye surgical services.'],

            // Programmes and non-facility settings, where departments do not apply.
            'COMMUNITY_HEALTH_PROGRAMME' => ['Community Health Programme', 'COMMUNITY', 'Community health workers, outreach and community structures.'],
            'PUBLIC_HEALTH_UNIT' => ['Public Health Unit', 'GOVERNMENT_ORG', 'District or municipal public health function.'],
            'NGO_HEALTH_PROGRAMME' => ['NGO Health Programme', 'NGO_PROGRAMME', 'Programme delivered by a non-governmental organisation.'],
            'HUMANITARIAN_PROGRAMME' => ['Refugee or Humanitarian Health Programme', 'NGO_PROGRAMME', 'Health services in a displacement or emergency setting.'],
            'SCHOOL_HEALTH_SERVICE' => ['School Health Service', 'SCHOOL', 'Health services delivered in or to a school.'],
            'OCCUPATIONAL_HEALTH_SERVICE' => ['Occupational Health Service', 'WORKPLACE', 'Workplace health and safety services.'],
            'MILITARY_HEALTH_SERVICE' => ['Military Health Service', 'GOVERNMENT_ORG', 'Health services for armed forces personnel.'],
            'CORRECTIONAL_HEALTH_SERVICE' => ['Correctional Health Service', 'CORRECTIONAL', 'Health services in a prison or detention setting.'],
        ];
    }

    private function seedDepartments(): void
    {
        foreach (self::additionalDepartments() as $code => [$name, $respondent, $minutes]) {
            AssessmentModule::updateOrCreate(
                ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => $code],
                [
                    'module_name' => $name,
                    'primary_respondent' => $respondent,
                    'estimated_duration_minutes' => $minutes,
                    'data_collection_methods' => 'Interview · Observation · Record review',
                    'is_active' => true,
                    'requires_consent' => false,
                ]
            );
        }
    }

    private function seedFacilityProfiles(): void
    {
        $order = 0;

        foreach (self::facilityProfiles() as $code => [$name, $settingType, $description]) {
            FacilityProfile::updateOrCreate(
                ['profile_code' => $code],
                [
                    'profile_name' => $name,
                    'setting_type_code' => $settingType,
                    'description' => $description,
                    'status' => FacilityProfile::STATUS_PUBLISHED,
                    'display_order' => ++$order,
                ]
            );
        }
    }

    /**
     * Which departments each facility type is expected to have.
     *
     * REQUIRED cannot be removed, DEFAULT is pre-ticked but removable, OPTIONAL is
     * offered but off. The point is that a user opening a district hospital assessment
     * starts with a sensible set rather than an empty list of forty departments.
     *
     * Only facility settings map departments; a community or NGO programme is not
     * assessed department by department.
     */
    private function mapProfilesToDepartments(): void
    {
        // Every facility has these, whatever its size.
        $universal = ['FAC', 'GOV', 'INF', 'WSHF', 'HRM', 'REC', 'QAS'];

        // Anything providing direct clinical care.
        $clinicalCore = ['OPD', 'PHM', 'LAB', 'REF'];

        $inpatient = ['IPD', 'EMR', 'THR', 'ICU', 'RAD', 'BLB'];
        $maternal = ['ANC', 'LBD', 'PNC', 'IMM', 'FP', 'NUT', 'SRH'];
        $programmes = ['HTB', 'MAL', 'NCD', 'MNH', 'COM', 'HPR', 'ADO'];

        $map = [
            'NATIONAL_REFERRAL_HOSPITAL' => [$universal, [...$clinicalCore, ...$inpatient, ...$maternal, ...$programmes], ['REH', 'PAL', 'ORL', 'EYE', 'OLD', 'AMR', 'OBR', 'NTD', 'DIS', 'OCC', 'ENV', 'FIN']],
            'TEACHING_HOSPITAL' => [$universal, [...$clinicalCore, ...$inpatient, ...$maternal, ...$programmes], ['REH', 'PAL', 'ORL', 'EYE', 'OLD', 'AMR', 'OBR', 'DIS', 'OCC', 'FIN']],
            'REGIONAL_HOSPITAL' => [$universal, [...$clinicalCore, ...$inpatient, ...$maternal, ...$programmes], ['REH', 'PAL', 'ORL', 'EYE', 'AMR', 'OBR', 'DIS', 'FIN']],
            'DISTRICT_HOSPITAL' => [$universal, [...$clinicalCore, 'IPD', 'EMR', 'THR', 'RAD', ...$maternal, 'HTB', 'MAL', 'NCD', 'MNH', 'COM'], ['ICU', 'BLB', 'REH', 'PAL', 'AMR', 'OBR', 'HPR', 'ADO', 'DIS', 'FIN']],
            'GENERAL_HOSPITAL' => [$universal, [...$clinicalCore, 'IPD', 'EMR', 'THR', 'RAD', ...$maternal, 'HTB', 'MAL', 'NCD'], ['ICU', 'BLB', 'MNH', 'REH', 'PAL', 'AMR', 'COM', 'DIS', 'FIN']],
            'SPECIALIST_HOSPITAL' => [$universal, [...$clinicalCore, 'IPD', 'EMR'], ['THR', 'ICU', 'RAD', 'BLB', 'REH', 'PAL', 'AMR', 'NCD', 'MNH', 'FIN']],
            'MATERNITY_HOSPITAL' => [$universal, ['ANC', 'LBD', 'PNC', 'IMM', 'FP', 'NUT', 'SRH', 'OPD', 'PHM', 'LAB', 'REF'], ['THR', 'BLB', 'IPD', 'EMR', 'ADO', 'COM']],
            'MENTAL_HEALTH_HOSPITAL' => [$universal, ['MNH', 'OPD', 'PHM', 'IPD', 'REF'], ['LAB', 'COM', 'ADO', 'OLD', 'REH', 'DIS']],
            'REHABILITATION_CENTRE' => [$universal, ['REH', 'OPD', 'REF'], ['PHM', 'DIS', 'OLD', 'MNH', 'PAL']],

            'PRIMARY_HEALTH_CENTRE' => [$universal, ['OPD', 'ANC', 'LBD', 'PNC', 'IMM', 'FP', 'NUT', 'PHM', 'REF', 'COM'], ['LAB', 'HTB', 'MAL', 'NCD', 'MNH', 'HPR', 'ADO', 'SRH', 'DIS']],
            'HEALTH_CENTRE' => [$universal, ['OPD', 'ANC', 'PNC', 'IMM', 'FP', 'NUT', 'PHM', 'REF', 'COM'], ['LBD', 'LAB', 'IPD', 'HTB', 'MAL', 'NCD', 'MNH', 'HPR', 'ADO', 'SRH']],
            'HEALTH_POST' => [$universal, ['OPD', 'IMM', 'FP', 'COM', 'REF'], ['ANC', 'PNC', 'NUT', 'PHM', 'MAL', 'HPR']],
            'CLINIC' => [$universal, ['OPD', 'PHM', 'REF'], ['LAB', 'ANC', 'FP', 'IMM', 'NCD', 'MAL', 'HTB']],
            'MOBILE_CLINIC' => [$universal, ['OPD', 'IMM', 'COM', 'REF'], ['ANC', 'FP', 'NUT', 'MAL', 'HPR']],

            'LABORATORY' => [$universal, ['LAB', 'REF'], ['AMR', 'BLB', 'OBR']],
            'DIAGNOSTIC_CENTRE' => [$universal, ['RAD', 'REF'], ['LAB']],
            'BLOOD_BANK' => [$universal, ['BLB', 'LAB'], ['REF']],
            'PHARMACY' => [$universal, ['PHM'], ['AMR', 'NCD', 'HPR']],
            'DENTAL_FACILITY' => [$universal, ['ORL', 'OPD'], ['PHM', 'RAD', 'REF']],
            'EYE_CARE_FACILITY' => [$universal, ['EYE', 'OPD'], ['THR', 'PHM', 'REF']],
        ];

        foreach ($map as $profileCode => [$required, $default, $optional]) {
            $profile = FacilityProfile::where('profile_code', $profileCode)->first();

            if (! $profile) {
                continue;
            }

            $order = 0;

            foreach ([
                'REQUIRED' => $required,
                'DEFAULT' => $default,
                'OPTIONAL' => $optional,
            ] as $applicability => $codes) {
                foreach (array_unique($codes) as $moduleCode) {
                    $moduleId = AssessmentModule::where('module_code', $moduleCode)
                        ->where('target_type_code', 'HEALTH_FACILITY')
                        ->value('module_id');

                    if (! $moduleId) {
                        $this->command?->warn("Department {$moduleCode} is missing; skipped for {$profileCode}.");

                        continue;
                    }

                    DB::table('facility_profile_departments')->updateOrInsert(
                        ['facility_profile_id' => $profile->facility_profile_id, 'module_id' => $moduleId],
                        [
                            'applicability' => $applicability,
                            'display_order' => ++$order,
                            'removal_allowed' => $applicability !== 'REQUIRED',
                        ]
                    );
                }
            }
        }
    }
}
