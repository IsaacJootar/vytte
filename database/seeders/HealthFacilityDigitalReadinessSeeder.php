<?php

namespace Database\Seeders;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\Question;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Services\CataloguePublishingService;
use App\Services\OfficialFrameworkComposer;
use App\Services\QuestionVersionPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Health Facility Problem Discovery & Digital Readiness Assessment.
 *
 * A Focused Health Assessment authored from Odion Ikyo's three-email specification
 * (20-21 Aug 2026): thirty scored questions across six domains — health information
 * workflow, information problems and pain, impact and workarounds, digital infrastructure
 * and workforce readiness, digital health/NDHA alignment, and implementation and change
 * readiness — plus one intentionally unscored free-text follow-up (DHR.014).
 *
 * This is a pure measurement and readiness instrument. No product-fit or solution-matching
 * logic exists anywhere in this seeder or in the report it produces — that scope was
 * explicitly rejected: Vytte reports are shareable (report links, exports), so anything in
 * one is effectively visible to whoever the assessing organisation hands it to, including
 * the facility itself. A "this facility needs product X" line in that context is
 * advertising, not diagnosis.
 *
 * Q7 (between "unable to find previous information" and "duplicate records created") was
 * missing from the source material — a PDF page-break artifact — and is authored here to
 * fill the gap, in the same style and covering a theme (delayed retrieval) Odion's own
 * emails listed as worth exploring.
 *
 * DHR.014, the "describe the workaround" follow-up, is unscored — Odion's own instruction:
 * "Vyttes should record what the workaround is as a non-scored follow-up." OfficialFrameworkComposer
 * decides scoring purely by question type (unscored only for open text or band-less numeric),
 * so an OPEN_ENDED question is automatically unscored with no extra code needed.
 *
 * DHR.016 ("how much difference would solving this make") is scored like every other
 * select-type question in this framework, by explicit decision: every template question
 * should be scored. It measures potential value rather than current-state maturity, which
 * is worth keeping in mind when reading Domain C's score, but it is not an exception.
 *
 * Domain-to-measurement-domain mapping is one domain per section, matching the platform's
 * existing convention (one section, one dominant domain) rather than splitting within a
 * section: A/B/C -> INFO (all three are facets of the same information-management problem,
 * matching Odion's own grouping of B+C into one Problem Burden score), D -> RES (the
 * section is majority infrastructure — electricity, connectivity, devices — with two
 * workforce-flavoured items folded in for this v1 structure), E -> INFO (interoperability
 * and information-systems is the dominant theme), F -> WORK (staff and organisational
 * change-readiness is the dominant theme). Flagged for review, not treated as unquestionably
 * final — a finer per-question split is possible later if the field-validation pass shows
 * the domain rollups are misleading.
 *
 * Follows the same governed publishing path as the official library: questions through
 * QuestionVersionPublishingService, the framework through OfficialFrameworkComposer (which
 * itself publishes via DepartmentFrameworkPublishingService), the catalogue release through
 * CataloguePublishingService. Idempotent throughout.
 */
class HealthFacilityDigitalReadinessSeeder extends Seeder
{
    private const MODULE_CODE = 'DHR';

    private const FRAMEWORK_NAME = 'Health Facility Problem Discovery & Digital Readiness Assessment';

    private const HEALTH_DOMAIN_CODE = 'DIGITAL_HEALTH_READINESS';

    private const RELEASE_CODE = 'VYTTE_DHR_V1';

    public function run(): void
    {
        $this->seedModule();
        $this->seedHealthDomain();
        $this->publishQuestions();
        $result = $this->composeFramework();

        if ($result['status'] === 'published') {
            $this->command?->info("Health Facility Digital Readiness: {$result['placed']} questions placed.");
            $this->publishCatalogueRelease();
        } else {
            $this->command?->warn("Health Facility Digital Readiness framework: {$result['status']}.");
        }

        if ($result['missing'] !== []) {
            $this->command?->warn('Question codes referenced but not found: '.implode(', ', $result['missing']));
        }
    }

    private function seedModule(): void
    {
        AssessmentModule::updateOrCreate(
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => self::MODULE_CODE],
            [
                'module_name' => 'Digital Health Readiness',
                'primary_respondent' => 'Facility Manager · Health Information Officer · Digital Health Focal Person',
                'estimated_duration_minutes' => 40,
                'data_collection_methods' => 'Interview · Observation · Record review',
                'is_active' => true,
                'requires_consent' => false,
            ]
        );
    }

    private function seedHealthDomain(): void
    {
        DB::table('health_domains')->insertOrIgnore([
            'domain_code' => self::HEALTH_DOMAIN_CODE,
            'domain_name' => 'Digital Health Readiness',
            'display_order' => (int) DB::table('health_domains')->max('display_order') + 1,
        ]);
    }

    private function publishQuestions(): void
    {
        $publishing = app(QuestionVersionPublishingService::class);
        $types = QuestionType::pluck('type_id', 'type_code');
        $moduleId = AssessmentModule::where('module_code', self::MODULE_CODE)
            ->where('target_type_code', 'HEALTH_FACILITY')
            ->value('module_id');

        $published = 0;
        $skipped = 0;

        foreach (self::questions() as $definition) {
            $result = DB::transaction(fn () => $this->publishQuestion($definition, $moduleId, $types, $publishing));
            $result ? $published++ : $skipped++;
        }

        $this->command?->info("Digital readiness questions: {$published} published, {$skipped} skipped.");
    }

    private function publishQuestion(array $definition, int $moduleId, $types, QuestionVersionPublishingService $publishing): bool
    {
        $typeId = $types[$definition['type']] ?? null;

        if (! $typeId) {
            return false;
        }

        $question = Question::where('question_code', $definition['code'])->first();

        if ($question && $question->versions()->where('status', QuestionVersion::STATUS_PUBLISHED)->exists()) {
            return false;
        }

        if (! $question) {
            $nextNumber = ((int) Question::where('module_id', $moduleId)->max('question_number')) + 1;
            $nextOrder = ((int) Question::where('module_id', $moduleId)->max('display_order')) + 1;

            $question = Question::create([
                'module_id' => $moduleId,
                'question_number' => $nextNumber,
                'question_code' => $definition['code'],
                'question_text' => $definition['text'],
                'type_id' => $typeId,
                'respondent_role_hint' => $definition['respondent'] ?? null,
                'display_order' => $nextOrder,
                'is_active' => true,
                'is_scored' => $definition['type'] !== 'OPEN_ENDED',
                'source' => 'VYTTE_OFFICIAL',
                'question_status' => 'DRAFT',
                'standard_alignment_status' => 'VYTTE_METHODOLOGY',
            ]);
        }

        $version = QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => ((int) $question->versions()->max('version_number')) + 1,
            'status' => QuestionVersion::STATUS_APPROVED,
            'question_text' => $definition['text'],
            'type_id' => $typeId,
            'options' => $this->optionPayload($definition),
            'requires_observation' => $definition['observe'] ?? false,
            'respondent_role_hint' => $definition['respondent'] ?? null,
            'methodology_notes' => $definition['why'] ?? null,
            'source_summary' => 'Authored from PrimeSafePath\'s Health Facility Problem Discovery & Digital Readiness specification (Aug 2026).',
        ]);

        $publishing->publish($version);

        return true;
    }

    private function optionPayload(array $definition): ?array
    {
        if (! isset($definition['options'])) {
            return null;
        }

        $payload = [];
        foreach ($definition['options'] as $index => $option) {
            $payload[] = [
                'option_id' => $index + 1,
                'option_label' => $option['label'],
                'option_order' => $index + 1,
                'score_weight' => $option['score'],
                'critical_failure' => false,
            ];
        }

        return $payload;
    }

    private function composeFramework(): array
    {
        return app(OfficialFrameworkComposer::class)->compose([
            'module' => self::MODULE_CODE,
            'code' => 'DHR',
            'name' => self::FRAMEWORK_NAME,
            'description' => 'Discovers what health-information problems a facility actually has, how severe they are, and whether the facility is realistically able to adopt and sustain digital health technology. Reports readiness only — it never names or recommends any specific product.',
            'type' => 'FOCUSED',
            'sections' => [
                ['domain' => 'INFO', 'name' => 'Health Information Workflow', 'questions' => [
                    'DHR.001', 'DHR.002', 'DHR.003', 'DHR.004', 'DHR.005',
                    'DHR.006', 'DHR.007', 'DHR.008', 'DHR.009', 'DHR.010',
                    'DHR.011', 'DHR.012', 'DHR.013', 'DHR.014', 'DHR.015', 'DHR.016',
                ]],
                ['domain' => 'RES', 'name' => 'Digital Infrastructure & Workforce Readiness', 'questions' => [
                    'DHR.017', 'DHR.018', 'DHR.019', 'DHR.020', 'DHR.021',
                ]],
                ['domain' => 'INFO', 'name' => 'Digital Health & NDHA Alignment', 'questions' => [
                    'DHR.022', 'DHR.023', 'DHR.024', 'DHR.025', 'DHR.026',
                ]],
                ['domain' => 'WORK', 'name' => 'Implementation & Change Readiness', 'questions' => [
                    'DHR.027', 'DHR.028', 'DHR.029', 'DHR.030', 'DHR.031',
                ]],
            ],
        ]);
    }

    private function publishCatalogueRelease(): void
    {
        $existing = AssessmentCatalogueRelease::where('release_code', self::RELEASE_CODE)->first();
        if ($existing) {
            return;
        }

        $framework = \App\Models\DepartmentFrameworkVersion::where('display_name', self::FRAMEWORK_NAME)
            ->where('status', \App\Models\DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->first();

        if (! $framework) {
            $this->command?->warn('Digital readiness catalogue release skipped: framework not published.');

            return;
        }

        $domainId = DB::table('health_domains')->where('domain_code', self::HEALTH_DOMAIN_CODE)->value('health_domain_id');

        DB::transaction(function () use ($framework, $domainId): void {
            $release = AssessmentCatalogueRelease::firstOrCreate(
                ['release_code' => self::RELEASE_CODE],
                [
                    'release_name' => self::FRAMEWORK_NAME,
                    'description' => 'Focused digital-readiness and problem-discovery assessment.',
                    'creation_path' => 'FOCUSED',
                    'health_domain_id' => $domainId,
                    'aggregation_policy' => [
                        'method' => 'MEAN_OF_SCORED_SUB_INDICES',
                        'critical_failures' => ['enabled' => true, 'option_score_at_or_below' => 0, 'overall_score' => 'ZERO'],
                    ],
                    'composition_rules' => ['latest_resolution' => 'forbidden'],
                ]
            );

            $release->departmentFrameworkVersions()->syncWithoutDetaching([
                $framework->framework_version_id => [
                    'module_id' => $framework->module_id,
                    'applicability' => 'REQUIRED',
                    'display_order' => 1,
                    'area_label' => self::FRAMEWORK_NAME,
                ],
            ]);

            app(CataloguePublishingService::class)->publish($release->fresh());
        });

        $this->command?->info('Digital readiness catalogue release published.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function questions(): array
    {
        $infoRespondent = 'Facility Manager · Health Information Officer · Records Officer';
        $digitalRespondent = 'Facility Manager · IT/Digital Health Focal Person';

        return [
            // Domain A — Health Information Workflow
            ['code' => 'DHR.001', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'How are patients registered when they first arrive at the facility?',
                'options' => [
                    ['label' => 'Registration is digitally captured using a structured system with a reliable patient identifier', 'score' => 100],
                    ['label' => 'Standard registration process is consistently followed', 'score' => 75],
                    ['label' => 'Standard paper registration process exists but is inconsistently followed', 'score' => 50],
                    ['label' => 'Registration is entirely manual/ad hoc', 'score' => 25],
                    ['label' => 'No consistent registration process', 'score' => 0],
                ]],
            ['code' => 'DHR.002', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'How are patient records created and stored?',
                'options' => [
                    ['label' => 'Patient records are predominantly electronic and centrally retrievable', 'score' => 100],
                    ['label' => 'Paper and digital records are systematically maintained', 'score' => 75],
                    ['label' => 'Structured paper records/registers are maintained', 'score' => 50],
                    ['label' => 'Predominantly loose paper records', 'score' => 25],
                    ['label' => 'No organised patient record system', 'score' => 0],
                ]],
            ['code' => 'DHR.003', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => "When a returning patient arrives, how easily can staff retrieve the patient's previous information?",
                'options' => [
                    ['label' => 'Patient information is immediately retrievable through an electronic system', 'score' => 100],
                    ['label' => 'Records are routinely retrieved within the normal patient workflow', 'score' => 75],
                    ['label' => 'Records can usually be retrieved but delays are common', 'score' => 50],
                    ['label' => 'Retrieval depends on finding paper files/registers', 'score' => 25],
                    ['label' => 'Previous information is generally unavailable', 'score' => 0],
                ]],
            ['code' => 'DHR.004', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'How is patient information transferred between departments within the facility?',
                'options' => [
                    ['label' => 'Information is digitally available to authorised staff across relevant departments', 'score' => 100],
                    ['label' => 'Standardised transfer process is consistently followed', 'score' => 75],
                    ['label' => 'Paper/register-based process exists but is inconsistent', 'score' => 50],
                    ['label' => 'Mainly verbal or paper-based', 'score' => 25],
                    ['label' => 'No defined process', 'score' => 0],
                ]],
            ['code' => 'DHR.005', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'How is information reported from the facility to the LGA/state/national health system?',
                'options' => [
                    ['label' => 'Data are digitally captured once and reused for required reporting with minimal duplicate entry', 'score' => 100],
                    ['label' => 'Reporting is routine and consistently completed using established systems', 'score' => 75],
                    ['label' => 'Required reporting systems exist but substantial duplication/manual work remains', 'score' => 50],
                    ['label' => 'Reporting is largely manual and dependent on individual staff', 'score' => 25],
                    ['label' => 'No defined reporting process', 'score' => 0],
                ]],

            // Domain B — Information Problems & Pain
            ['code' => 'DHR.006', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => "In the past 3 months, how often has staff needed a patient's previous information but been unable to find it?",
                'options' => [
                    ['label' => 'Never', 'score' => 100],
                    ['label' => 'Once', 'score' => 75],
                    ['label' => '2–3 times', 'score' => 50],
                    ['label' => 'Monthly or more', 'score' => 25],
                    ['label' => 'Weekly or more', 'score' => 0],
                ]],
            ['code' => 'DHR.007', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => "In the past 3 months, how often has retrieving a patient's existing record taken long enough to delay the consultation or care being given?",
                'why' => "Authored to fill a gap in the source material (a missing 'Q7') — covers delayed retrieval specifically, distinct from total unavailability.",
                'options' => [
                    ['label' => 'Never', 'score' => 100],
                    ['label' => 'Once', 'score' => 75],
                    ['label' => '2–3 times', 'score' => 50],
                    ['label' => 'Monthly or more', 'score' => 25],
                    ['label' => 'Weekly or more', 'score' => 0],
                ]],
            ['code' => 'DHR.008', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'How often are duplicate patient records created because staff cannot confidently identify an existing record?',
                'options' => [
                    ['label' => 'Never/not applicable', 'score' => 100],
                    ['label' => 'Rarely', 'score' => 75],
                    ['label' => 'Occasionally', 'score' => 50],
                    ['label' => 'Frequently', 'score' => 25],
                    ['label' => 'Very frequently', 'score' => 0],
                ]],
            ['code' => 'DHR.009', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'How often does missing, incomplete or illegible information affect the care or follow-up of a patient?',
                'options' => [
                    ['label' => 'Never', 'score' => 100],
                    ['label' => 'Rarely', 'score' => 75],
                    ['label' => 'Occasionally', 'score' => 50],
                    ['label' => 'Frequently', 'score' => 25],
                    ['label' => 'Very frequently', 'score' => 0],
                ]],
            ['code' => 'DHR.010', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'How much staff time is spent searching for, reconciling or manually reproducing patient information?',
                'options' => [
                    ['label' => 'None/negligible', 'score' => 100],
                    ['label' => 'Less than 15 minutes per day', 'score' => 75],
                    ['label' => '15–30 minutes per day', 'score' => 50],
                    ['label' => '31–60 minutes per day', 'score' => 25],
                    ['label' => 'More than 60 minutes per day', 'score' => 0],
                ]],

            // Domain C — Impact & Existing Workarounds
            ['code' => 'DHR.011', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'When patient information is unavailable, what is the usual consequence?',
                'options' => [
                    ['label' => 'No meaningful consequence', 'score' => 100],
                    ['label' => 'Minor inconvenience', 'score' => 75],
                    ['label' => 'Additional staff time/work', 'score' => 50],
                    ['label' => 'Delayed or less efficient care/follow-up', 'score' => 25],
                    ['label' => 'Has resulted in a significant clinical, reporting, financial or patient-safety consequence', 'score' => 0],
                ]],
            ['code' => 'DHR.012', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'How often are patients asked to repeat information, investigations or processes because previous information is unavailable?',
                'options' => [
                    ['label' => 'Never', 'score' => 100],
                    ['label' => 'Once', 'score' => 75],
                    ['label' => '2–3 times', 'score' => 50],
                    ['label' => 'Monthly or more', 'score' => 25],
                    ['label' => 'Weekly or more', 'score' => 0],
                ]],
            ['code' => 'DHR.013', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'Does the facility currently use any workaround to deal with missing or fragmented patient information?',
                'options' => [
                    ['label' => 'No workaround because the problem does not occur', 'score' => 100],
                    ['label' => 'Staff solve problems individually when they arise', 'score' => 15],
                    ['label' => 'Informal workarounds such as notebooks, WhatsApp, spreadsheets or personal records', 'score' => 40],
                    ['label' => 'A formal workaround/process exists', 'score' => 75],
                    ['label' => 'A structured digital solution already addresses the problem', 'score' => 100],
                ]],
            ['code' => 'DHR.014', 'type' => 'OPEN_ENDED', 'respondent' => $infoRespondent,
                'text' => 'Please describe the current workaround, if any, in your own words.',
                'why' => "Odion's own instruction: recorded as a non-scored follow-up to explain the answer above, not judged on a scale."],
            ['code' => 'DHR.015', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => "How effective is the facility's current approach to managing patient information?",
                'options' => [
                    ['label' => 'Highly effective', 'score' => 100],
                    ['label' => 'Mostly effective', 'score' => 75],
                    ['label' => 'Partially effective', 'score' => 50],
                    ['label' => 'Very limited', 'score' => 25],
                    ['label' => 'Not effective', 'score' => 0],
                ]],
            ['code' => 'DHR.016', 'type' => 'SINGLE_SELECT', 'respondent' => $infoRespondent,
                'text' => 'If the current problem with patient information were completely solved tomorrow, how much difference would it make to the facility?',
                'why' => 'Measures potential value rather than current-state maturity, but every template question is scored — no exception here.',
                'options' => [
                    ['label' => 'Critical improvement to service delivery', 'score' => 100],
                    ['label' => 'Major improvement', 'score' => 75],
                    ['label' => 'Moderate improvement', 'score' => 50],
                    ['label' => 'Small improvement', 'score' => 25],
                    ['label' => 'No meaningful difference', 'score' => 0],
                ]],

            // Domain D — Digital Infrastructure & Workforce Readiness
            ['code' => 'DHR.017', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent, 'observe' => true,
                'text' => 'Does the facility have reliable access to electricity for operating digital equipment?',
                'options' => [
                    ['label' => 'More than 75% of operating time / reliable primary and backup power', 'score' => 100],
                    ['label' => '51–75% of operating time', 'score' => 75],
                    ['label' => '25–50% of operating time', 'score' => 50],
                    ['label' => 'Less than 25% of operating time', 'score' => 25],
                    ['label' => 'No reliable electricity', 'score' => 0],
                ]],
            ['code' => 'DHR.018', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent, 'observe' => true,
                'text' => 'Does the facility have reliable internet connectivity where digital health activities would take place?',
                'options' => [
                    ['label' => 'Reliable and adequate for routine digital health operations', 'score' => 100],
                    ['label' => 'Reliable during most operating hours', 'score' => 75],
                    ['label' => 'Intermittent but usable', 'score' => 50],
                    ['label' => 'Rare/unreliable', 'score' => 25],
                    ['label' => 'No connectivity', 'score' => 0],
                ]],
            ['code' => 'DHR.019', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent, 'observe' => true,
                'text' => 'Does the facility have adequate functional digital devices for the staff who would use a digital health system?',
                'options' => [
                    ['label' => 'Adequate devices with replacement/maintenance arrangements', 'score' => 100],
                    ['label' => 'Adequate devices for routine use', 'score' => 75],
                    ['label' => 'Devices available but insufficient', 'score' => 50],
                    ['label' => 'Devices available only to a few staff', 'score' => 25],
                    ['label' => 'No appropriate devices', 'score' => 0],
                ]],
            ['code' => 'DHR.020', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'What is the level of digital competence among the staff who would use the system?',
                'options' => [
                    ['label' => 'Staff are competent and the facility has a mechanism for ongoing digital training', 'score' => 100],
                    ['label' => 'Most relevant staff can independently use digital systems', 'score' => 75],
                    ['label' => 'Some staff can use digital systems independently', 'score' => 50],
                    ['label' => 'Basic smartphone/computer use only', 'score' => 25],
                    ['label' => 'Staff have little/no digital experience', 'score' => 0],
                ]],
            ['code' => 'DHR.021', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'Has the facility successfully implemented and sustained a digital health system before?',
                'options' => [
                    ['label' => 'Has successfully implemented multiple systems and has established processes for digital-system management', 'score' => 100],
                    ['label' => 'Has successfully implemented and sustained at least one system', 'score' => 75],
                    ['label' => 'Currently using one inconsistently', 'score' => 50],
                    ['label' => 'Tried but abandoned', 'score' => 25],
                    ['label' => 'Never implemented one', 'score' => 0],
                ]],

            // Domain E — Digital Health & NDHA Alignment
            ['code' => 'DHR.022', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'Does the facility currently use an electronic system to capture or manage patient health information?',
                'options' => [
                    ['label' => 'Integrated electronic record system used routinely across the facility', 'score' => 100],
                    ['label' => 'Used routinely across most relevant services', 'score' => 75],
                    ['label' => 'Used in some departments/services', 'score' => 50],
                    ['label' => 'Pilot/isolated use', 'score' => 25],
                    ['label' => 'No', 'score' => 0],
                ]],
            ['code' => 'DHR.023', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'Does the facility use a consistent method of identifying patients across visits?',
                'options' => [
                    ['label' => 'A unique identifier is systematically used and can support continuity across services/systems', 'score' => 100],
                    ['label' => 'A consistent patient identifier is routinely used', 'score' => 75],
                    ['label' => 'Some structured identifiers are used', 'score' => 50],
                    ['label' => 'Mainly name-based/manual identification', 'score' => 25],
                    ['label' => 'No consistent identification method', 'score' => 0],
                ]],
            ['code' => 'DHR.024', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => "Can the facility's current digital system exchange patient information with another health information system?",
                'options' => [
                    ['label' => 'System is designed and actively used for standards-based interoperability', 'score' => 100],
                    ['label' => 'Some automated exchange/integration exists', 'score' => 75],
                    ['label' => 'Limited/manual exchange', 'score' => 50],
                    ['label' => 'Digital system exists but cannot exchange data', 'score' => 25],
                    ['label' => 'No digital system', 'score' => 0],
                ]],
            ['code' => 'DHR.025', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'Does the facility have documented practices for protecting patient health information — authorised access, passwords/access control, confidentiality, backup, data recovery, secure storage?',
                'options' => [
                    ['label' => 'Documented practices are implemented, monitored and periodically reviewed', 'score' => 100],
                    ['label' => 'Documented practices are consistently implemented', 'score' => 75],
                    ['label' => 'Some practices exist but are inconsistent', 'score' => 50],
                    ['label' => 'Informal practices', 'score' => 25],
                    ['label' => 'No defined practices', 'score' => 0],
                ]],
            ['code' => 'DHR.026', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => "How familiar is facility leadership with Nigeria's digital-health direction, including NDHA/NDHI?",
                'why' => 'Awareness alone does not make a facility ready — this is one component among several, not treated as decisive on its own.',
                'options' => [
                    ['label' => 'Actively aligning facility digital-health activities with national direction', 'score' => 100],
                    ['label' => 'Understands the implications for the facility and has begun planning', 'score' => 75],
                    ['label' => 'Understands the general direction', 'score' => 50],
                    ['label' => 'Has heard of it but cannot explain its relevance', 'score' => 25],
                    ['label' => 'Not aware', 'score' => 0],
                ]],

            // Domain F — Implementation & Change Readiness
            ['code' => 'DHR.027', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'Is there a senior person at the facility who will take responsibility for implementation of a digital system?',
                'options' => [
                    ['label' => 'Responsible person identified with defined authority, time and accountability', 'score' => 100],
                    ['label' => 'Responsible person formally identified', 'score' => 75],
                    ['label' => 'Person identified but no formal responsibility', 'score' => 50],
                    ['label' => 'Someone may be available but role is undefined', 'score' => 25],
                    ['label' => 'No', 'score' => 0],
                ]],
            ['code' => 'DHR.028', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'How willing are the relevant staff to change their current workflow to use a digital system?',
                'options' => [
                    ['label' => 'Strong willingness with staff actively requesting improvement', 'score' => 100],
                    ['label' => 'Generally willing', 'score' => 75],
                    ['label' => 'Mixed/uncertain', 'score' => 50],
                    ['label' => 'Mostly resistant', 'score' => 25],
                    ['label' => 'Strong resistance', 'score' => 0],
                ]],
            ['code' => 'DHR.029', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'Is there a defined process for training new staff when staff turnover occurs?',
                'options' => [
                    ['label' => 'Standard training, onboarding and refresher process is institutionalised', 'score' => 100],
                    ['label' => 'Standard training process exists', 'score' => 75],
                    ['label' => 'Training occurs but inconsistently', 'score' => 50],
                    ['label' => 'Training occurs informally', 'score' => 25],
                    ['label' => 'No training process', 'score' => 0],
                ]],
            ['code' => 'DHR.030', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => 'If a digital system stops working, does the facility have a defined process for obtaining technical support?',
                'options' => [
                    ['label' => 'Defined support mechanism with response expectations, escalation and continuity procedures', 'score' => 100],
                    ['label' => 'Defined support mechanism exists', 'score' => 75],
                    ['label' => 'Informal support arrangement', 'score' => 50],
                    ['label' => 'Staff rely on whoever happens to know the system', 'score' => 25],
                    ['label' => 'No support process', 'score' => 0],
                ]],
            ['code' => 'DHR.031', 'type' => 'SINGLE_SELECT', 'respondent' => $digitalRespondent,
                'text' => "If the facility were offered a digital health solution that addressed its most important information problem, what would be the facility's implementation position?",
                'why' => 'Deliberately phrased as a generic "a digital health solution", never naming any specific product.',
                'options' => [
                    ['label' => 'Ready to implement and able to sustain the solution', 'score' => 100],
                    ['label' => 'Ready to implement with defined support', 'score' => 75],
                    ['label' => 'Interested and could participate in a limited pilot', 'score' => 50],
                    ['label' => 'Interested but no realistic ability to implement', 'score' => 25],
                    ['label' => 'Not interested', 'score' => 0],
                ]],
        ];
    }
}
