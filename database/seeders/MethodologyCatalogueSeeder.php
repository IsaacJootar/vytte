<?php

namespace Database\Seeders;

use App\Models\AnalysisLens;
use App\Models\AssessmentObjective;
use App\Models\AssessmentTemplate;
use App\Models\HealthArea;
use App\Models\HealthDomain;
use App\Models\InsightCategory;
use App\Models\MethodologyVersion;
use App\Models\ObjectivePreset;
use App\Models\ObjectiveRecommendation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The official Vytte health knowledge library.
 *
 * Curated against recognised international health assessment practice — WHO Service
 * Availability and Readiness Assessment (SARA), Service Provision Assessment (SPA), the
 * Harmonized Health Facility Assessment (HHFA), the WHO health system building blocks,
 * WHO/UNICEF WASH FIT, IPC minimum requirements, and the common programme review and
 * supportive supervision patterns used across Nigeria, Ghana, Kenya and South Africa.
 *
 * Deliberately generic where national standards differ. Vytte supplies the structure and
 * vocabulary; a workspace supplies its own thresholds and content. Nothing here encodes
 * one country's regulations as though it were universal.
 *
 * This seeder is NOT part of the default seed. It is run explicitly, and only after the
 * methodology catalogue has been approved.
 */
class MethodologyCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $version = MethodologyVersion::firstOrCreate(
                ['version_number' => 1],
                [
                    'status' => MethodologyVersion::STATUS_DRAFT,
                    'methodology_notes' => 'Initial official Vytte health knowledge library.',
                ]
            );

            if (! $version->isEditable()) {
                $this->command?->warn('Methodology version 1 is published. Nothing was changed.');

                return;
            }

            $this->seedObjectives($version);
            $this->seedHealthAreas($version);
            $this->seedAnalysisLenses($version);
            $this->seedInsightCategories($version);
            $this->seedTemplates($version);
            $this->seedObjectiveRecommendations($version);
            $this->seedPresets($version);
            $this->pruneRemovedEntries($version);
        });
    }

    /**
     * Removes entries that are no longer in the catalogue.
     *
     * Without this the seeder can only ever add. An entry removed from the catalogue, or
     * moved from one health domain to another under a new code, would linger in the
     * database forever and still appear to administrators — so the seeded methodology
     * would silently stop matching this file.
     *
     * Only ever runs against a draft version. A published methodology is immutable, and
     * the guard in run() has already returned by this point if it is published.
     */
    private function pruneRemovedEntries(MethodologyVersion $version): void
    {
        $versionId = $version->methodology_version_id;

        $expectedAreas = collect(self::healthAreas())->flatten(1)->pluck('code');
        HealthArea::where('methodology_version_id', $versionId)
            ->whereNotIn('area_code', $expectedAreas)->delete();

        AssessmentObjective::where('methodology_version_id', $versionId)
            ->whereNotIn('objective_code', collect(self::objectives())->pluck('code'))->delete();

        AnalysisLens::where('methodology_version_id', $versionId)
            ->whereNotIn('lens_code', collect(self::analysisLenses())->pluck('code'))->delete();

        InsightCategory::where('methodology_version_id', $versionId)
            ->whereNotIn('category_code', collect(self::insightCategories())->pluck('code'))->delete();

        AssessmentTemplate::where('methodology_version_id', $versionId)
            ->whereNotIn('template_code', collect(self::templates())->pluck('code'))->delete();
    }

    // ── Part 1: objectives are purposes, never subjects ──────────────────────

    /**
     * @return array<int, array{code: string, name: string, group: string, question: string, description: string}>
     */
    public static function objectives(): array
    {
        return [
            // Why you are assessing
            ['code' => 'BASELINE', 'name' => 'Baseline', 'group' => 'LIFECYCLE', 'question' => 'Where are we starting from?', 'description' => 'Establish the starting position before an intervention or programme begins, so later change can be measured against something real.'],
            ['code' => 'MIDLINE', 'name' => 'Midline', 'group' => 'LIFECYCLE', 'question' => 'Are we on track?', 'description' => 'Measure progress partway through a programme while there is still time to correct course.'],
            ['code' => 'ENDLINE', 'name' => 'Endline', 'group' => 'LIFECYCLE', 'question' => 'What changed?', 'description' => 'Measure the position at the end of a programme and compare it against the baseline.'],
            ['code' => 'MONITORING', 'name' => 'Routine Monitoring', 'group' => 'LIFECYCLE', 'question' => 'How are we doing right now?', 'description' => 'Repeated measurement on a regular cycle to watch performance over time rather than at a single moment.'],
            ['code' => 'PROGRAMME_EVALUATION', 'name' => 'Programme Evaluation', 'group' => 'LIFECYCLE', 'question' => 'Did this programme work?', 'description' => 'Judge whether a programme achieved what it set out to achieve, and what can be learned from it.'],
            ['code' => 'RESEARCH', 'name' => 'Research Study', 'group' => 'PURPOSE', 'question' => 'What does the evidence show?', 'description' => 'Structured data collection for a study, where method consistency and reproducibility matter more than operational speed.'],
            ['code' => 'SITUATION_ANALYSIS', 'name' => 'Situation Analysis', 'group' => 'PURPOSE', 'question' => 'What is actually going on here?', 'description' => 'A broad first look at a facility, district or system before deciding what to prioritise.'],
            ['code' => 'NEEDS_ASSESSMENT', 'name' => 'Needs Assessment', 'group' => 'PURPOSE', 'question' => 'What is missing?', 'description' => 'Identify gaps between what is available and what is required, to direct investment or support.'],

            // Compliance and accreditation
            ['code' => 'REGULATORY_COMPLIANCE', 'name' => 'Regulatory Compliance', 'group' => 'ASSURANCE', 'question' => 'Do we meet the rules?', 'description' => 'Check performance against the regulations a facility is legally required to meet.'],
            ['code' => 'ACCREDITATION', 'name' => 'Accreditation Readiness', 'group' => 'ASSURANCE', 'question' => 'Are we ready to be accredited?', 'description' => 'Prepare for a formal accreditation visit by checking against the accrediting body standards in advance.'],
            ['code' => 'LICENSING', 'name' => 'Licensing Review', 'group' => 'ASSURANCE', 'question' => 'Do we qualify to operate?', 'description' => 'Confirm the conditions attached to a facility operating licence are being met.'],
            ['code' => 'CLINICAL_AUDIT', 'name' => 'Clinical Audit', 'group' => 'ASSURANCE', 'question' => 'Is care meeting the standard?', 'description' => 'Compare clinical practice against an agreed clinical standard and record where it diverges.'],
            ['code' => 'SUPPORTIVE_SUPERVISION', 'name' => 'Supportive Supervision', 'group' => 'IMPROVEMENT', 'question' => 'How can we help this team improve?', 'description' => 'A supervisory visit intended to coach and support rather than to police, producing agreed actions with the team.'],

            // Improving performance
            ['code' => 'QUALITY_IMPROVEMENT', 'name' => 'Quality Improvement', 'group' => 'IMPROVEMENT', 'question' => 'What should we improve first?', 'description' => 'Find and prioritise improvement opportunities, usually feeding a structured improvement cycle.'],
            ['code' => 'PATIENT_SAFETY', 'name' => 'Patient Safety Review', 'group' => 'IMPROVEMENT', 'question' => 'Where could patients be harmed?', 'description' => 'Look specifically for conditions and practices that could cause avoidable harm.'],
            ['code' => 'PERFORMANCE_REVIEW', 'name' => 'Performance Review', 'group' => 'IMPROVEMENT', 'question' => 'How well are we performing?', 'description' => 'Assess performance against targets or peers, typically on a management cycle.'],
            ['code' => 'GAP_ANALYSIS', 'name' => 'Gap Analysis', 'group' => 'IMPROVEMENT', 'question' => 'What stands between us and the standard?', 'description' => 'Measure the distance between current state and a defined target state.'],

            // Health system function
            ['code' => 'OPERATIONAL_READINESS', 'name' => 'Operational Readiness', 'group' => 'SYSTEM', 'question' => 'Can we deliver services today?', 'description' => 'Whether the staff, supplies, equipment, infrastructure and systems needed to deliver services are actually in place and working.'],
            ['code' => 'SERVICE_AVAILABILITY', 'name' => 'Service Availability', 'group' => 'SYSTEM', 'question' => 'What services do we actually offer?', 'description' => 'Which services are offered, when, and to whom, as distinct from whether they are ready to deliver.'],
            ['code' => 'EMERGENCY_PREPAREDNESS', 'name' => 'Emergency Preparedness', 'group' => 'SYSTEM', 'question' => 'Could we cope with a surge or an outbreak?', 'description' => 'Readiness to respond to emergencies, outbreaks and sudden increases in demand.'],
            ['code' => 'EQUITY_ACCESS', 'name' => 'Equity and Access', 'group' => 'SYSTEM', 'question' => 'Who is being left out?', 'description' => 'Whether services reach all groups, including those furthest from care.'],

            // Deliberately absent: Health Workforce, Leadership and Governance, Health
            // Financing, Health Information, Infrastructure, Supply Chain, Community
            // Engagement, Digital Health and Health Promotion.
            //
            // Each names a subject or a measurement dimension rather than a purpose, and
            // each already exists as a health domain or a measurement domain. Carrying
            // them here too would recreate exactly the collision that keeping Malaria out
            // of this list avoids: a user facing the same concept in two places with no
            // way to tell which one to pick.
            //
            // "Assess our workforce" is reached instead through a purpose — Situation
            // Analysis or Gap Analysis — narrowed by the Workforce measurement domain,
            // and the familiar entry point is preserved as an objective preset.

            // Assessment types performed routinely worldwide that the first catalogue missed.
            ['code' => 'DATA_QUALITY', 'name' => 'Data Quality Assessment', 'group' => 'ASSURANCE', 'question' => 'Can we believe the numbers we report?', 'description' => 'Verify that reported data matches source records and is complete, accurate and timely. Distinct from Health Information Systems, which asks whether systems exist rather than whether the data they produce is true.'],
            ['code' => 'TRAINING_NEEDS', 'name' => 'Training and Capacity Needs', 'group' => 'IMPROVEMENT', 'question' => 'What do our people need to learn?', 'description' => 'Identify skill and knowledge gaps to direct training, mentorship and supervision.'],
            ['code' => 'RBF_VERIFICATION', 'name' => 'Results-Based Financing Verification', 'group' => 'ASSURANCE', 'question' => 'Did the reported results actually happen?', 'description' => 'Independent verification of reported performance where payment depends on results.'],
            ['code' => 'OUTBREAK_RESPONSE_REVIEW', 'name' => 'Outbreak Response Review', 'group' => 'LIFECYCLE', 'question' => 'How well did we respond?', 'description' => 'Review detection, response and containment during or after an outbreak.'],
            ['code' => 'SERVICE_EXPANSION', 'name' => 'Service Expansion Readiness', 'group' => 'SYSTEM', 'question' => 'Can we take on a new service?', 'description' => 'Whether a facility is ready to introduce or scale up a service it does not currently provide.'],
            ['code' => 'EFFICIENCY_REVIEW', 'name' => 'Efficiency and Value Review', 'group' => 'IMPROVEMENT', 'question' => 'Are we getting value for what we spend?', 'description' => 'Relate what is achieved to what it costs, in money, staff time or supplies.'],
            ['code' => 'SUSTAINABILITY_REVIEW', 'name' => 'Sustainability Review', 'group' => 'LIFECYCLE', 'question' => 'Would this continue without external support?', 'description' => 'Whether a programme could be maintained if current funding, staffing or partner support ended.'],
            ['code' => 'PATIENT_SATISFACTION', 'name' => 'Patient Satisfaction Survey', 'group' => 'PURPOSE', 'question' => 'What do patients think of us?', 'description' => 'Collect experience and satisfaction directly from patients, usually with many respondents.'],
        ];
    }

    // ── Part 2: health areas beneath existing health domains ─────────────────

    /**
     * Keyed by the existing `health_domains.domain_code`.
     *
     * @return array<string, array<int, array{code: string, name: string}>>
     */
    public static function healthAreas(): array
    {
        return [
            'MATERNAL_HEALTH' => [
                ['code' => 'ANC', 'name' => 'Antenatal Care'],
                ['code' => 'LABOUR_DELIVERY', 'name' => 'Labour and Delivery'],
                ['code' => 'EMONC', 'name' => 'Emergency Obstetric and Newborn Care'],
                ['code' => 'PNC', 'name' => 'Postnatal Care'],
                ['code' => 'MATERNAL_DEATH_REVIEW', 'name' => 'Maternal Death Surveillance and Response'],
            ],
            'CHILD_HEALTH' => [
                ['code' => 'NEWBORN_CARE', 'name' => 'Newborn Care'],
                ['code' => 'IMCI', 'name' => 'Integrated Management of Childhood Illness'],
                ['code' => 'CHILD_NUTRITION', 'name' => 'Child Nutrition and Growth Monitoring'],
                ['code' => 'ADOLESCENT_HEALTH', 'name' => 'Adolescent Health'],
            ],
            'HIV' => [
                ['code' => 'HIV_TESTING', 'name' => 'HIV Testing Services'],
                ['code' => 'ART', 'name' => 'Antiretroviral Treatment'],
                ['code' => 'PMTCT', 'name' => 'Prevention of Mother-to-Child Transmission'],
                ['code' => 'HIV_PREVENTION', 'name' => 'HIV Prevention'],
                ['code' => 'HIV_RETENTION', 'name' => 'Retention and Adherence Support'],
            ],
            'TUBERCULOSIS' => [
                ['code' => 'TB_SCREENING', 'name' => 'TB Screening and Case Finding'],
                ['code' => 'TB_DIAGNOSIS', 'name' => 'TB Diagnosis'],
                ['code' => 'TB_TREATMENT', 'name' => 'TB Treatment and Follow-up'],
                ['code' => 'DRTB', 'name' => 'Drug-Resistant TB'],
                ['code' => 'TB_INFECTION_CONTROL', 'name' => 'TB Infection Control'],
            ],
            'IMMUNIZATION' => [
                ['code' => 'ROUTINE_IMMUNIZATION', 'name' => 'Routine Immunization'],
                ['code' => 'COLD_CHAIN', 'name' => 'Cold Chain and Vaccine Management'],
                ['code' => 'IMMUNIZATION_OUTREACH', 'name' => 'Outreach and Campaigns'],
                ['code' => 'AEFI', 'name' => 'Adverse Events Following Immunization'],
            ],
            'NUTRITION' => [
                ['code' => 'ACUTE_MALNUTRITION', 'name' => 'Management of Acute Malnutrition'],
                ['code' => 'IYCF', 'name' => 'Infant and Young Child Feeding'],
                ['code' => 'MICRONUTRIENT', 'name' => 'Micronutrient Supplementation'],
            ],
            'WASH' => [
                ['code' => 'WATER_SUPPLY', 'name' => 'Water Supply'],
                ['code' => 'SANITATION', 'name' => 'Sanitation and Toilets'],
                ['code' => 'HYGIENE', 'name' => 'Hand Hygiene'],
                ['code' => 'WASTE_MANAGEMENT', 'name' => 'Health Care Waste Management'],
                ['code' => 'ENVIRONMENTAL_CLEANING', 'name' => 'Environmental Cleaning'],
            ],
            'INFECTION_PREVENTION' => [
                ['code' => 'IPC_PROGRAMME', 'name' => 'IPC Programme and Guidelines'],
                ['code' => 'IPC_TRAINING', 'name' => 'IPC Training and Education'],
                ['code' => 'IPC_SURVEILLANCE', 'name' => 'Healthcare-Associated Infection Surveillance'],
                ['code' => 'STERILIZATION', 'name' => 'Sterilization and Decontamination'],
                ['code' => 'PPE', 'name' => 'Personal Protective Equipment'],
            ],
            'MENTAL_HEALTH' => [
                ['code' => 'MH_SCREENING', 'name' => 'Mental Health Screening'],
                ['code' => 'MH_TREATMENT', 'name' => 'Treatment and Follow-up'],
                ['code' => 'MH_REFERRAL', 'name' => 'Referral and Specialist Access'],
                ['code' => 'SUBSTANCE_USE', 'name' => 'Substance Use Services'],
            ],
            'FAMILY_PLANNING' => [
                ['code' => 'FP_COUNSELLING', 'name' => 'Family Planning Counselling'],
                ['code' => 'FP_METHODS', 'name' => 'Contraceptive Method Availability'],
                ['code' => 'SRH', 'name' => 'Sexual and Reproductive Health'],
            ],
            'PATIENT_EXPERIENCE' => [
                ['code' => 'WAITING_ACCESS', 'name' => 'Waiting Times and Access'],
                ['code' => 'RESPECTFUL_CARE', 'name' => 'Respectful and Dignified Care'],
                ['code' => 'PATIENT_FEEDBACK', 'name' => 'Patient Feedback and Complaints'],
                ['code' => 'PATIENT_RIGHTS', 'name' => 'Patient Rights and Consent'],
            ],
            // Kept deliberately small. Everything that is routinely assessed on its own
            // has been promoted to a health domain, so this no longer absorbs subjects
            // that deserve to be first class.
            'GENERAL_HEALTH_SYSTEMS' => [
                ['code' => 'OPD', 'name' => 'Outpatient Services'],
                ['code' => 'IPD', 'name' => 'Inpatient Services'],
                ['code' => 'REFERRAL', 'name' => 'Referral and Transport'],
                ['code' => 'PATIENT_RECORDS', 'name' => 'Patient Records'],
            ],

            'MALARIA' => [
                ['code' => 'MALARIA_DIAGNOSIS', 'name' => 'Malaria Testing and Diagnosis'],
                ['code' => 'MALARIA_TREATMENT', 'name' => 'Malaria Case Management'],
                ['code' => 'MALARIA_PREVENTION', 'name' => 'Prevention, Nets and Spraying'],
                ['code' => 'MALARIA_IN_PREGNANCY', 'name' => 'Malaria in Pregnancy'],
                ['code' => 'SEVERE_MALARIA', 'name' => 'Severe Malaria Management'],
                ['code' => 'MALARIA_COMMODITIES', 'name' => 'Malaria Commodities and Stock'],
            ],
            'NON_COMMUNICABLE_DISEASES' => [
                ['code' => 'HYPERTENSION', 'name' => 'Hypertension and Cardiovascular Care'],
                ['code' => 'DIABETES', 'name' => 'Diabetes Care'],
                ['code' => 'CANCER', 'name' => 'Cancer Screening and Care'],
                ['code' => 'RESPIRATORY_NCD', 'name' => 'Chronic Respiratory Disease'],
                ['code' => 'NCD_SCREENING', 'name' => 'NCD Screening and Risk Assessment'],
                ['code' => 'NCD_MEDICINES', 'name' => 'NCD Medicines and Continuity'],
            ],
            'NEGLECTED_TROPICAL_DISEASES' => [
                ['code' => 'NTD_CASE_MANAGEMENT', 'name' => 'NTD Case Management'],
                ['code' => 'MASS_DRUG_ADMINISTRATION', 'name' => 'Mass Drug Administration'],
                ['code' => 'NTD_SURVEILLANCE', 'name' => 'NTD Surveillance and Mapping'],
            ],
            'ANTIMICROBIAL_RESISTANCE' => [
                ['code' => 'ANTIBIOTIC_STEWARDSHIP', 'name' => 'Antimicrobial Stewardship'],
                ['code' => 'AMR_SURVEILLANCE', 'name' => 'Resistance Surveillance'],
                ['code' => 'PRESCRIBING_PRACTICE', 'name' => 'Prescribing Practice'],
            ],
            'OUTBREAK_RESPONSE' => [
                ['code' => 'OUTBREAK_DETECTION', 'name' => 'Detection and Early Warning'],
                ['code' => 'OUTBREAK_PREPAREDNESS', 'name' => 'Preparedness and Planning'],
                ['code' => 'CASE_ISOLATION', 'name' => 'Isolation and Case Management'],
                ['code' => 'CONTACT_TRACING', 'name' => 'Contact Tracing'],
                ['code' => 'RISK_COMMUNICATION', 'name' => 'Risk Communication'],
            ],

            'LABORATORY' => [
                ['code' => 'LAB_TESTING_CAPACITY', 'name' => 'Testing Capacity and Menu'],
                ['code' => 'LAB_QUALITY_ASSURANCE', 'name' => 'Quality Assurance and Controls'],
                ['code' => 'LAB_BIOSAFETY', 'name' => 'Biosafety and Specimen Handling'],
                ['code' => 'LAB_EQUIPMENT', 'name' => 'Equipment and Maintenance'],
                ['code' => 'LAB_TURNAROUND', 'name' => 'Turnaround and Result Reporting'],
                ['code' => 'SAMPLE_TRANSPORT', 'name' => 'Sample Referral and Transport'],
            ],
            'PHARMACY' => [
                ['code' => 'MEDICINE_AVAILABILITY', 'name' => 'Essential Medicine Availability'],
                ['code' => 'MEDICINE_STORAGE', 'name' => 'Storage Conditions'],
                ['code' => 'STOCK_MANAGEMENT', 'name' => 'Stock Management and Forecasting'],
                ['code' => 'DISPENSING_PRACTICE', 'name' => 'Dispensing Practice'],
                ['code' => 'PHARMACOVIGILANCE', 'name' => 'Pharmacovigilance'],
                ['code' => 'SUPPLY_CHAIN', 'name' => 'Supply Chain and Distribution'],
            ],
            'EMERGENCY_CARE' => [
                ['code' => 'TRIAGE', 'name' => 'Triage'],
                ['code' => 'RESUSCITATION', 'name' => 'Resuscitation Capability'],
                ['code' => 'TRAUMA_CARE', 'name' => 'Trauma Care'],
                ['code' => 'CRITICAL_CARE', 'name' => 'Critical and Intensive Care'],
                ['code' => 'OXYGEN_SUPPLY', 'name' => 'Oxygen Supply'],
                ['code' => 'AMBULANCE', 'name' => 'Ambulance and Pre-hospital Care'],
            ],
            'SURGICAL_CARE' => [
                ['code' => 'SURGICAL_CAPACITY', 'name' => 'Surgical Capacity and Theatre'],
                ['code' => 'ANAESTHESIA', 'name' => 'Anaesthesia Services'],
                ['code' => 'SURGICAL_SAFETY', 'name' => 'Surgical Safety Checklist'],
                ['code' => 'POST_OPERATIVE', 'name' => 'Post-operative Care'],
                ['code' => 'STERILE_PROCESSING', 'name' => 'Sterile Processing'],
            ],
            'BLOOD_SERVICES' => [
                ['code' => 'BLOOD_AVAILABILITY', 'name' => 'Blood Availability'],
                ['code' => 'BLOOD_SCREENING', 'name' => 'Donor Screening and Testing'],
                ['code' => 'TRANSFUSION_SAFETY', 'name' => 'Transfusion Safety'],
                ['code' => 'BLOOD_COLD_CHAIN', 'name' => 'Blood Storage and Cold Chain'],
            ],
            'DIAGNOSTIC_IMAGING' => [
                ['code' => 'IMAGING_CAPACITY', 'name' => 'Imaging Capacity'],
                ['code' => 'RADIATION_SAFETY', 'name' => 'Radiation Safety'],
                ['code' => 'IMAGING_REPORTING', 'name' => 'Reporting and Interpretation'],
            ],
            'REHABILITATION' => [
                ['code' => 'PHYSIOTHERAPY', 'name' => 'Physiotherapy'],
                ['code' => 'ASSISTIVE_DEVICES', 'name' => 'Assistive Products and Devices'],
                ['code' => 'REHAB_REFERRAL', 'name' => 'Rehabilitation Referral'],
            ],
            'PALLIATIVE_CARE' => [
                ['code' => 'PAIN_MANAGEMENT', 'name' => 'Pain Management'],
                ['code' => 'END_OF_LIFE', 'name' => 'End-of-Life Care'],
                ['code' => 'PALLIATIVE_MEDICINES', 'name' => 'Access to Palliative Medicines'],
            ],
            'ORAL_HEALTH' => [
                ['code' => 'DENTAL_SERVICES', 'name' => 'Dental Services'],
                ['code' => 'ORAL_PREVENTION', 'name' => 'Oral Health Prevention'],
            ],
            'EYE_HEALTH' => [
                ['code' => 'EYE_SCREENING', 'name' => 'Vision Screening'],
                ['code' => 'CATARACT_SERVICES', 'name' => 'Cataract and Surgical Eye Care'],
                ['code' => 'REFRACTIVE_SERVICES', 'name' => 'Refractive Services and Spectacles'],
            ],

            'SEXUAL_REPRODUCTIVE_HEALTH' => [
                ['code' => 'STI_SERVICES', 'name' => 'Sexually Transmitted Infection Services'],
                ['code' => 'CERVICAL_SCREENING', 'name' => 'Cervical Cancer Screening'],
                ['code' => 'GBV_SERVICES', 'name' => 'Gender-Based Violence Services'],
                ['code' => 'SAFE_ABORTION_CARE', 'name' => 'Post-Abortion and Safe Care'],
            ],
            'ADOLESCENT_HEALTH' => [
                ['code' => 'ADOLESCENT_FRIENDLY', 'name' => 'Adolescent-Friendly Services'],
                ['code' => 'ADOLESCENT_SRH', 'name' => 'Adolescent Sexual and Reproductive Health'],
                ['code' => 'ADOLESCENT_MENTAL_HEALTH', 'name' => 'Adolescent Mental Health'],
            ],
            'OLDER_PEOPLE_HEALTH' => [
                ['code' => 'GERIATRIC_ASSESSMENT', 'name' => 'Geriatric Assessment'],
                ['code' => 'CHRONIC_CARE_ELDERLY', 'name' => 'Chronic Care for Older People'],
                ['code' => 'FALLS_PREVENTION', 'name' => 'Falls Prevention'],
            ],
            'DISABILITY_INCLUSION' => [
                ['code' => 'PHYSICAL_ACCESSIBILITY', 'name' => 'Physical Accessibility'],
                ['code' => 'COMMUNICATION_ACCESS', 'name' => 'Communication Accessibility'],
                ['code' => 'INCLUSIVE_SERVICE_DESIGN', 'name' => 'Inclusive Service Design'],
            ],

            'COMMUNITY_HEALTH' => [
                ['code' => 'CHW_PROGRAMME', 'name' => 'Community Health Worker Programme'],
                ['code' => 'OUTREACH_SERVICES', 'name' => 'Outreach Services'],
                ['code' => 'COMMUNITY_REFERRAL', 'name' => 'Community Referral Links'],
                ['code' => 'COMMUNITY_STRUCTURES', 'name' => 'Community Governance Structures'],
            ],
            'HEALTH_INFORMATION_SYSTEMS' => [
                ['code' => 'DATA_RECORDING', 'name' => 'Recording and Registers'],
                ['code' => 'DATA_REPORTING', 'name' => 'Reporting and Timeliness'],
                ['code' => 'DATA_QUALITY', 'name' => 'Data Quality and Verification'],
                ['code' => 'DATA_USE', 'name' => 'Data Use for Decisions'],
                ['code' => 'DIGITAL_SYSTEMS', 'name' => 'Digital Systems and Connectivity'],
            ],
            'HEALTH_PROMOTION' => [
                ['code' => 'HEALTH_EDUCATION', 'name' => 'Health Education'],
                ['code' => 'BEHAVIOUR_CHANGE', 'name' => 'Behaviour Change Communication'],
                ['code' => 'SCREENING_PROMOTION', 'name' => 'Screening Promotion and Uptake'],
            ],
            'ENVIRONMENTAL_HEALTH' => [
                ['code' => 'CLIMATE_RESILIENCE', 'name' => 'Climate Resilience'],
                ['code' => 'ENERGY_SUPPLY', 'name' => 'Energy and Power Supply'],
                ['code' => 'AIR_QUALITY', 'name' => 'Air Quality and Ventilation'],
                ['code' => 'VECTOR_CONTROL', 'name' => 'Vector Control'],
            ],
            'OCCUPATIONAL_HEALTH' => [
                ['code' => 'STAFF_SAFETY', 'name' => 'Staff Safety and Injury Prevention'],
                ['code' => 'STAFF_IMMUNIZATION', 'name' => 'Staff Immunization'],
                ['code' => 'STAFF_WELLBEING', 'name' => 'Staff Wellbeing and Mental Health'],
            ],
        ];
    }

    // ── Part 5: analysis lenses ──────────────────────────────────────────────

    /**
     * @return array<int, array{code: string, name: string, question: string, description: string}>
     */
    public static function analysisLenses(): array
    {
        return [
            ['code' => 'PERFORMANCE', 'name' => 'Performance', 'question' => 'How well is this performing?', 'description' => 'Reads the results as achievement against expectation, highlighting strongest and weakest scoring areas.'],
            ['code' => 'RISK', 'name' => 'Risk', 'question' => 'What could go wrong?', 'description' => 'Reads the same results for exposure, leading with critical failures and pain points rather than averages.'],
            ['code' => 'COMPLIANCE', 'name' => 'Compliance', 'question' => 'Where do we fall short of the standard?', 'description' => 'Reads the results strictly against required standards, where partial credit matters less than whether a requirement is met.'],
            ['code' => 'QUALITY', 'name' => 'Quality', 'question' => 'Is the care good?', 'description' => 'Focuses on clinical quality and the reliability of care processes.'],
            ['code' => 'CAPACITY', 'name' => 'Capacity', 'question' => 'Do we have what it takes?', 'description' => 'Focuses on staff, skills, equipment, supplies and infrastructure as enablers rather than outcomes.'],
            ['code' => 'PATIENT_SAFETY', 'name' => 'Patient Safety', 'question' => 'Where could a patient be harmed?', 'description' => 'Surfaces conditions associated with avoidable harm, regardless of overall score.'],
            ['code' => 'OPERATIONS', 'name' => 'Operations', 'question' => 'Is the day-to-day working?', 'description' => 'Focuses on flow, availability, timeliness and the practical running of services.'],
            ['code' => 'CLINICAL_GOVERNANCE', 'name' => 'Clinical Governance', 'question' => 'Is clinical practice properly overseen?', 'description' => 'Focuses on protocols, supervision, audit and accountability for clinical care.'],
            ['code' => 'PROGRAMME_EFFECTIVENESS', 'name' => 'Programme Effectiveness', 'question' => 'Is the programme achieving its aim?', 'description' => 'Reads results against the intent of a specific programme rather than the facility as a whole.'],
            ['code' => 'PUBLIC_HEALTH', 'name' => 'Public Health', 'question' => 'What does this mean for the population?', 'description' => 'Reads results at population rather than facility level, including coverage and reach.'],
            ['code' => 'EQUITY', 'name' => 'Equity', 'question' => 'Who is being left behind?', 'description' => 'Looks for differences in access and quality between groups and locations.'],
            ['code' => 'TREND', 'name' => 'Trend', 'question' => 'Is this getting better or worse?', 'description' => 'Compares against previous assessments of the same target. Requires at least two completed assessments.'],
            ['code' => 'BENCHMARK', 'name' => 'Benchmarking', 'question' => 'How do we compare with others?', 'description' => 'Compares against peer results. Requires a peer set and is meaningless with a single assessment.'],
            ['code' => 'PROGRESS', 'name' => 'Progress Tracking', 'question' => 'Are the agreed actions being done?', 'description' => 'Reads results against previously agreed actions rather than against the standard.'],
            ['code' => 'ROOT_CAUSE', 'name' => 'Root Cause', 'question' => 'Why is this happening?', 'description' => 'Groups related weaknesses to point at an underlying cause rather than listing symptoms separately.'],
            ['code' => 'PRIORITY_ACTION', 'name' => 'Priority Actions', 'question' => 'What should we do first?', 'description' => 'Orders findings by a combination of severity and feasibility to produce a short, actionable list.'],
            ['code' => 'EXECUTIVE', 'name' => 'Executive Summary', 'question' => 'What does leadership need to know?', 'description' => 'The shortest defensible account for someone who will not read the detail.'],
            ['code' => 'EFFICIENCY', 'name' => 'Efficiency and Value', 'question' => 'Are we getting value for what we spend?', 'description' => 'Relates what is achieved to what it costs in money, staff time or supplies. A different question from performance: a high score bought expensively is not the same as a high score.'],
            ['code' => 'SUSTAINABILITY', 'name' => 'Sustainability', 'question' => 'Would this survive without external support?', 'description' => 'Reads results for dependence on funding, partners or individuals who may not remain. Standard in programme evaluation.'],
            ['code' => 'DATA_CONFIDENCE', 'name' => 'Data Confidence', 'question' => 'How much of this can we actually rely on?', 'description' => 'Reads the assessment for how well evidenced it is, using calibration state and missing evidence. Answers a question about the assessment itself rather than about the facility.'],
        ];
    }

    // ── Part 6: insight categories ───────────────────────────────────────────

    /**
     * @return array<int, array{code: string, name: string, polarity: string, diagnostic: bool, description: string}>
     */
    public static function insightCategories(): array
    {
        return [
            ['code' => 'STRENGTH', 'name' => 'Strengths', 'polarity' => 'POSITIVE', 'diagnostic' => false, 'description' => 'Things being done well that should be protected and, where possible, spread.'],
            ['code' => 'ACHIEVEMENT', 'name' => 'Achievements', 'polarity' => 'POSITIVE', 'diagnostic' => false, 'description' => 'Improvements made since a previous assessment.'],
            ['code' => 'HIGH_PERFORMING', 'name' => 'High-Performing Areas', 'polarity' => 'POSITIVE', 'diagnostic' => false, 'description' => 'Areas scoring well above the rest, which may hold transferable practice.'],
            ['code' => 'WEAKNESS', 'name' => 'Weaknesses', 'polarity' => 'NEGATIVE', 'diagnostic' => false, 'description' => 'Areas performing below expectation.'],
            ['code' => 'GAP', 'name' => 'Gaps', 'polarity' => 'NEGATIVE', 'diagnostic' => false, 'description' => 'Something required that is absent altogether, as distinct from present but weak.'],
            ['code' => 'LOW_PERFORMING', 'name' => 'Low-Performing Areas', 'polarity' => 'NEGATIVE', 'diagnostic' => false, 'description' => 'Areas scoring well below the rest.'],
            ['code' => 'PAIN_POINT', 'name' => 'Pain Points', 'polarity' => 'NEGATIVE', 'diagnostic' => true, 'description' => 'A specific answer flagged as a known problem signal, not merely a low score. Vytte already records these at option level, which makes them traceable to an exact response rather than inferred from an average.'],
            ['code' => 'CRITICAL_FINDING', 'name' => 'Critical Findings', 'polarity' => 'NEGATIVE', 'diagnostic' => true, 'description' => 'A finding serious enough to warrant attention regardless of the overall score, including critical failures recorded during scoring.'],
            ['code' => 'CLINICAL_RISK', 'name' => 'Clinical Risks', 'polarity' => 'NEGATIVE', 'diagnostic' => true, 'description' => 'Conditions that could lead to harm in the course of clinical care.'],
            ['code' => 'OPERATIONAL_RISK', 'name' => 'Operational Risks', 'polarity' => 'NEGATIVE', 'diagnostic' => true, 'description' => 'Conditions that could interrupt or degrade service delivery.'],
            ['code' => 'COMPLIANCE_RISK', 'name' => 'Compliance Risks', 'polarity' => 'NEGATIVE', 'diagnostic' => true, 'description' => 'Conditions that could result in regulatory or accreditation failure.'],
            ['code' => 'EMERGING_ISSUE', 'name' => 'Emerging Issues', 'polarity' => 'NEGATIVE', 'diagnostic' => true, 'description' => 'Something deteriorating across assessments that is not yet a failure.'],
            ['code' => 'OPPORTUNITY', 'name' => 'Opportunities', 'polarity' => 'NEUTRAL', 'diagnostic' => false, 'description' => 'Something that could be improved with reasonable effort, without being a failure today.'],
            ['code' => 'QUICK_WIN', 'name' => 'Quick Wins', 'polarity' => 'NEUTRAL', 'diagnostic' => false, 'description' => 'Improvements achievable quickly with low effort or cost.'],
            ['code' => 'STRATEGIC_PRIORITY', 'name' => 'Strategic Priorities', 'polarity' => 'NEUTRAL', 'diagnostic' => false, 'description' => 'Substantial changes that need planning, budget or time, and will not be resolved in one cycle.'],

            // The honesty categories. Every other category describes what the data says;
            // these say when the data cannot support a conclusion. Without them an
            // assessment that is largely unanswered still produces a confident-looking
            // report, which quietly undermines the credibility of everything else in it.
            ['code' => 'DATA_GAP', 'name' => 'Data Gaps', 'polarity' => 'NEUTRAL', 'diagnostic' => true, 'description' => 'Areas where too little was answered to draw a conclusion. The platform already records this as a calibration state on every score; this surfaces it as a finding so a thin section is never mistaken for a good one.'],
            ['code' => 'INSUFFICIENT_EVIDENCE', 'name' => 'Insufficient Evidence', 'polarity' => 'NEUTRAL', 'diagnostic' => true, 'description' => 'A claim was made in an answer but the evidence expected to support it was not provided. The finding stands, but it rests on assertion alone.'],
            ['code' => 'NO_CHANGE', 'name' => 'No Change', 'polarity' => 'NEUTRAL', 'diagnostic' => false, 'description' => 'An area that has neither improved nor declined since the previous assessment. Worth stating explicitly, because silence reads as progress.'],
            ['code' => 'DECLINE', 'name' => 'Deterioration', 'polarity' => 'NEGATIVE', 'diagnostic' => true, 'description' => 'An area that has got worse since the previous assessment, even if it still scores acceptably.'],
            ['code' => 'SYSTEMIC_ISSUE', 'name' => 'Systemic Issues', 'polarity' => 'NEGATIVE', 'diagnostic' => true, 'description' => 'A weakness appearing across several areas at once, which usually means one underlying cause rather than several separate problems.'],
            ['code' => 'GOOD_PRACTICE', 'name' => 'Good Practice to Share', 'polarity' => 'POSITIVE', 'diagnostic' => false, 'description' => 'Something done notably well that other sites or teams could copy.'],
        ];
    }

    // ── Part 3: official template catalogue ──────────────────────────────────

    /**
     * @return array<int, array{code: string, name: string, scope: string, target: ?string, minutes: ?int, description: string}>
     */
    public static function templates(): array
    {
        return [
            ['code' => 'HOSPITAL_READINESS', 'name' => 'Hospital Operational Readiness', 'scope' => 'ENTERPRISE', 'target' => 'HEALTH_FACILITY', 'minutes' => 240, 'description' => 'Whole-hospital readiness across departments, staffing, supplies, infrastructure and systems.'],
            ['code' => 'PHC_ASSESSMENT', 'name' => 'Primary Healthcare Facility Assessment', 'scope' => 'ENTERPRISE', 'target' => 'HEALTH_FACILITY', 'minutes' => 150, 'description' => 'General assessment of a primary healthcare centre across the services it is expected to provide.'],
            ['code' => 'HEALTH_FACILITY_GENERAL', 'name' => 'General Health Facility Assessment', 'scope' => 'ENTERPRISE', 'target' => 'HEALTH_FACILITY', 'minutes' => 180, 'description' => 'Broad facility assessment suitable where no more specific template applies.'],
            ['code' => 'DISTRICT_REVIEW', 'name' => 'District Health System Review', 'scope' => 'ENTERPRISE', 'target' => null, 'minutes' => 300, 'description' => 'Assessment across several facilities in a district, focused on system function rather than a single site.'],
            ['code' => 'ACCREDITATION_READINESS', 'name' => 'Accreditation Readiness Review', 'scope' => 'ENTERPRISE', 'target' => 'HEALTH_FACILITY', 'minutes' => 240, 'description' => 'Structured preparation for a formal accreditation visit.'],
            ['code' => 'SUPPORTIVE_SUPERVISION_VISIT', 'name' => 'Supportive Supervision Visit', 'scope' => 'ENTERPRISE', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'A shorter supervisory visit designed to coach a team and agree actions.'],
            ['code' => 'IPC_ASSESSMENT', 'name' => 'Infection Prevention and Control Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'IPC programme, practice, supplies and surveillance.'],
            ['code' => 'WASH_ASSESSMENT', 'name' => 'WASH in Health Care Facilities', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Water, sanitation, hygiene, cleaning and waste management.'],
            ['code' => 'LABORATORY_ASSESSMENT', 'name' => 'Laboratory Services Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Laboratory capacity, quality, safety and turnaround.'],
            ['code' => 'PHARMACY_ASSESSMENT', 'name' => 'Pharmacy and Medicines Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 75, 'description' => 'Medicine availability, storage, dispensing and stock management.'],
            ['code' => 'MATERNAL_NEWBORN', 'name' => 'Maternal and Newborn Care Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 120, 'description' => 'Antenatal, delivery, emergency obstetric and newborn care.'],
            ['code' => 'MALARIA_PROGRAMME', 'name' => 'Malaria Programme Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 75, 'description' => 'Malaria diagnosis, treatment, commodities and reporting.'],
            ['code' => 'HIV_PROGRAMME', 'name' => 'HIV Programme Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Testing, treatment, prevention and retention.'],
            ['code' => 'TB_PROGRAMME', 'name' => 'TB Programme Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Case finding, diagnosis, treatment and infection control.'],
            ['code' => 'IMMUNIZATION_PROGRAMME', 'name' => 'Immunization Programme Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 75, 'description' => 'Routine immunization, cold chain and outreach.'],
            ['code' => 'NUTRITION_PROGRAMME', 'name' => 'Nutrition Programme Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 75, 'description' => 'Malnutrition management, feeding practice and supplementation.'],
            ['code' => 'MENTAL_HEALTH_SERVICES', 'name' => 'Mental Health Services Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 75, 'description' => 'Screening, treatment, referral and workforce for mental health.'],
            ['code' => 'EMERGENCY_CARE_ASSESSMENT', 'name' => 'Emergency Care Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Emergency unit readiness, triage, and trauma capability.'],
            ['code' => 'PATIENT_EXPERIENCE_SURVEY', 'name' => 'Patient Experience Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 30, 'description' => 'Experience of care from the patient perspective, usually multi-respondent.'],
            ['code' => 'COMMUNITY_OUTREACH', 'name' => 'Community Health and Outreach Assessment', 'scope' => 'FOCUSED', 'target' => null, 'minutes' => 60, 'description' => 'Community health services, outreach and engagement structures.'],
            ['code' => 'DIGITAL_READINESS', 'name' => 'Digital Health Readiness Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 60, 'description' => 'Connectivity, devices, systems, skills and digital governance.'],
            ['code' => 'RESEARCH_INSTRUMENT', 'name' => 'Research Data Collection Instrument', 'scope' => 'FOCUSED', 'target' => null, 'minutes' => null, 'description' => 'A blank structured instrument for a study, where the researcher supplies the content.'],

            // Templates for the subjects promoted to health domains, plus the assessment
            // types added to the objective catalogue.
            ['code' => 'DATA_QUALITY_ASSESSMENT', 'name' => 'Data Quality Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Verify reported data against source records for completeness, accuracy and timeliness.'],
            ['code' => 'NCD_SERVICES', 'name' => 'Non-Communicable Disease Services', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Hypertension, diabetes, cancer screening and chronic care continuity.'],
            ['code' => 'SURGICAL_CARE_ASSESSMENT', 'name' => 'Surgical and Anaesthesia Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Theatre capacity, anaesthesia, surgical safety and post-operative care.'],
            ['code' => 'BLOOD_SERVICES_ASSESSMENT', 'name' => 'Blood and Transfusion Services Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 60, 'description' => 'Availability, donor screening, transfusion safety and cold chain.'],
            ['code' => 'AMR_ASSESSMENT', 'name' => 'Antimicrobial Resistance and Stewardship', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 60, 'description' => 'Stewardship, prescribing practice and resistance surveillance.'],
            ['code' => 'OUTBREAK_READINESS', 'name' => 'Outbreak Preparedness and Response', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Detection, isolation, contact tracing and risk communication.'],
            ['code' => 'SRH_ASSESSMENT', 'name' => 'Sexual and Reproductive Health Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 75, 'description' => 'STI services, cervical screening, gender-based violence care and family planning links.'],
            ['code' => 'ADOLESCENT_SERVICES', 'name' => 'Adolescent Health Services Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 60, 'description' => 'Whether services are accessible and acceptable to young people.'],
            ['code' => 'REHABILITATION_ASSESSMENT', 'name' => 'Rehabilitation Services Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 60, 'description' => 'Physiotherapy, assistive products and referral pathways.'],
            ['code' => 'EYE_HEALTH_ASSESSMENT', 'name' => 'Eye Health Services Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 60, 'description' => 'Vision screening, refractive services and surgical eye care.'],
            ['code' => 'ORAL_HEALTH_ASSESSMENT', 'name' => 'Oral Health Services Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 45, 'description' => 'Dental services and oral health prevention.'],
            ['code' => 'DISABILITY_ACCESS_AUDIT', 'name' => 'Disability and Accessibility Audit', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 60, 'description' => 'Physical access, communication access and inclusive service design.'],
            ['code' => 'RBF_VERIFICATION_VISIT', 'name' => 'Results-Based Financing Verification', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 90, 'description' => 'Independent verification of reported results where payment depends on them.'],
            ['code' => 'TRAINING_NEEDS_ASSESSMENT', 'name' => 'Training Needs Assessment', 'scope' => 'FOCUSED', 'target' => null, 'minutes' => 60, 'description' => 'Identify skill and knowledge gaps to direct training and mentorship.'],
            ['code' => 'HMIS_ASSESSMENT', 'name' => 'Health Information Systems Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 75, 'description' => 'Recording, reporting, data use and digital systems.'],
            ['code' => 'CLIMATE_RESILIENCE', 'name' => 'Climate Resilience and Environmental Health', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 75, 'description' => 'Energy, water security, ventilation and resilience to climate shocks.'],
            ['code' => 'OCCUPATIONAL_HEALTH_ASSESSMENT', 'name' => 'Staff Health and Safety Assessment', 'scope' => 'FOCUSED', 'target' => 'HEALTH_FACILITY', 'minutes' => 45, 'description' => 'Staff safety, immunization and wellbeing.'],
            ['code' => 'DISTRICT_PHC_SUPERVISION', 'name' => 'District PHC Supervision Round', 'scope' => 'ENTERPRISE', 'target' => null, 'minutes' => 180, 'description' => 'Supervision across several primary healthcare facilities in one round.'],
        ];
    }

    private function seedObjectives(MethodologyVersion $version): void
    {
        foreach (self::objectives() as $order => $objective) {
            AssessmentObjective::updateOrCreate(
                ['methodology_version_id' => $version->methodology_version_id, 'objective_code' => $objective['code']],
                [
                    'objective_name' => $objective['name'],
                    'objective_group' => $objective['group'],
                    'description' => $objective['description'],
                    'question_it_answers' => $objective['question'],
                    'display_order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedHealthAreas(MethodologyVersion $version): void
    {
        $domains = HealthDomain::pluck('health_domain_id', 'domain_code');
        $order = 0;

        foreach (self::healthAreas() as $domainCode => $areas) {
            $domainId = $domains[$domainCode] ?? null;

            if (! $domainId) {
                $this->command?->warn("Health domain {$domainCode} is missing; its areas were skipped.");

                continue;
            }

            foreach ($areas as $area) {
                HealthArea::updateOrCreate(
                    ['methodology_version_id' => $version->methodology_version_id, 'area_code' => $area['code']],
                    [
                        'health_domain_id' => $domainId,
                        'area_name' => $area['name'],
                        'display_order' => ++$order,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedAnalysisLenses(MethodologyVersion $version): void
    {
        foreach (self::analysisLenses() as $order => $lens) {
            AnalysisLens::updateOrCreate(
                ['methodology_version_id' => $version->methodology_version_id, 'lens_code' => $lens['code']],
                [
                    'lens_name' => $lens['name'],
                    'question_it_answers' => $lens['question'],
                    'description' => $lens['description'],
                    'display_order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedInsightCategories(MethodologyVersion $version): void
    {
        foreach (self::insightCategories() as $order => $category) {
            InsightCategory::updateOrCreate(
                ['methodology_version_id' => $version->methodology_version_id, 'category_code' => $category['code']],
                [
                    'category_name' => $category['name'],
                    'polarity' => $category['polarity'],
                    'description' => $category['description'],
                    'is_diagnostic' => $category['diagnostic'],
                    'display_order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedTemplates(MethodologyVersion $version): void
    {
        foreach (self::templates() as $order => $template) {
            AssessmentTemplate::updateOrCreate(
                ['methodology_version_id' => $version->methodology_version_id, 'template_code' => $template['code']],
                [
                    'template_name' => $template['name'],
                    'description' => $template['description'],
                    'scope_type' => $template['scope'],
                    'target_type_code' => $template['target'],
                    'typical_duration_minutes' => $template['minutes'],
                    'display_order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Part 4. Suggestions only, and deliberately sparse: a recommendation the author
     * disagrees with costs them time, so only confident pairings are recorded.
     */
    private function seedObjectiveRecommendations(MethodologyVersion $version): void
    {
        $map = [
            'OPERATIONAL_READINESS' => [
                'TEMPLATE' => ['HOSPITAL_READINESS', 'PHC_ASSESSMENT'],
                'ANALYSIS_LENS' => ['PERFORMANCE', 'CAPACITY', 'OPERATIONS', 'PRIORITY_ACTION'],
                'MEASUREMENT_DOMAIN' => ['RES', 'WORK', 'SERV'],
            ],
            'REGULATORY_COMPLIANCE' => [
                'ANALYSIS_LENS' => ['COMPLIANCE', 'RISK', 'EXECUTIVE'],
                'MEASUREMENT_DOMAIN' => ['GOV', 'SAFE'],
                'EVIDENCE_TYPE' => ['DOCUMENT', 'OBSERVATION'],
            ],
            'ACCREDITATION' => [
                'TEMPLATE' => ['ACCREDITATION_READINESS'],
                'ANALYSIS_LENS' => ['COMPLIANCE', 'QUALITY', 'PRIORITY_ACTION'],
                'EVIDENCE_TYPE' => ['DOCUMENT', 'OBSERVATION'],
            ],
            'QUALITY_IMPROVEMENT' => [
                'ANALYSIS_LENS' => ['QUALITY', 'ROOT_CAUSE', 'PRIORITY_ACTION', 'TREND'],
                'MEASUREMENT_DOMAIN' => ['SAFE', 'SERV', 'INFO'],
            ],
            'PATIENT_SAFETY' => [
                'ANALYSIS_LENS' => ['PATIENT_SAFETY', 'RISK', 'ROOT_CAUSE'],
                'MEASUREMENT_DOMAIN' => ['SAFE'],
                'HEALTH_DOMAIN' => ['INFECTION_PREVENTION'],
            ],
            'BASELINE' => [
                'ANALYSIS_LENS' => ['PERFORMANCE', 'CAPACITY', 'EXECUTIVE'],
            ],
            'ENDLINE' => [
                'ANALYSIS_LENS' => ['TREND', 'PROGRESS', 'PROGRAMME_EFFECTIVENESS', 'EXECUTIVE'],
            ],
            'MONITORING' => [
                'ANALYSIS_LENS' => ['TREND', 'PERFORMANCE', 'PROGRESS'],
            ],
            'SUPPORTIVE_SUPERVISION' => [
                'TEMPLATE' => ['SUPPORTIVE_SUPERVISION_VISIT'],
                'ANALYSIS_LENS' => ['PROGRESS', 'PRIORITY_ACTION', 'ROOT_CAUSE'],
            ],
            'PROGRAMME_EVALUATION' => [
                'ANALYSIS_LENS' => ['PROGRAMME_EFFECTIVENESS', 'TREND', 'EXECUTIVE'],
            ],
            'EMERGENCY_PREPAREDNESS' => [
                'ANALYSIS_LENS' => ['RISK', 'CAPACITY', 'PRIORITY_ACTION'],
                'TEMPLATE' => ['EMERGENCY_CARE_ASSESSMENT'],
            ],
            'EQUITY_ACCESS' => [
                'ANALYSIS_LENS' => ['EQUITY', 'PUBLIC_HEALTH'],
                'MEASUREMENT_DOMAIN' => ['PCOM', 'SERV'],
            ],
            'RESEARCH' => [
                'TEMPLATE' => ['RESEARCH_INSTRUMENT'],
                'ANALYSIS_LENS' => ['PERFORMANCE', 'BENCHMARK'],
            ],
            'DATA_QUALITY' => [
                'TEMPLATE' => ['DATA_QUALITY_ASSESSMENT'],
                'ANALYSIS_LENS' => ['DATA_CONFIDENCE', 'COMPLIANCE'],
                'HEALTH_DOMAIN' => ['HEALTH_INFORMATION_SYSTEMS'],
                'MEASUREMENT_DOMAIN' => ['INFO'],
                'EVIDENCE_TYPE' => ['DOCUMENT', 'OBSERVATION'],
            ],
            'TRAINING_NEEDS' => [
                'TEMPLATE' => ['TRAINING_NEEDS_ASSESSMENT'],
                'ANALYSIS_LENS' => ['CAPACITY', 'PRIORITY_ACTION'],
                'MEASUREMENT_DOMAIN' => ['WORK'],
            ],
            'RBF_VERIFICATION' => [
                'TEMPLATE' => ['RBF_VERIFICATION_VISIT'],
                'ANALYSIS_LENS' => ['COMPLIANCE', 'DATA_CONFIDENCE'],
                'EVIDENCE_TYPE' => ['DOCUMENT'],
            ],
            'OUTBREAK_RESPONSE_REVIEW' => [
                'TEMPLATE' => ['OUTBREAK_READINESS'],
                'ANALYSIS_LENS' => ['RISK', 'PROGRAMME_EFFECTIVENESS'],
                'HEALTH_DOMAIN' => ['OUTBREAK_RESPONSE'],
            ],
            'SERVICE_EXPANSION' => [
                'ANALYSIS_LENS' => ['CAPACITY', 'PRIORITY_ACTION'],
                'MEASUREMENT_DOMAIN' => ['RES', 'WORK'],
            ],
            'EFFICIENCY_REVIEW' => [
                'ANALYSIS_LENS' => ['EFFICIENCY', 'EXECUTIVE'],
            ],
            'SUSTAINABILITY_REVIEW' => [
                'ANALYSIS_LENS' => ['SUSTAINABILITY', 'PROGRAMME_EFFECTIVENESS', 'EXECUTIVE'],
            ],
            'PATIENT_SATISFACTION' => [
                'TEMPLATE' => ['PATIENT_EXPERIENCE_SURVEY'],
                'ANALYSIS_LENS' => ['QUALITY', 'EQUITY'],
                'HEALTH_DOMAIN' => ['PATIENT_EXPERIENCE'],
                'MEASUREMENT_DOMAIN' => ['PCOM'],
            ],

            // Objectives that were selectable but suggested nothing, so choosing one left
            // the user at a blank page with no hint of where to go next.
            'MIDLINE' => [
                'ANALYSIS_LENS' => ['TREND', 'PROGRESS', 'PERFORMANCE'],
            ],
            'NEEDS_ASSESSMENT' => [
                'ANALYSIS_LENS' => ['CAPACITY', 'PRIORITY_ACTION'],
                'MEASUREMENT_DOMAIN' => ['RES', 'WORK'],
            ],
            'LICENSING' => [
                'ANALYSIS_LENS' => ['COMPLIANCE', 'RISK'],
                'MEASUREMENT_DOMAIN' => ['GOV'],
                'EVIDENCE_TYPE' => ['DOCUMENT'],
            ],
            'CLINICAL_AUDIT' => [
                'ANALYSIS_LENS' => ['QUALITY', 'CLINICAL_GOVERNANCE', 'PATIENT_SAFETY'],
                'MEASUREMENT_DOMAIN' => ['SAFE'],
            ],
            'PERFORMANCE_REVIEW' => [
                'ANALYSIS_LENS' => ['PERFORMANCE', 'BENCHMARK', 'EXECUTIVE'],
            ],
            'GAP_ANALYSIS' => [
                'ANALYSIS_LENS' => ['CAPACITY', 'PRIORITY_ACTION', 'PERFORMANCE'],
            ],
            'SERVICE_AVAILABILITY' => [
                'ANALYSIS_LENS' => ['PERFORMANCE', 'EQUITY', 'PUBLIC_HEALTH'],
                'MEASUREMENT_DOMAIN' => ['SERV'],
            ],
        ];

        $objectives = AssessmentObjective::where('methodology_version_id', $version->methodology_version_id)
            ->pluck('assessment_objective_id', 'objective_code');

        foreach ($map as $objectiveCode => $groups) {
            $objectiveId = $objectives[$objectiveCode] ?? null;

            if (! $objectiveId) {
                continue;
            }

            $order = 0;

            foreach ($groups as $type => $refs) {
                foreach ($refs as $ref) {
                    ObjectiveRecommendation::updateOrCreate(
                        [
                            'assessment_objective_id' => $objectiveId,
                            'recommends_type' => $type,
                            'recommends_ref' => $ref,
                        ],
                        ['display_order' => ++$order]
                    );
                }
            }
        }
    }

    /**
     * Presets exist so a user can start from a familiar name without the subject
     * needing to exist as both an objective and a health domain.
     */
    private function seedPresets(MethodologyVersion $version): void
    {
        $presets = [
            // Malaria is now a health domain in its own right. Before this it pointed at
            // GENERAL_HEALTH_SYSTEMS, which mis-filed every malaria assessment.
            ['code' => 'MALARIA_BASELINE', 'name' => 'Malaria Baseline Assessment', 'objective' => 'BASELINE', 'domains' => ['MALARIA'], 'template' => 'MALARIA_PROGRAMME', 'lenses' => ['PERFORMANCE', 'CAPACITY']],
            ['code' => 'HIV_SUPERVISION', 'name' => 'HIV Supportive Supervision', 'objective' => 'SUPPORTIVE_SUPERVISION', 'domains' => ['HIV'], 'template' => 'HIV_PROGRAMME', 'lenses' => ['PROGRESS', 'PRIORITY_ACTION']],
            ['code' => 'TB_PROGRAMME_REVIEW', 'name' => 'TB Programme Review', 'objective' => 'PROGRAMME_EVALUATION', 'domains' => ['TUBERCULOSIS'], 'template' => 'TB_PROGRAMME', 'lenses' => ['PROGRAMME_EFFECTIVENESS', 'TREND']],
            ['code' => 'HOSPITAL_READINESS_REVIEW', 'name' => 'Hospital Operational Readiness', 'objective' => 'OPERATIONAL_READINESS', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'HOSPITAL_READINESS', 'lenses' => ['PERFORMANCE', 'CAPACITY', 'PRIORITY_ACTION']],
            ['code' => 'PHC_BASELINE', 'name' => 'Primary Healthcare Baseline', 'objective' => 'BASELINE', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'PHC_ASSESSMENT', 'lenses' => ['PERFORMANCE', 'CAPACITY', 'EXECUTIVE']],
            ['code' => 'IPC_COMPLIANCE', 'name' => 'IPC Compliance Review', 'objective' => 'REGULATORY_COMPLIANCE', 'domains' => ['INFECTION_PREVENTION'], 'template' => 'IPC_ASSESSMENT', 'lenses' => ['COMPLIANCE', 'RISK']],
            ['code' => 'WASH_FACILITY', 'name' => 'WASH Facility Assessment', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['WASH'], 'template' => 'WASH_ASSESSMENT', 'lenses' => ['CAPACITY', 'RISK']],
            ['code' => 'MATERNAL_QUALITY', 'name' => 'Maternal and Newborn Quality Review', 'objective' => 'QUALITY_IMPROVEMENT', 'domains' => ['MATERNAL_HEALTH', 'CHILD_HEALTH'], 'template' => 'MATERNAL_NEWBORN', 'lenses' => ['QUALITY', 'PATIENT_SAFETY', 'ROOT_CAUSE']],
            ['code' => 'ACCREDITATION_PREP', 'name' => 'Accreditation Preparation', 'objective' => 'ACCREDITATION', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'ACCREDITATION_READINESS', 'lenses' => ['COMPLIANCE', 'PRIORITY_ACTION']],
            ['code' => 'PATIENT_EXPERIENCE_REVIEW', 'name' => 'Patient Experience Review', 'objective' => 'QUALITY_IMPROVEMENT', 'domains' => ['PATIENT_EXPERIENCE'], 'template' => 'PATIENT_EXPERIENCE_SURVEY', 'lenses' => ['QUALITY', 'EQUITY']],
            ['code' => 'IMMUNIZATION_MONITORING', 'name' => 'Immunization Routine Monitoring', 'objective' => 'MONITORING', 'domains' => ['IMMUNIZATION'], 'template' => 'IMMUNIZATION_PROGRAMME', 'lenses' => ['TREND', 'PERFORMANCE']],
            ['code' => 'NUTRITION_BASELINE', 'name' => 'Nutrition Programme Baseline', 'objective' => 'BASELINE', 'domains' => ['NUTRITION'], 'template' => 'NUTRITION_PROGRAMME', 'lenses' => ['PERFORMANCE', 'CAPACITY']],
            ['code' => 'MENTAL_HEALTH_SITUATION', 'name' => 'Mental Health Situation Analysis', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['MENTAL_HEALTH'], 'template' => 'MENTAL_HEALTH_SERVICES', 'lenses' => ['CAPACITY', 'EQUITY']],
            ['code' => 'LAB_QUALITY', 'name' => 'Laboratory Quality Review', 'objective' => 'QUALITY_IMPROVEMENT', 'domains' => ['LABORATORY'], 'template' => 'LABORATORY_ASSESSMENT', 'lenses' => ['QUALITY', 'CAPACITY']],
            ['code' => 'EMERGENCY_READINESS', 'name' => 'Emergency Care Readiness', 'objective' => 'EMERGENCY_PREPAREDNESS', 'domains' => ['EMERGENCY_CARE'], 'template' => 'EMERGENCY_CARE_ASSESSMENT', 'lenses' => ['RISK', 'CAPACITY']],

            // Starting points for the newly promoted subjects and assessment types.
            ['code' => 'DATA_QUALITY_AUDIT', 'name' => 'Data Quality Assessment', 'objective' => 'DATA_QUALITY', 'domains' => ['HEALTH_INFORMATION_SYSTEMS'], 'template' => 'DATA_QUALITY_ASSESSMENT', 'lenses' => ['DATA_CONFIDENCE', 'COMPLIANCE']],
            ['code' => 'NCD_BASELINE', 'name' => 'NCD Services Baseline', 'objective' => 'BASELINE', 'domains' => ['NON_COMMUNICABLE_DISEASES'], 'template' => 'NCD_SERVICES', 'lenses' => ['PERFORMANCE', 'CAPACITY']],
            ['code' => 'PHARMACY_SUPPLY_REVIEW', 'name' => 'Pharmacy and Supply Chain Review', 'objective' => 'OPERATIONAL_READINESS', 'domains' => ['PHARMACY'], 'template' => 'PHARMACY_ASSESSMENT', 'lenses' => ['OPERATIONS', 'RISK']],
            ['code' => 'SURGICAL_SAFETY_REVIEW', 'name' => 'Surgical Safety Review', 'objective' => 'PATIENT_SAFETY', 'domains' => ['SURGICAL_CARE'], 'template' => 'SURGICAL_CARE_ASSESSMENT', 'lenses' => ['PATIENT_SAFETY', 'RISK']],
            ['code' => 'AMR_STEWARDSHIP', 'name' => 'Antimicrobial Stewardship Review', 'objective' => 'QUALITY_IMPROVEMENT', 'domains' => ['ANTIMICROBIAL_RESISTANCE'], 'template' => 'AMR_ASSESSMENT', 'lenses' => ['CLINICAL_GOVERNANCE', 'RISK']],
            ['code' => 'OUTBREAK_PREPAREDNESS_CHECK', 'name' => 'Outbreak Preparedness Check', 'objective' => 'EMERGENCY_PREPAREDNESS', 'domains' => ['OUTBREAK_RESPONSE'], 'template' => 'OUTBREAK_READINESS', 'lenses' => ['RISK', 'CAPACITY']],
            ['code' => 'SRH_SITUATION', 'name' => 'Sexual and Reproductive Health Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['SEXUAL_REPRODUCTIVE_HEALTH'], 'template' => 'SRH_ASSESSMENT', 'lenses' => ['CAPACITY', 'EQUITY']],
            ['code' => 'ADOLESCENT_FRIENDLY_REVIEW', 'name' => 'Adolescent-Friendly Services Review', 'objective' => 'QUALITY_IMPROVEMENT', 'domains' => ['ADOLESCENT_HEALTH'], 'template' => 'ADOLESCENT_SERVICES', 'lenses' => ['QUALITY', 'EQUITY']],
            ['code' => 'DISABILITY_ACCESS', 'name' => 'Accessibility and Inclusion Audit', 'objective' => 'EQUITY_ACCESS', 'domains' => ['DISABILITY_INCLUSION'], 'template' => 'DISABILITY_ACCESS_AUDIT', 'lenses' => ['EQUITY', 'COMPLIANCE']],
            ['code' => 'RBF_VERIFICATION_ROUND', 'name' => 'Results-Based Financing Verification', 'objective' => 'RBF_VERIFICATION', 'domains' => ['HEALTH_INFORMATION_SYSTEMS'], 'template' => 'RBF_VERIFICATION_VISIT', 'lenses' => ['COMPLIANCE', 'DATA_CONFIDENCE']],
            ['code' => 'TRAINING_NEEDS_ROUND', 'name' => 'Training Needs Assessment', 'objective' => 'TRAINING_NEEDS', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'TRAINING_NEEDS_ASSESSMENT', 'lenses' => ['CAPACITY', 'PRIORITY_ACTION']],
            ['code' => 'CLIMATE_RESILIENCE_CHECK', 'name' => 'Climate Resilience Check', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['ENVIRONMENTAL_HEALTH'], 'template' => 'CLIMATE_RESILIENCE', 'lenses' => ['RISK', 'SUSTAINABILITY']],
            ['code' => 'STAFF_SAFETY_REVIEW', 'name' => 'Staff Health and Safety Review', 'objective' => 'PATIENT_SAFETY', 'domains' => ['OCCUPATIONAL_HEALTH'], 'template' => 'OCCUPATIONAL_HEALTH_ASSESSMENT', 'lenses' => ['RISK', 'CAPACITY']],
            ['code' => 'HMIS_REVIEW', 'name' => 'Health Information Systems Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['HEALTH_INFORMATION_SYSTEMS'], 'template' => 'HMIS_ASSESSMENT', 'lenses' => ['CAPACITY', 'DATA_CONFIDENCE']],

            // Entry points for the subjects and dimensions that are deliberately not
            // objectives. A user still starts from the familiar name; the model behind it
            // stays a purpose narrowed by a subject or a measurement dimension.
            ['code' => 'WORKFORCE_REVIEW', 'name' => 'Health Workforce Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'HEALTH_FACILITY_GENERAL', 'lenses' => ['CAPACITY', 'PERFORMANCE']],
            ['code' => 'GOVERNANCE_REVIEW', 'name' => 'Leadership and Governance Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'HEALTH_FACILITY_GENERAL', 'lenses' => ['CLINICAL_GOVERNANCE', 'EXECUTIVE']],
            ['code' => 'FINANCING_REVIEW', 'name' => 'Health Financing Review', 'objective' => 'EFFICIENCY_REVIEW', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'HEALTH_FACILITY_GENERAL', 'lenses' => ['EFFICIENCY', 'SUSTAINABILITY']],
            ['code' => 'INFRASTRUCTURE_REVIEW', 'name' => 'Infrastructure and Environment Review', 'objective' => 'OPERATIONAL_READINESS', 'domains' => ['ENVIRONMENTAL_HEALTH'], 'template' => 'CLIMATE_RESILIENCE', 'lenses' => ['CAPACITY', 'RISK']],
            ['code' => 'SUPPLY_CHAIN_REVIEW', 'name' => 'Supply Chain and Commodities Review', 'objective' => 'OPERATIONAL_READINESS', 'domains' => ['PHARMACY'], 'template' => 'PHARMACY_ASSESSMENT', 'lenses' => ['OPERATIONS', 'RISK']],
            ['code' => 'COMMUNITY_ENGAGEMENT_REVIEW', 'name' => 'Community Engagement Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['COMMUNITY_HEALTH'], 'template' => 'COMMUNITY_OUTREACH', 'lenses' => ['EQUITY', 'PUBLIC_HEALTH']],
            ['code' => 'DIGITAL_READINESS_REVIEW', 'name' => 'Digital Health Readiness', 'objective' => 'SERVICE_EXPANSION', 'domains' => ['HEALTH_INFORMATION_SYSTEMS'], 'template' => 'DIGITAL_READINESS', 'lenses' => ['CAPACITY', 'OPERATIONS']],
            ['code' => 'HEALTH_PROMOTION_REVIEW', 'name' => 'Health Promotion Review', 'objective' => 'PROGRAMME_EVALUATION', 'domains' => ['HEALTH_PROMOTION'], 'template' => 'COMMUNITY_OUTREACH', 'lenses' => ['PROGRAMME_EFFECTIVENESS', 'PUBLIC_HEALTH']],
            ['code' => 'DISTRICT_SUPERVISION_ROUND', 'name' => 'District PHC Supervision Round', 'objective' => 'SUPPORTIVE_SUPERVISION', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'DISTRICT_PHC_SUPERVISION', 'lenses' => ['PROGRESS', 'PRIORITY_ACTION']],
            ['code' => 'PATIENT_SATISFACTION_ROUND', 'name' => 'Patient Satisfaction Survey', 'objective' => 'PATIENT_SATISFACTION', 'domains' => ['PATIENT_EXPERIENCE'], 'template' => 'PATIENT_EXPERIENCE_SURVEY', 'lenses' => ['QUALITY', 'EQUITY']],
            ['code' => 'SUSTAINABILITY_CHECK', 'name' => 'Programme Sustainability Review', 'objective' => 'SUSTAINABILITY_REVIEW', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'HEALTH_FACILITY_GENERAL', 'lenses' => ['SUSTAINABILITY', 'EXECUTIVE']],

            // Templates that existed but nothing led to them. A template nobody is routed
            // to is only findable by browsing the whole catalogue, which is the same as
            // not existing for most users.
            ['code' => 'BLOOD_SERVICES_REVIEW', 'name' => 'Blood and Transfusion Services Review', 'objective' => 'OPERATIONAL_READINESS', 'domains' => ['BLOOD_SERVICES'], 'template' => 'BLOOD_SERVICES_ASSESSMENT', 'lenses' => ['CAPACITY', 'PATIENT_SAFETY']],
            ['code' => 'DISTRICT_SYSTEM_REVIEW', 'name' => 'District Health System Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'DISTRICT_REVIEW', 'lenses' => ['PERFORMANCE', 'EQUITY', 'EXECUTIVE']],
            ['code' => 'EYE_HEALTH_REVIEW', 'name' => 'Eye Health Services Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['EYE_HEALTH'], 'template' => 'EYE_HEALTH_ASSESSMENT', 'lenses' => ['CAPACITY', 'EQUITY']],
            ['code' => 'ORAL_HEALTH_REVIEW', 'name' => 'Oral Health Services Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['ORAL_HEALTH'], 'template' => 'ORAL_HEALTH_ASSESSMENT', 'lenses' => ['CAPACITY', 'EQUITY']],
            ['code' => 'REHABILITATION_REVIEW', 'name' => 'Rehabilitation Services Review', 'objective' => 'SITUATION_ANALYSIS', 'domains' => ['REHABILITATION'], 'template' => 'REHABILITATION_ASSESSMENT', 'lenses' => ['CAPACITY', 'EQUITY']],
        ];

        $objectives = AssessmentObjective::where('methodology_version_id', $version->methodology_version_id)
            ->pluck('assessment_objective_id', 'objective_code');

        foreach ($presets as $order => $preset) {
            $objectiveId = $objectives[$preset['objective']] ?? null;

            if (! $objectiveId) {
                continue;
            }

            ObjectivePreset::updateOrCreate(
                ['methodology_version_id' => $version->methodology_version_id, 'preset_code' => $preset['code']],
                [
                    'assessment_objective_id' => $objectiveId,
                    'preset_name' => $preset['name'],
                    'health_domain_codes' => $preset['domains'],
                    'template_code' => $preset['template'],
                    'analysis_lens_codes' => $preset['lenses'],
                    'display_order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
