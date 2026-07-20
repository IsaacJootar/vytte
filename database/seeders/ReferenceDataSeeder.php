<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTargetTypes();
        $this->seedDomains();
        $this->seedMaturityLevels();
        $this->seedAssessmentTiers();
        $this->seedQuestionTypes();
        $this->seedStandardsRegistry();
        $this->seedTopics();
        $this->seedAssessmentModules();
        $this->seedRespondentRoles();
        $this->seedHealthTaxonomy();
    }

    private function seedTargetTypes(): void
    {
        DB::table('target_types')->insertOrIgnore([
            ['target_type_code' => 'HEALTH_FACILITY', 'target_type_name' => 'Health Facility', 'description' => 'Hospitals, health centres, and clinics — the fully built-out launch scope.'],
            ['target_type_code' => 'SCHOOL', 'target_type_name' => 'School', 'description' => 'Primary and secondary schools — e.g. a WASH/hygiene assessment across a set of schools.'],
            ['target_type_code' => 'COMMUNITY', 'target_type_name' => 'Community', 'description' => 'A community or catchment area assessed as its own entity, not tied to one facility.'],
            ['target_type_code' => 'WATER_POINT', 'target_type_name' => 'Water Point', 'description' => 'A borehole, well, or piped water source — roadmap target type, not yet built out.'],
            ['target_type_code' => 'CORRECTIONAL', 'target_type_name' => 'Correctional Facility', 'description' => 'A prison, detention centre, or other correctional setting.'],
            ['target_type_code' => 'WORKPLACE', 'target_type_name' => 'Workplace or Business', 'description' => 'A business, company, factory, or other workplace.'],
            ['target_type_code' => 'PLACE_OF_WORSHIP', 'target_type_name' => 'Place of Worship', 'description' => 'A church, mosque, temple, or other faith community setting.'],
            ['target_type_code' => 'NGO_PROGRAMME', 'target_type_name' => 'NGO or Programme', 'description' => 'A non-governmental organization or health programme.'],
            ['target_type_code' => 'GOVERNMENT_ORG', 'target_type_name' => 'Government Organization', 'description' => 'A ministry, department, agency, or government office.'],
            ['target_type_code' => 'CUSTOM', 'target_type_name' => 'Custom Setting', 'description' => 'A user-defined setting not covered by the standard list.'],
        ]);
    }

    private function seedDomains(): void
    {
        $domains = [
            ['domain_code' => 'GOV', 'domain_name' => 'Governance and Accountability', 'display_order' => 1],
            ['domain_code' => 'WORK', 'domain_name' => 'Workforce and Capability', 'display_order' => 2],
            ['domain_code' => 'SERV', 'domain_name' => 'Service Delivery and Access', 'display_order' => 3],
            ['domain_code' => 'SAFE', 'domain_name' => 'Safety and Quality', 'display_order' => 4],
            ['domain_code' => 'RES', 'domain_name' => 'Infrastructure, Equipment and Supplies', 'display_order' => 5],
            ['domain_code' => 'INFO', 'domain_name' => 'Information, Measurement and Learning', 'display_order' => 6],
            ['domain_code' => 'PCOM', 'domain_name' => 'Person-Centredness and Community Responsiveness', 'display_order' => 7],
            // Financing completes the WHO health system building blocks. Without it a
            // financing weakness cannot roll up and be compared across programmes the way
            // a workforce weakness can. Added to the master list here; it only takes
            // effect once a taxonomy version containing it is published, so nothing that
            // is currently scored changes.
            ['domain_code' => 'FIN', 'domain_name' => 'Financing and Resource Management', 'display_order' => 8],
        ];

        foreach ($domains as $domain) {
            DB::table('domains')->updateOrInsert(
                ['domain_code' => $domain['domain_code']],
                [
                    'domain_name' => $domain['domain_name'],
                    'is_operational' => false,
                    'display_order' => $domain['display_order'],
                ]
            );
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

    private function seedHealthTaxonomy(): void
    {
        DB::table('setting_types')->insertOrIgnore([
            ['setting_type_code' => 'HEALTH_FACILITY', 'setting_type_name' => 'Health Facility', 'uses_departments' => true, 'display_order' => 1],
            ['setting_type_code' => 'SCHOOL', 'setting_type_name' => 'School', 'uses_departments' => false, 'display_order' => 2],
            ['setting_type_code' => 'COMMUNITY', 'setting_type_name' => 'Community', 'uses_departments' => false, 'display_order' => 3],
            ['setting_type_code' => 'CORRECTIONAL', 'setting_type_name' => 'Correctional Facility', 'uses_departments' => false, 'display_order' => 4],
            ['setting_type_code' => 'WORKPLACE', 'setting_type_name' => 'Workplace or Business', 'uses_departments' => false, 'display_order' => 5],
            ['setting_type_code' => 'PLACE_OF_WORSHIP', 'setting_type_name' => 'Place of Worship', 'uses_departments' => false, 'display_order' => 6],
            ['setting_type_code' => 'NGO_PROGRAMME', 'setting_type_name' => 'NGO or Programme', 'uses_departments' => false, 'display_order' => 7],
            ['setting_type_code' => 'GOVERNMENT_ORG', 'setting_type_name' => 'Government Organization', 'uses_departments' => false, 'display_order' => 8],
            ['setting_type_code' => 'WATER_POINT', 'setting_type_name' => 'Water Point', 'uses_departments' => false, 'display_order' => 9],
            ['setting_type_code' => 'CUSTOM', 'setting_type_name' => 'Custom Setting', 'uses_departments' => false, 'display_order' => 10],
        ]);

        foreach (DB::table('target_types')->pluck('target_type_code') as $targetTypeCode) {
            DB::table('target_type_setting_map')->insertOrIgnore([
                'target_type_code' => $targetTypeCode,
                'setting_type_code' => $targetTypeCode,
            ]);
        }

        // Health domains are subjects, never purposes. Each one here is a subject that is
        // routinely assessed on its own somewhere in the world, which is the test for
        // whether it deserves to be a domain rather than an area beneath one.
        $domains = [
            // Cross-cutting and facility-wide
            ['GENERAL_HEALTH_SYSTEMS', 'General Health Systems'],
            ['PATIENT_EXPERIENCE', 'Patient Experience'],
            ['INFECTION_PREVENTION', 'Infection Prevention and Control'],
            ['WASH', 'Water, Sanitation and Hygiene'],
            ['ANTIMICROBIAL_RESISTANCE', 'Antimicrobial Resistance'],
            ['HEALTH_INFORMATION_SYSTEMS', 'Health Information and Data Systems'],
            ['HEALTH_PROMOTION', 'Health Promotion and Education'],
            ['COMMUNITY_HEALTH', 'Community Health Services'],
            ['ENVIRONMENTAL_HEALTH', 'Environmental Health and Climate Resilience'],
            ['OCCUPATIONAL_HEALTH', 'Occupational Health and Safety'],
            ['DISABILITY_INCLUSION', 'Disability and Inclusion'],

            // Population groups
            ['MATERNAL_HEALTH', 'Maternal Health'],
            ['CHILD_HEALTH', 'Child Health'],
            ['ADOLESCENT_HEALTH', 'Adolescent and Youth Health'],
            ['OLDER_PEOPLE_HEALTH', 'Older People and Geriatric Care'],

            // Communicable disease and programmes
            ['HIV', 'HIV'],
            ['TUBERCULOSIS', 'Tuberculosis'],
            ['MALARIA', 'Malaria'],
            ['NEGLECTED_TROPICAL_DISEASES', 'Neglected Tropical Diseases'],
            ['IMMUNIZATION', 'Immunization'],
            ['OUTBREAK_RESPONSE', 'Epidemic and Outbreak Response'],

            // Non-communicable and mental health
            ['NON_COMMUNICABLE_DISEASES', 'Non-Communicable Diseases'],
            ['MENTAL_HEALTH', 'Mental Health'],
            ['NUTRITION', 'Nutrition'],

            // Sexual and reproductive health
            ['FAMILY_PLANNING', 'Family Planning'],
            ['SEXUAL_REPRODUCTIVE_HEALTH', 'Sexual and Reproductive Health'],

            // Clinical services assessed in their own right
            ['LABORATORY', 'Laboratory Services'],
            ['PHARMACY', 'Pharmacy and Medical Supplies'],
            ['EMERGENCY_CARE', 'Emergency and Critical Care'],
            ['SURGICAL_CARE', 'Surgical and Anaesthesia Care'],
            ['BLOOD_SERVICES', 'Blood and Transfusion Services'],
            ['DIAGNOSTIC_IMAGING', 'Diagnostic Imaging'],
            ['REHABILITATION', 'Rehabilitation Services'],
            ['PALLIATIVE_CARE', 'Palliative and End-of-Life Care'],
            ['ORAL_HEALTH', 'Oral Health'],
            ['EYE_HEALTH', 'Eye Health'],
        ];

        foreach ($domains as $order => [$code, $name]) {
            DB::table('health_domains')->insertOrIgnore([
                'domain_code' => $code,
                'domain_name' => $name,
                'display_order' => $order + 1,
            ]);
        }

        $moduleMappings = [
            'MNH' => ['MENTAL_HEALTH'],
            'HTB' => ['HIV', 'TUBERCULOSIS'],
            'WASH' => ['WASH'],
            'HYGED' => ['WASH', 'INFECTION_PREVENTION'],
            'NUT' => ['NUTRITION'],
            'FP' => ['FAMILY_PLANNING'],
            'IMM' => ['IMMUNIZATION', 'CHILD_HEALTH'],
            'INF' => ['INFECTION_PREVENTION', 'WASH'],
            'ANC' => ['MATERNAL_HEALTH'],
            'LBD' => ['MATERNAL_HEALTH'],
            'PNC' => ['MATERNAL_HEALTH'],
        ];

        foreach ($moduleMappings as $moduleCode => $domainCodes) {
            $moduleIds = DB::table('assessment_modules')->where('module_code', $moduleCode)->pluck('module_id');
            foreach ($moduleIds as $moduleId) {
                foreach ($domainCodes as $index => $domainCode) {
                    $domainId = DB::table('health_domains')->where('domain_code', $domainCode)->value('health_domain_id');
                    DB::table('assessment_module_health_domain')->insertOrIgnore([
                        'module_id' => $moduleId,
                        'health_domain_id' => $domainId,
                        'is_primary' => $index === 0,
                    ]);
                }
            }
        }
    }
}
