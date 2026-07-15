<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTargetTypes();
        $this->seedTargetCategories();
        $this->seedDomains();
        $this->seedDomainWeights();
        $this->seedMaturityLevels();
        $this->seedAssessmentTiers();
        $this->seedQuestionTypes();
        $this->seedStandardsRegistry();
        $this->seedTopics();
        $this->seedAssessmentModules();
        $this->seedRespondentRoles();
        $this->seedTargetCategoryDefaultModules();
    }

    private function seedTargetTypes(): void
    {
        DB::table('target_types')->insertOrIgnore([
            ['target_type_code' => 'HEALTH_FACILITY', 'target_type_name' => 'Health Facility', 'description' => 'Hospitals, health centres, and clinics — the fully built-out launch scope.'],
            ['target_type_code' => 'SCHOOL', 'target_type_name' => 'School', 'description' => 'Primary and secondary schools — e.g. a WASH/hygiene assessment across a set of schools.'],
            ['target_type_code' => 'COMMUNITY', 'target_type_name' => 'Community', 'description' => 'A community or catchment area assessed as its own entity, not tied to one facility.'],
            ['target_type_code' => 'WATER_POINT', 'target_type_name' => 'Water Point', 'description' => 'A borehole, well, or piped water source — roadmap target type, not yet built out.'],
        ]);
    }

    private function seedTargetCategories(): void
    {
        DB::table('target_categories')->insertOrIgnore([
            ['target_type_code' => 'HEALTH_FACILITY', 'category_code' => 'PHC', 'category_name' => 'Primary Health Centre', 'description' => 'Smallest facility category; typically OPD, ANC, immunization, pharmacy, records.'],
            ['target_type_code' => 'HEALTH_FACILITY', 'category_code' => 'GENERAL_HOSPITAL', 'category_name' => 'General Hospital', 'description' => 'Mid-tier facility; full departmental spread including in-patient and laboratory.'],
            ['target_type_code' => 'HEALTH_FACILITY', 'category_code' => 'REFERRAL_HOSPITAL', 'category_name' => 'Referral / Tertiary Hospital', 'description' => 'Highest tier; full departmental spread plus specialist and referral-receiving capacity.'],
            ['target_type_code' => 'SCHOOL', 'category_code' => 'PRIMARY_SCHOOL', 'category_name' => 'Primary School', 'description' => 'Nursery/primary-level school.'],
            ['target_type_code' => 'SCHOOL', 'category_code' => 'SECONDARY_SCHOOL', 'category_name' => 'Secondary School', 'description' => 'Junior/senior secondary-level school.'],
            ['target_type_code' => 'COMMUNITY', 'category_code' => 'GENERAL_COMMUNITY', 'category_name' => 'General Community', 'description' => 'A community or catchment area assessed as its own entity, not tiered by size or type.'],
        ]);
    }

    private function seedDomains(): void
    {
        DB::table('domains')->insertOrIgnore([
            ['domain_code' => 'WE', 'domain_name' => 'Workflow Efficiency', 'is_operational' => true, 'display_order' => 1],
            ['domain_code' => 'DB', 'domain_name' => 'Documentation Burden', 'is_operational' => true, 'display_order' => 2],
            ['domain_code' => 'RB', 'domain_name' => 'Reporting Burden', 'is_operational' => true, 'display_order' => 3],
            ['domain_code' => 'DQ', 'domain_name' => 'Data Quality', 'is_operational' => true, 'display_order' => 4],
            ['domain_code' => 'DR', 'domain_name' => 'Digital Readiness', 'is_operational' => true, 'display_order' => 5],
            ['domain_code' => 'OP', 'domain_name' => 'Operational Pain', 'is_operational' => true, 'display_order' => 6],
            ['domain_code' => 'DI', 'domain_name' => 'Decision Intelligence', 'is_operational' => true, 'display_order' => 7],
            ['domain_code' => 'CQ', 'domain_name' => 'Clinical & Service Quality', 'is_operational' => false, 'display_order' => 8],
        ]);
    }

    private function seedDomainWeights(): void
    {
        $operationalDomainIds = DB::table('domains')->where('is_operational', true)->pluck('domain_id');
        foreach ($operationalDomainIds as $domainId) {
            DB::table('domain_weights')->insertOrIgnore([
                'domain_id' => $domainId,
                'weight' => 0.143,
                'updated_at' => now(),
            ]);
        }
    }

    private function seedMaturityLevels(): void
    {
        DB::table('maturity_levels')->insertOrIgnore([
            ['level_number' => 1, 'level_name' => 'Data Collection', 'description' => 'Collects routine data but rarely analyzes or uses it.', 'min_score' => 0, 'max_score' => 20],
            ['level_number' => 2, 'level_name' => 'Data Reporting', 'description' => 'Submits reports consistently with limited internal use.', 'min_score' => 20, 'max_score' => 40],
            ['level_number' => 3, 'level_name' => 'Data Analysis', 'description' => 'Reviews and interprets data for selected activities.', 'min_score' => 40, 'max_score' => 60],
            ['level_number' => 4, 'level_name' => 'Data-Driven Management', 'description' => 'Uses data regularly to guide operational and clinical decisions.', 'min_score' => 60, 'max_score' => 80],
            ['level_number' => 5, 'level_name' => 'Learning Health System', 'description' => 'Continuously improves through data, feedback, and innovation.', 'min_score' => 80, 'max_score' => 100],
        ]);
    }

    private function seedAssessmentTiers(): void
    {
        DB::table('assessment_tiers')->insertOrIgnore([
            ['tier_code' => 'TIER_1', 'tier_name' => 'Core — Self-Administered Questionnaire'],
            ['tier_code' => 'TIER_2', 'tier_name' => 'Enhanced — Assessor-Led Observation & Time-Motion'],
        ]);
    }

    private function seedQuestionTypes(): void
    {
        $types = ['SINGLE_SELECT', 'MULTI_SELECT', 'NUMERIC', 'LIKERT', 'TIME_ESTIMATE', 'RANKING', 'OPEN_ENDED', 'FIVE_WHYS', 'JTBD', 'OBSERVATION'];
        foreach ($types as $code) {
            DB::table('question_types')->insertOrIgnore(['type_code' => $code]);
        }
    }

    private function seedStandardsRegistry(): void
    {
        DB::table('standards_registry')->insertOrIgnore([
            ['standard_code' => 'WHO_SARA', 'standard_name' => 'Service Availability and Readiness Assessment', 'issuing_body' => 'World Health Organization', 'description' => 'Overall facility service-readiness assessment methodology.', 'reference_url' => null],
            ['standard_code' => 'WHO_MHGAP', 'standard_name' => 'Mental Health Gap Action Programme (mhGAP-IG)', 'issuing_body' => 'World Health Organization', 'description' => 'Standardized primary-care mental health screening and management guide.', 'reference_url' => null],
            ['standard_code' => 'WHO_SRQ20', 'standard_name' => 'Self-Reporting Questionnaire (SRQ-20)', 'issuing_body' => 'World Health Organization', 'description' => 'Validated 20-item screening tool for common mental disorders.', 'reference_url' => null],
            ['standard_code' => 'WHO_EPI', 'standard_name' => 'Expanded Programme on Immunization Indicators', 'issuing_body' => 'WHO / UNICEF', 'description' => 'Standard indicator set for immunization coverage, cold chain, and vaccine management.', 'reference_url' => null],
            ['standard_code' => 'WHO_IPC', 'standard_name' => 'Infection Prevention and Control Core Components', 'issuing_body' => 'World Health Organization', 'description' => 'Minimum requirements and core components for facility-level infection prevention and control.', 'reference_url' => null],
            ['standard_code' => 'WHO_CMAM', 'standard_name' => 'Community-Based Management of Acute Malnutrition', 'issuing_body' => 'WHO / UNICEF', 'description' => 'Protocol for SAM/MAM screening, classification (MUAC), and outpatient/inpatient management.', 'reference_url' => null],
            ['standard_code' => 'WHO_ANC2016', 'standard_name' => 'WHO Recommendations on Antenatal Care', 'issuing_body' => 'World Health Organization', 'description' => 'Standard ANC contact schedule and content guidance.', 'reference_url' => null],
            ['standard_code' => 'WHO_SAFE_CHILDBIRTH', 'standard_name' => 'WHO Safe Childbirth Checklist', 'issuing_body' => 'World Health Organization', 'description' => 'Standardized checklist for essential birth practices.', 'reference_url' => null],
            ['standard_code' => 'UNAIDS_95_95_95', 'standard_name' => 'UNAIDS 95-95-95 Targets', 'issuing_body' => 'UNAIDS', 'description' => 'Global HIV testing, treatment, and viral suppression cascade targets.', 'reference_url' => null],
            ['standard_code' => 'WHO_PCC', 'standard_name' => 'WHO People-Centred Care Framework', 'issuing_body' => 'World Health Organization', 'description' => 'Framework and indicators for assessing care from the patient/service-user experience perspective.', 'reference_url' => null],
            ['standard_code' => 'COMMUNITY_SCORE_CARD', 'standard_name' => 'Community Score Card', 'issuing_body' => 'CARE International / adapted by WHO & partners', 'description' => 'Participatory community-level social accountability and perception assessment methodology.', 'reference_url' => null],
            ['standard_code' => 'WB_SDI', 'standard_name' => 'World Bank Service Delivery Indicators — Client Satisfaction Module', 'issuing_body' => 'World Bank', 'description' => 'Standardized client/patient satisfaction and experience module.', 'reference_url' => null],
        ]);
    }

    private function seedTopics(): void
    {
        DB::table('topics')->insertOrIgnore([
            ['topic_code' => 'HYGIENE', 'topic_name' => 'Hygiene & Infection Prevention', 'description' => 'Cross-cutting assessment of hand hygiene, sterilization, waste management, and IPC practice.', 'score_acronym' => 'HIPS', 'score_full_name' => 'Hygiene & Infection Prevention Score', 'source' => 'CURATED', 'review_status' => 'PUBLISHED'],
            ['topic_code' => 'MALARIA', 'topic_name' => 'Malaria Case Management', 'description' => 'Diagnosis, treatment protocol adherence, and surveillance reporting for malaria services.', 'score_acronym' => 'MCMS', 'score_full_name' => 'Malaria Case Management Score', 'source' => 'CURATED', 'review_status' => 'PUBLISHED'],
            ['topic_code' => 'HIV', 'topic_name' => 'HIV Service Continuum', 'description' => 'HIV counselling, testing, PMTCT, ART/viral load, and reporting.', 'score_acronym' => 'HSCS', 'score_full_name' => 'HIV Service Continuum Score', 'source' => 'CURATED', 'review_status' => 'PUBLISHED'],
            ['topic_code' => 'MENTAL_HEALTH', 'topic_name' => 'Mental Health Services', 'description' => 'Mental health screening, documentation, medication availability, and follow-up.', 'score_acronym' => 'MHSS', 'score_full_name' => 'Mental Health Service Score', 'source' => 'CURATED', 'review_status' => 'PUBLISHED'],
            ['topic_code' => 'MATERNAL_HEALTH', 'topic_name' => 'Maternal & Reproductive Health', 'description' => 'The full maternal continuum — Antenatal Care, Labour & Delivery, Postnatal Care — plus Family Planning.', 'score_acronym' => 'MRHS', 'score_full_name' => 'Maternal & Reproductive Health Score', 'source' => 'CURATED', 'review_status' => 'PUBLISHED'],
            ['topic_code' => 'PATIENT_VOICE', 'topic_name' => 'Patient Exit Experience', 'description' => 'Optional module — short exit micro-survey capturing the patient\'s own experience of a specific visit.', 'score_acronym' => 'PVES', 'score_full_name' => 'Patient Voice Experience Score', 'source' => 'CURATED', 'review_status' => 'PUBLISHED'],
            ['topic_code' => 'COMMUNITY_VOICE', 'topic_name' => 'Community Perception', 'description' => 'Optional module — broader community-level perception of trust, awareness, and access.', 'score_acronym' => 'CPS', 'score_full_name' => 'Community Perception Score', 'source' => 'CURATED', 'review_status' => 'PUBLISHED'],
        ]);
    }

    private function seedAssessmentModules(): void
    {
        DB::table('assessment_modules')->insertOrIgnore([
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'OPD', 'module_name' => 'Outpatient Department', 'primary_respondent' => 'Nurse in Charge · Triage Officer · Medical Officer', 'estimated_duration_minutes' => 23, 'data_collection_methods' => 'Interview · Observation · Time-Motion'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'ANC', 'module_name' => 'Antenatal Care', 'primary_respondent' => 'Midwife · Nurse · CHEW · CHO', 'estimated_duration_minutes' => 20, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'LBD', 'module_name' => 'Labour & Delivery', 'primary_respondent' => 'Midwife · Nurse-Midwife · Medical Officer', 'estimated_duration_minutes' => 20, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'PNC', 'module_name' => 'Postnatal Care', 'primary_respondent' => 'Midwife · Nurse · CHEW', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'IMM', 'module_name' => 'Child Health & Immunization', 'primary_respondent' => 'RI Focal Person · Nurse · CHEW', 'estimated_duration_minutes' => 20, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'FP', 'module_name' => 'Family Planning', 'primary_respondent' => 'Nurse · CHEW · CHO', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'LAB', 'module_name' => 'Laboratory', 'primary_respondent' => 'Medical Laboratory Scientist · Laboratory Technician', 'estimated_duration_minutes' => 20, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'PHM', 'module_name' => 'Pharmacy', 'primary_respondent' => 'Pharmacist · Pharmacy Technician', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'IPD', 'module_name' => 'In-Patient Ward', 'primary_respondent' => 'Ward Nurse · Medical Officer', 'estimated_duration_minutes' => 20, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'REF', 'module_name' => 'Referral Management', 'primary_respondent' => 'Records Officer · Nurse in Charge', 'estimated_duration_minutes' => 22, 'data_collection_methods' => 'Interview · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'REC', 'module_name' => 'Records & Health Information Management', 'primary_respondent' => 'Records Officer · HIM Officer', 'estimated_duration_minutes' => 20, 'data_collection_methods' => 'Interview · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'HTB', 'module_name' => 'HIV · TB · PMTCT Services', 'primary_respondent' => 'HTB Focal Person · Nurse', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'COM', 'module_name' => 'Community Health & Outreach', 'primary_respondent' => 'CHEW · CHO · Outreach Coordinator', 'estimated_duration_minutes' => 20, 'data_collection_methods' => 'Interview · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'NUT', 'module_name' => 'Nutrition Services', 'primary_respondent' => 'Nutrition Officer · CHEW', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'THR', 'module_name' => 'Theatre & Surgical Services', 'primary_respondent' => 'Theatre Nurse · Anaesthetic Nurse · Surgeon', 'estimated_duration_minutes' => 28, 'data_collection_methods' => 'Interview · Observation · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'RAD', 'module_name' => 'Radiology & Imaging', 'primary_respondent' => 'Radiographer · Radiologist', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Observation · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'BLB', 'module_name' => 'Blood Bank & Transfusion Services', 'primary_respondent' => 'Laboratory Scientist · Blood Bank Officer', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Observation · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'EMR', 'module_name' => 'Emergency & Accident Unit', 'primary_respondent' => 'Emergency Nurse · Medical Officer on Call', 'estimated_duration_minutes' => 23, 'data_collection_methods' => 'Interview · Observation · Time-Motion'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'ICU', 'module_name' => 'Intensive & High Dependency Care', 'primary_respondent' => 'ICU Nurse · Consultant · Anaesthetist', 'estimated_duration_minutes' => 23, 'data_collection_methods' => 'Interview · Observation · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'FIN', 'module_name' => 'Finance, Billing & Insurance Claims', 'primary_respondent' => 'Accounts Officer · Records Officer', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'HRM', 'module_name' => 'Human Resource Management', 'primary_respondent' => 'Admin Officer · Facility Manager · HR Officer', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Document Review'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'INF', 'module_name' => 'Infrastructure, Utilities & Infection Prevention', 'primary_respondent' => 'Facility Manager · Maintenance Officer · IPC Focal Person', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => 'MNH', 'module_name' => 'Mental Health Services', 'primary_respondent' => 'CHEW · Mental Health Officer · Psychiatric Nurse', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Document Review'],
            ['target_type_code' => 'SCHOOL', 'module_code' => 'WASH', 'module_name' => 'Water, Sanitation & Hygiene Facilities', 'primary_respondent' => 'Head Teacher · School Health Focal Teacher', 'estimated_duration_minutes' => 20, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'SCHOOL', 'module_code' => 'HYGED', 'module_name' => 'Hygiene Education & Practice', 'primary_respondent' => 'Class Teacher · School Health Focal Teacher', 'estimated_duration_minutes' => 18, 'data_collection_methods' => 'Interview · Observation'],
            ['target_type_code' => 'SCHOOL', 'module_code' => 'MHM', 'module_name' => 'Menstrual Health Management', 'primary_respondent' => 'School Health Focal Teacher · Female Staff Member', 'estimated_duration_minutes' => 15, 'data_collection_methods' => 'Interview'],
            ['target_type_code' => 'COMMUNITY', 'module_code' => 'HIVAW', 'module_name' => 'HIV Awareness & Service Uptake', 'primary_respondent' => 'Community Member', 'estimated_duration_minutes' => 12, 'data_collection_methods' => 'Interview'],
        ]);
    }

    private function seedRespondentRoles(): void
    {
        DB::table('respondent_roles')->insertOrIgnore([
            ['target_type_code' => 'HEALTH_FACILITY', 'role_code' => 'STAFF', 'role_name' => 'Facility Staff', 'is_internal' => true],
            ['target_type_code' => 'HEALTH_FACILITY', 'role_code' => 'PATIENT', 'role_name' => 'Patient', 'is_internal' => false],
            ['target_type_code' => 'HEALTH_FACILITY', 'role_code' => 'COMMUNITY_MEMBER', 'role_name' => 'Community Member', 'is_internal' => false],
            ['target_type_code' => 'SCHOOL', 'role_code' => 'TEACHER', 'role_name' => 'Teacher / School Staff', 'is_internal' => true],
            ['target_type_code' => 'SCHOOL', 'role_code' => 'STUDENT', 'role_name' => 'Student', 'is_internal' => false],
            ['target_type_code' => 'SCHOOL', 'role_code' => 'PARENT', 'role_name' => 'Parent / Guardian', 'is_internal' => false],
            ['target_type_code' => 'COMMUNITY', 'role_code' => 'COMMUNITY_LEADER', 'role_name' => 'Community Leader', 'is_internal' => true],
            ['target_type_code' => 'COMMUNITY', 'role_code' => 'COMMUNITY_MEMBER', 'role_name' => 'Community Member', 'is_internal' => false],
        ]);
    }

    private function seedTargetCategoryDefaultModules(): void
    {
        $phcId = DB::table('target_categories')->where('category_code', 'PHC')->value('category_id');
        $generalId = DB::table('target_categories')->where('category_code', 'GENERAL_HOSPITAL')->value('category_id');
        $referralId = DB::table('target_categories')->where('category_code', 'REFERRAL_HOSPITAL')->value('category_id');
        $primarySchoolId = DB::table('target_categories')->where('category_code', 'PRIMARY_SCHOOL')->value('category_id');
        $secondarySchoolId = DB::table('target_categories')->where('category_code', 'SECONDARY_SCHOOL')->value('category_id');
        $generalCommunityId = DB::table('target_categories')->where('category_code', 'GENERAL_COMMUNITY')->value('category_id');

        $phcModules = DB::table('assessment_modules')
            ->where('target_type_code', 'HEALTH_FACILITY')
            ->whereIn('module_code', ['OPD', 'ANC', 'LBD', 'PNC', 'IMM', 'FP', 'LAB', 'PHM', 'IPD', 'REF', 'REC', 'HTB', 'COM', 'NUT'])
            ->pluck('module_id');

        $generalModules = DB::table('assessment_modules')
            ->where('target_type_code', 'HEALTH_FACILITY')
            ->whereIn('module_code', ['OPD', 'ANC', 'LBD', 'PNC', 'IMM', 'FP', 'LAB', 'PHM', 'IPD', 'REF', 'REC', 'HTB', 'COM', 'NUT', 'THR', 'RAD', 'BLB', 'EMR', 'FIN', 'HRM', 'INF'])
            ->pluck('module_id');

        $referralModules = DB::table('assessment_modules')
            ->where('target_type_code', 'HEALTH_FACILITY')
            ->whereIn('module_code', ['OPD', 'ANC', 'LBD', 'PNC', 'IMM', 'FP', 'LAB', 'PHM', 'IPD', 'REF', 'REC', 'HTB', 'COM', 'NUT', 'THR', 'RAD', 'BLB', 'EMR', 'ICU', 'FIN', 'HRM', 'INF', 'MNH'])
            ->pluck('module_id');

        $schoolModules = DB::table('assessment_modules')
            ->where('target_type_code', 'SCHOOL')
            ->whereIn('module_code', ['WASH', 'HYGED', 'MHM'])
            ->pluck('module_id');

        $hivawModuleId = DB::table('assessment_modules')
            ->where('target_type_code', 'COMMUNITY')
            ->where('module_code', 'HIVAW')
            ->value('module_id');

        foreach ($phcModules as $moduleId) {
            DB::table('target_category_default_modules')->insertOrIgnore(['category_id' => $phcId, 'module_id' => $moduleId, 'is_default' => true]);
        }
        foreach ($generalModules as $moduleId) {
            DB::table('target_category_default_modules')->insertOrIgnore(['category_id' => $generalId, 'module_id' => $moduleId, 'is_default' => true]);
        }
        foreach ($referralModules as $moduleId) {
            DB::table('target_category_default_modules')->insertOrIgnore(['category_id' => $referralId, 'module_id' => $moduleId, 'is_default' => true]);
        }
        foreach ($schoolModules as $moduleId) {
            DB::table('target_category_default_modules')->insertOrIgnore(['category_id' => $primarySchoolId, 'module_id' => $moduleId, 'is_default' => true]);
            DB::table('target_category_default_modules')->insertOrIgnore(['category_id' => $secondarySchoolId, 'module_id' => $moduleId, 'is_default' => true]);
        }
        if ($hivawModuleId) {
            DB::table('target_category_default_modules')->insertOrIgnore(['category_id' => $generalCommunityId, 'module_id' => $hivawModuleId, 'is_default' => true]);
        }
    }
}
