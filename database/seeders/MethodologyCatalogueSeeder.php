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
        });
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
            ['code' => 'HEALTH_WORKFORCE', 'name' => 'Health Workforce', 'group' => 'SYSTEM', 'question' => 'Do we have the right people?', 'description' => 'Staffing numbers, skill mix, training, supervision, retention and distribution.'],
            ['code' => 'LEADERSHIP_GOVERNANCE', 'name' => 'Leadership and Governance', 'group' => 'SYSTEM', 'question' => 'Is this place well run?', 'description' => 'Decision-making, accountability, planning, policies and oversight arrangements.'],
            ['code' => 'HEALTH_FINANCING', 'name' => 'Health Financing', 'group' => 'SYSTEM', 'question' => 'Is the money working?', 'description' => 'Funding flows, budgeting, cost of care, financial protection for patients and financial management.'],
            ['code' => 'HEALTH_INFORMATION', 'name' => 'Health Information Systems', 'group' => 'SYSTEM', 'question' => 'Can we trust our data?', 'description' => 'Recording, reporting, data quality, and whether information is actually used in decisions.'],
            ['code' => 'DIGITAL_HEALTH', 'name' => 'Digital Health Readiness', 'group' => 'SYSTEM', 'question' => 'Are we ready to work digitally?', 'description' => 'Connectivity, devices, systems, digital skills and governance of digital tools.'],
            ['code' => 'SUPPLY_CHAIN', 'name' => 'Supply Chain and Commodities', 'group' => 'SYSTEM', 'question' => 'Do we have what we need, when we need it?', 'description' => 'Availability, storage, stock management and distribution of medicines, consumables and equipment.'],
            ['code' => 'INFRASTRUCTURE', 'name' => 'Infrastructure and Environment', 'group' => 'SYSTEM', 'question' => 'Is the physical environment fit for care?', 'description' => 'Buildings, utilities, water, power, waste and the physical conditions in which care happens.'],
            ['code' => 'COMMUNITY_ENGAGEMENT', 'name' => 'Community Engagement', 'group' => 'SYSTEM', 'question' => 'Are we connected to the people we serve?', 'description' => 'Outreach, community structures, feedback and the relationship between the facility and its population.'],
            ['code' => 'HEALTH_PROMOTION', 'name' => 'Health Promotion', 'group' => 'SYSTEM', 'question' => 'Are we helping people stay well?', 'description' => 'Prevention, education and promotion activity, as distinct from treatment.'],
            ['code' => 'EQUITY_ACCESS', 'name' => 'Equity and Access', 'group' => 'SYSTEM', 'question' => 'Who is being left out?', 'description' => 'Whether services reach all groups, including those furthest from care.'],
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
            'GENERAL_HEALTH_SYSTEMS' => [
                ['code' => 'OPD', 'name' => 'Outpatient Services'],
                ['code' => 'IPD', 'name' => 'Inpatient Services'],
                ['code' => 'EMERGENCY_CARE', 'name' => 'Emergency and Trauma Care'],
                ['code' => 'LABORATORY', 'name' => 'Laboratory Services'],
                ['code' => 'PHARMACY', 'name' => 'Pharmacy and Medicines'],
                ['code' => 'IMAGING', 'name' => 'Diagnostic Imaging'],
                ['code' => 'SURGERY', 'name' => 'Surgical Services'],
                ['code' => 'BLOOD_SERVICES', 'name' => 'Blood and Transfusion Services'],
                ['code' => 'REFERRAL', 'name' => 'Referral and Transport'],
                ['code' => 'NCD', 'name' => 'Non-Communicable Diseases'],
                ['code' => 'MALARIA_SERVICES', 'name' => 'Malaria Diagnosis and Treatment'],
                ['code' => 'NTD', 'name' => 'Neglected Tropical Diseases'],
                ['code' => 'COMMUNITY_HEALTH', 'name' => 'Community Health Services'],
                ['code' => 'RECORDS', 'name' => 'Records and Health Information'],
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
            'HEALTH_WORKFORCE' => [
                'ANALYSIS_LENS' => ['CAPACITY', 'PERFORMANCE'],
                'MEASUREMENT_DOMAIN' => ['WORK'],
            ],
            'HEALTH_INFORMATION' => [
                'ANALYSIS_LENS' => ['QUALITY', 'CAPACITY'],
                'MEASUREMENT_DOMAIN' => ['INFO'],
            ],
            'LEADERSHIP_GOVERNANCE' => [
                'ANALYSIS_LENS' => ['CLINICAL_GOVERNANCE', 'EXECUTIVE'],
                'MEASUREMENT_DOMAIN' => ['GOV'],
            ],
            'EQUITY_ACCESS' => [
                'ANALYSIS_LENS' => ['EQUITY', 'PUBLIC_HEALTH'],
                'MEASUREMENT_DOMAIN' => ['PCOM', 'SERV'],
            ],
            'DIGITAL_HEALTH' => [
                'TEMPLATE' => ['DIGITAL_READINESS'],
                'ANALYSIS_LENS' => ['CAPACITY', 'OPERATIONS'],
            ],
            'RESEARCH' => [
                'TEMPLATE' => ['RESEARCH_INSTRUMENT'],
                'ANALYSIS_LENS' => ['PERFORMANCE', 'BENCHMARK'],
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
            ['code' => 'MALARIA_BASELINE', 'name' => 'Malaria Baseline Assessment', 'objective' => 'BASELINE', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'MALARIA_PROGRAMME', 'lenses' => ['PERFORMANCE', 'CAPACITY']],
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
            ['code' => 'LAB_QUALITY', 'name' => 'Laboratory Quality Review', 'objective' => 'QUALITY_IMPROVEMENT', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'LABORATORY_ASSESSMENT', 'lenses' => ['QUALITY', 'CAPACITY']],
            ['code' => 'EMERGENCY_READINESS', 'name' => 'Emergency Care Readiness', 'objective' => 'EMERGENCY_PREPAREDNESS', 'domains' => ['GENERAL_HEALTH_SYSTEMS'], 'template' => 'EMERGENCY_CARE_ASSESSMENT', 'lenses' => ['RISK', 'CAPACITY']],
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
