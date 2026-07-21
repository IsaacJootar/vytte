<?php

namespace Database\Seeders;

use App\Models\AssessmentModule;
use App\Models\Question;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Services\QuestionVersionPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The official Vytte question library.
 *
 * Production content, not demonstration data. Every question here is intended to be
 * answered by a real facility and to carry a real score.
 *
 * Published through `QuestionVersionPublishingService` rather than written straight to
 * the table, so each version is validated, content-hashed and audited exactly as it
 * would be if an author had created it in the builder. Seeding is not an excuse to skip
 * governance.
 *
 * Questions are written to be reusable. A hand hygiene question belongs to infection
 * prevention, but it is placed into hospital readiness, primary healthcare and maternal
 * care frameworks too, which is why the library is organised by subject rather than by
 * the framework that happens to use it first.
 *
 * Informed by the structure and content patterns of WHO SARA, SPA and HHFA, WASH FIT,
 * the WHO IPC minimum requirements and the health system building blocks. Wording is
 * Vytte's own; no instrument is reproduced.
 */
class OfficialQuestionLibrarySeeder extends Seeder
{
    /** Yes, partially, no. The default shape where a requirement is either met or not. */
    private const YES_PARTIAL_NO = [
        ['label' => 'Yes', 'score' => 100],
        ['label' => 'Partially', 'score' => 50],
        ['label' => 'No', 'score' => 0],
    ];

    /** Where a negative answer is a serious finding in its own right. */
    private const YES_PARTIAL_NO_CRITICAL = [
        ['label' => 'Yes', 'score' => 100],
        ['label' => 'Partially', 'score' => 50],
        ['label' => 'No', 'score' => 0, 'critical' => true],
    ];

    private const YES_NO = [
        ['label' => 'Yes', 'score' => 100],
        ['label' => 'No', 'score' => 0],
    ];

    private const AVAILABILITY = [
        ['label' => 'Always available', 'score' => 100],
        ['label' => 'Usually available', 'score' => 75],
        ['label' => 'Sometimes available', 'score' => 40],
        ['label' => 'Rarely or never available', 'score' => 0],
    ];

    private const FREQUENCY = [
        ['label' => 'Routinely, as scheduled', 'score' => 100],
        ['label' => 'Often but not consistently', 'score' => 70],
        ['label' => 'Occasionally', 'score' => 35],
        ['label' => 'Never', 'score' => 0],
    ];

    public function run(): void
    {
        $publishing = app(QuestionVersionPublishingService::class);
        $types = QuestionType::pluck('type_id', 'type_code');
        $modules = AssessmentModule::where('target_type_code', 'HEALTH_FACILITY')
            ->pluck('module_id', 'module_code');

        $published = 0;
        $skipped = 0;

        foreach (self::questions() as $definition) {
            $moduleId = $modules[$definition['module']] ?? null;

            if (! $moduleId) {
                $this->command?->warn("Department {$definition['module']} missing; skipped {$definition['code']}.");
                $skipped++;

                continue;
            }

            $result = DB::transaction(fn () => $this->publishQuestion($definition, $moduleId, $types, $publishing));

            $result ? $published++ : $skipped++;
        }

        $this->command?->info("Official question library: {$published} published, {$skipped} skipped.");
    }

    /**
     * Creates the identity and its first version, then takes it through approval and
     * publication. Idempotent: an already published question is left alone, because a
     * published version is immutable and re-publishing would be a governance violation
     * rather than a no-op.
     */
    private function publishQuestion(
        array $definition,
        int $moduleId,
        Collection $types,
        QuestionVersionPublishingService $publishing,
    ): bool {
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
            'numeric_config' => $definition['numeric'] ?? null,
            'requires_observation' => $definition['observe'] ?? false,
            'respondent_role_hint' => $definition['respondent'] ?? null,
            'methodology_notes' => $definition['why'] ?? null,
            'source_summary' => 'Vytte official methodology, informed by recognised international health assessment practice.',
        ]);

        $publishing->publish($version);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
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
                'critical_failure' => $option['critical'] ?? false,
            ];
        }

        return $payload;
    }

    /**
     * The cross-cutting core.
     *
     * These apply to almost any health facility regardless of what it specialises in,
     * which is why they are authored first: every framework in the library reuses them,
     * so their quality sets the floor for everything downstream.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function questions(): array
    {
        return array_merge(
            self::governance(),
            self::workforce(),
            self::qualityAndSafety(),
            self::infrastructure(),
            self::information(),
            self::financing(),
            self::personCentredness(),
            self::infectionPrevention(),
            self::wash(),
            self::hiv(),
            self::tuberculosis(),
            self::malaria(),
            self::immunization(),
            self::dataBurden(),
            self::maternalNewborn(),
            self::childHealth(),
            self::nutrition(),
            self::mentalHealth(),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private static function governance(): array
    {
        $respondent = 'Facility Manager · Medical Director · Board Member';

        return [
            ['code' => 'GOV.001', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility have a current written strategic or annual operational plan?',
                'why' => 'A facility without a plan cannot show what it intends to achieve, and improvement work has nothing to attach to.',
                'evidence' => 'Sight the plan and check the period it covers.'],
            ['code' => 'GOV.002', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a named person accountable for overall facility performance?',
                'why' => 'Accountability that is not assigned to a person tends not to happen.'],
            ['code' => 'GOV.003', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly does facility management meet to review performance?',
                'why' => 'Distinguishes facilities that review performance on a rhythm from those that react to problems.'],
            ['code' => 'GOV.004', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are minutes or records kept of management meetings, including decisions taken?',
                'why' => 'Decisions that are not recorded cannot be followed up, and no one can tell whether they were acted on.',
                'evidence' => 'Sight minutes from the last three meetings.'],
            ['code' => 'GOV.005', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a functioning governing board, health committee or equivalent oversight body?',
                'why' => 'External oversight is what separates self-assessment from accountability.'],
            ['code' => 'GOV.006', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility have written policies covering its main clinical services?',
                'why' => 'Policies are how a facility states the standard it holds itself to.',
                'evidence' => 'Ask to see two policies at random and check they are current.'],
            ['code' => 'GOV.007', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are staff able to locate the policies relevant to their own work?',
                'why' => 'A policy nobody can find is not in force, whatever the folder says. Asks about reality rather than existence.',
                'observe' => true],
            ['code' => 'GOV.008', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly are written policies reviewed and updated?',
                'why' => 'An out-of-date policy can be worse than none, because it legitimises outdated practice.'],
            ['code' => 'GOV.009', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a documented process for handling complaints from patients or the community?',
                'why' => 'Complaints are the cheapest source of improvement information a facility has.'],
            ['code' => 'GOV.010', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Can the facility show that complaints received in the last year led to a recorded action?',
                'why' => 'Separates a complaints box from a complaints process.',
                'evidence' => 'Sight the complaints register and trace two entries to an action.'],
            ['code' => 'GOV.011', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a current operating licence or registration for the facility?',
                'why' => 'A basic legal condition of operating, and commonly the first thing a regulator checks.',
                'evidence' => 'Sight the licence and check the expiry date.'],
            ['code' => 'GOV.012', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_NO, 'respondent' => $respondent,
                'text' => 'Has the facility been formally inspected or accredited in the last three years?',
                'why' => 'Establishes whether the facility is inside or outside any external assurance cycle.'],
            ['code' => 'GOV.013', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Were the findings of the most recent external inspection acted upon?',
                'why' => 'An inspection that changes nothing is an expense, not a control.'],
            ['code' => 'GOV.014', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a written code of conduct or ethics policy that staff are aware of?',
                'why' => 'Sets the boundary for professional behaviour and is the reference point when it is breached.'],
            ['code' => 'GOV.015', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a process for staff to raise concerns about unsafe or unethical practice without fear of reprisal?',
                'why' => 'Facilities where concerns cannot be raised safely discover problems through harm rather than through reporting.'],
            ['code' => 'GOV.016', 'module' => 'GOV', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility engage the community it serves in planning or reviewing services?',
                'why' => 'Community input is what keeps services aligned to the population rather than to the institution.'],
            ['code' => 'GOV.017', 'module' => 'GOV', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What is the single greatest obstacle to running this facility well at present?',
                'why' => 'An open question placed deliberately. The scored questions establish the position; this one often explains it.'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function workforce(): array
    {
        $respondent = 'HR Officer · Matron · Facility Manager';

        return [
            ['code' => 'WRK.001', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility have a current staffing establishment showing approved posts?',
                'why' => 'Without an establishment there is no way to say whether the facility is understaffed or simply small.'],
            ['code' => 'WRK.002', 'module' => 'HRM', 'type' => 'NUMERIC', 'numeric' => ['unit' => 'staff', 'min' => 0, 'max' => 5000], 'respondent' => $respondent,
                'text' => 'How many clinical staff are currently in post?',
                'why' => 'The denominator for most workforce judgements. Counted rather than estimated.'],
            ['code' => 'WRK.003', 'module' => 'HRM', 'type' => 'NUMERIC', 'numeric' => ['unit' => 'posts', 'min' => 0, 'max' => 5000], 'respondent' => $respondent,
                'text' => 'How many approved clinical posts are currently vacant?',
                'why' => 'Vacancy against establishment is the clearest single workforce signal.'],
            ['code' => 'WRK.004', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Is at least one clinically qualified staff member present whenever the facility is open?',
                'why' => 'A facility open without qualified cover is a safety failure regardless of how it scores elsewhere, which is why a negative answer is treated as critical.',
                'observe' => true],
            ['code' => 'WRK.005', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Do all clinical staff hold current professional registration for their role?',
                'why' => 'Registration lapses quietly and is rarely noticed until an incident or an inspection.',
                'evidence' => 'Check registration for a sample of three clinical staff.'],
            ['code' => 'WRK.006', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does every staff member have a written job description?',
                'why' => 'Role clarity is the foundation of both supervision and accountability.'],
            ['code' => 'WRK.007', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly do staff receive supportive supervision?',
                'why' => 'Supervision is the main mechanism by which clinical standards are maintained between trainings.'],
            ['code' => 'WRK.008', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are supervision visits documented, including any actions agreed?',
                'why' => 'Undocumented supervision cannot be followed up and tends to repeat the same findings.',
                'evidence' => 'Sight supervision records for the last six months.'],
            ['code' => 'WRK.009', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Have staff received training relevant to their role in the last twelve months?',
                'why' => 'Distinguishes facilities investing in capability from those relying on what staff arrived with.'],
            ['code' => 'WRK.010', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a written induction process for new staff?',
                'why' => 'The period just after joining is when avoidable errors cluster.'],
            ['code' => 'WRK.011', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a duty roster covering all operating hours, including nights and weekends?',
                'why' => 'Gaps in cover usually appear at the edges of the week rather than in the middle.',
                'observe' => true],
            ['code' => 'WRK.012', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are staff paid accurately and on time?',
                'why' => 'Late or wrong pay is one of the strongest predictors of absenteeism and attrition, and is rarely captured by clinical questions.'],
            ['code' => 'WRK.013', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a documented performance appraisal process, and has it been used in the last year?',
                'why' => 'Appraisal is where individual performance and facility performance are supposed to meet.'],
            ['code' => 'WRK.014', 'module' => 'HRM', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How available is a clinical supervisor or senior clinician for advice when needed?',
                'why' => 'Access to a second opinion is what prevents an uncertain junior decision becoming an incident.'],
            ['code' => 'WRK.015', 'module' => 'HRM', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'Which staffing gap most affects the services this facility can offer?',
                'why' => 'Vacancy counts show the size of the gap; this shows which gap actually bites.'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function qualityAndSafety(): array
    {
        $respondent = 'Quality Focal Person · Matron · Medical Director';

        return [
            ['code' => 'QAS.001', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a named person or team responsible for quality improvement?',
                'why' => 'Quality without an owner reverts to whoever happens to care that week.'],
            ['code' => 'QAS.002', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility record adverse events, incidents or near misses?',
                'why' => 'A facility that records no incidents is almost never a facility where none occur.'],
            ['code' => 'QAS.003', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are recorded incidents reviewed to identify why they happened?',
                'why' => 'Recording without review produces a register, not a safety system.',
                'evidence' => 'Sight the incident register and trace one entry to a review.'],
            ['code' => 'QAS.004', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Can staff describe how to report an incident?',
                'why' => 'Asks staff rather than management, because a reporting process staff cannot describe is not operating.',
                'observe' => true],
            ['code' => 'QAS.005', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there evidence that a change was made as a result of an incident review in the last year?',
                'why' => 'The whole point of incident reporting, and the step most often missing.'],
            ['code' => 'QAS.006', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly does the facility review its own clinical performance data?',
                'why' => 'Distinguishes facilities that measure themselves from those measured only by others.'],
            ['code' => 'QAS.007', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Has the facility completed a structured quality improvement project in the last two years?',
                'why' => 'Evidence of improvement capability rather than improvement intention.'],
            ['code' => 'QAS.008', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are clinical guidelines or protocols available at the point of care?',
                'why' => 'Guidelines kept in an office are not decision support.',
                'observe' => true],
            ['code' => 'QAS.009', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is patient identity confirmed before treatment or medication is given?',
                'why' => 'Misidentification is among the most common preventable causes of harm.',
                'observe' => true],
            ['code' => 'QAS.010', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Are medicines stored so that look-alike and sound-alike products cannot be confused?',
                'why' => 'A recognised and preventable cause of serious medication error, which is why a negative answer is critical.',
                'observe' => true],
            ['code' => 'QAS.011', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a process for obtaining and recording informed consent for procedures?',
                'why' => 'Consent is both an ethical requirement and the most common documentation gap found on audit.'],
            ['code' => 'QAS.012', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility collect feedback directly from patients?',
                'why' => 'Patient-reported experience captures failures that clinical audit does not see.'],
            ['code' => 'QAS.013', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are patients told what is wrong with them and what treatment they are receiving, in a language they understand?',
                'why' => 'Person-centred care in its most basic form, and frequently absent where consultations are rushed.'],
            ['code' => 'QAS.014', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is patient privacy maintained during consultation and examination?',
                'why' => 'Privacy failures deter people from returning, particularly for stigmatised conditions.',
                'observe' => true],
            ['code' => 'QAS.015', 'module' => 'QAS', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are patient records kept confidential and secure from unauthorised access?',
                'why' => 'Confidentiality is a legal obligation in most settings and a trust condition in all of them.',
                'observe' => true],
            ['code' => 'QAS.016', 'module' => 'QAS', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What quality or safety problem would staff here most want fixed?',
                'why' => 'Staff usually know the answer before any assessment starts. Asking directly is cheaper than inferring it.'],
        ];
    }

    /**
     * Infrastructure, equipment and supplies.
     *
     * The physical conditions in which care happens. A recurring finding in facility
     * assessments worldwide is that clinical competence is undone by a power cut, an empty
     * water tank or a broken steriliser, so these are scored in their own right.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function infrastructure(): array
    {
        $respondent = 'Facility Manager · Maintenance Officer · Matron';

        return [
            ['code' => 'INF.001', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How reliable is the electricity supply during opening hours?',
                'why' => 'Power failure stops sterilisation, cold chain, lighting for procedures and most diagnostics at once.',
                'observe' => true],
            ['code' => 'INF.002', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Is there a functioning backup power source for essential services?',
                'why' => 'For a facility providing inpatient or emergency care, no backup power is a critical exposure rather than a weakness.',
                'observe' => true],
            ['code' => 'INF.003', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How reliable is the supply of safe water to the facility?',
                'why' => 'Without water there is no hand hygiene, no cleaning and no safe procedures.',
                'observe' => true],
            ['code' => 'INF.004', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there water storage sufficient to continue services through a supply interruption?',
                'why' => 'Reliability today is not the same as resilience tomorrow.',
                'observe' => true],
            ['code' => 'INF.005', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are the buildings structurally sound and weatherproof?',
                'why' => 'A leaking roof or cracked wall affects infection control, records and equipment together.',
                'observe' => true],
            ['code' => 'INF.006', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there adequate lighting in clinical areas, including at night if the facility operates then?',
                'why' => 'Poor lighting causes procedural and dispensing errors that are entirely preventable.',
                'observe' => true],
            ['code' => 'INF.007', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there adequate ventilation in clinical and waiting areas?',
                'why' => 'Ventilation is a first-line control against airborne transmission, particularly of TB.',
                'observe' => true],
            ['code' => 'INF.008', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are there enough consultation spaces to see patients privately?',
                'why' => 'Space shortage is a common and unspoken cause of privacy and dignity failures.',
                'observe' => true],
            ['code' => 'INF.009', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is essential clinical equipment for the services offered available and functioning?',
                'why' => 'The point where stated services and actual capability most often diverge.',
                'observe' => true],
            ['code' => 'INF.010', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a maintenance schedule for clinical equipment, and is it followed?',
                'why' => 'Equipment without planned maintenance fails when it is needed rather than when it is convenient.',
                'evidence' => 'Sight the maintenance log for two pieces of equipment.'],
            ['code' => 'INF.011', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a process for reporting and repairing broken equipment?',
                'why' => 'A broken item with no repair route becomes permanent, whatever the maintenance plan says.'],
            ['code' => 'INF.012', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there functioning equipment to sterilise or decontaminate reusable instruments?',
                'why' => 'Reprocessing failure turns reusable instruments into a transmission route.',
                'observe' => true],
            ['code' => 'INF.013', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are essential medicines in stock?',
                'why' => 'The single most visible measure of readiness to the patient in front of you.'],
            ['code' => 'INF.014', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a system to track stock levels and reorder before items run out?',
                'why' => 'Distinguishes facilities that manage stock from those that discover a stockout at the point of care.'],
            ['code' => 'INF.015', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are medicines and supplies stored at the correct temperature and protected from damage?',
                'why' => 'Poor storage silently degrades medicines that then fail when used.',
                'observe' => true],
            ['code' => 'INF.016', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Are expired medicines removed from use and stored separately for disposal?',
                'why' => 'Expired stock mixed with usable stock is a direct route to patient harm, hence critical.',
                'observe' => true],
            ['code' => 'INF.017', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a functioning cold chain for items that require it?',
                'why' => 'Vaccines and some medicines are worthless, and can be harmful, if the cold chain breaks.',
                'observe' => true],
            ['code' => 'INF.018', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is cold chain temperature monitored and recorded?',
                'why' => 'A fridge that is on is not the same as a fridge holding the right temperature.',
                'evidence' => 'Sight the temperature log for the last two weeks.'],
            ['code' => 'INF.019', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are functioning communication means available to summon help or make referrals?',
                'why' => 'A referral decision is only as good as the ability to act on it.'],
            ['code' => 'INF.020', 'module' => 'INF', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'Which single piece of infrastructure or equipment, if fixed, would most improve services here?',
                'why' => 'Turns a list of gaps into the one that matters most to the people running the facility.'],
        ];
    }

    /**
     * Information, records and their use.
     *
     * A facility that cannot trust its own numbers cannot manage itself, and its reports
     * mislead everyone above it. These separate whether records exist from whether they
     * are accurate and, hardest of all, whether they are used.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function information(): array
    {
        $respondent = 'Records Officer · Data Clerk · Facility Manager';

        return [
            ['code' => 'INFO.001', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility keep a record for every patient it sees?',
                'why' => 'Continuity of care is impossible without a record to continue from.'],
            ['code' => 'INFO.002', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Can a patient record be retrieved quickly when the patient returns?',
                'why' => 'Records that cannot be found in time are, at the moment of care, records that do not exist.',
                'observe' => true],
            ['code' => 'INFO.003', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are the registers required for routine reporting complete and up to date?',
                'why' => 'Incomplete registers make every derived report wrong, however good the reporting process.',
                'evidence' => 'Check two registers for gaps in the last month.'],
            ['code' => 'INFO.004', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How consistently are required reports submitted on time?',
                'why' => 'Late reporting distorts the wider health information system, not just this facility.'],
            ['code' => 'INFO.005', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a process to check reported figures against source records?',
                'why' => 'The core of data quality: whether anyone verifies that the number sent up matches the register.'],
            ['code' => 'INFO.006', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'When errors are found in the data, are they corrected and the cause addressed?',
                'why' => 'Finding errors is common; fixing why they occur is what separates data quality from data cleaning.'],
            ['code' => 'INFO.007', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly does the facility review its own service data to guide decisions?',
                'why' => 'The hardest and most valuable step: data used for management rather than only for reporting upward.'],
            ['code' => 'INFO.008', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Can staff give an example of a decision changed by their own data in the last year?',
                'why' => 'Tests data use as a lived practice rather than a claimed one.',
                'observe' => true],
            ['code' => 'INFO.009', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are patient records stored securely and protected from loss or damage?',
                'why' => 'Records lost to fire, water or pests take the facility memory with them.',
                'observe' => true],
            ['code' => 'INFO.010', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_NO, 'respondent' => $respondent,
                'text' => 'Does the facility use any electronic system for records or reporting?',
                'why' => 'Establishes the digital starting point without assuming paper is a failure.'],
            ['code' => 'INFO.011', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Where electronic systems are used, are staff trained and confident in using them?',
                'why' => 'A system staff cannot use well produces worse data than the paper it replaced.'],
            ['code' => 'INFO.012', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a backup for electronic data against device failure or loss?',
                'why' => 'Digitising records concentrates the risk of losing them all at once.'],
            ['code' => 'INFO.013', 'module' => 'REC', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What would make the facility data more useful to the people who work here?',
                'why' => 'Data systems are usually designed for those above the facility; this asks those inside it.'],
        ];
    }

    /**
     * Financing and resource management.
     *
     * The WHO building block with the least visible presence in most facility tools, and
     * the one that quietly determines whether everything else is sustainable. Written to
     * be answerable by a facility that does not control its own budget, because most do not.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function financing(): array
    {
        $respondent = 'Facility Manager · Finance Officer · Administrator';

        return [
            ['code' => 'FIN.001', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility have a budget covering its operating costs?',
                'why' => 'A facility running without a budget is managing money by reaction rather than by plan.'],
            ['code' => 'FIN.002', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility have any discretion over how at least part of its funds are spent?',
                'why' => 'Distinguishes a facility that can respond to its own priorities from one that can only spend as directed.'],
            ['code' => 'FIN.003', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are income and expenditure recorded and reconciled?',
                'why' => 'Money that is not recorded cannot be accounted for, and unaccounted money is where trust fails.',
                'evidence' => 'Sight the most recent financial records.'],
            ['code' => 'FIN.004', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Where fees are charged, is the schedule of charges displayed openly to patients?',
                'why' => 'Hidden or informal charges are a leading source of both corruption and lost trust.',
                'observe' => true],
            ['code' => 'FIN.005', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a mechanism to protect the poorest patients from being turned away over cost?',
                'why' => 'Financial protection is a stated aim of most health systems and a common gap at the front line.'],
            ['code' => 'FIN.006', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Where the facility participates in health insurance, are claims submitted and paid reliably?',
                'why' => 'Insurance income that arrives late or not at all can destabilise a facility that has come to rely on it.'],
            ['code' => 'FIN.007', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How predictable is the funding the facility receives?',
                'why' => 'Unpredictable funding makes planning impossible regardless of the amount.'],
            ['code' => 'FIN.008', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is spending subject to any independent check or audit?',
                'why' => 'Oversight is what makes financial records trustworthy rather than merely present.'],
            ['code' => 'FIN.009', 'module' => 'FIN', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Can the facility meet an unexpected essential cost, such as an urgent repair?',
                'why' => 'Tests financial resilience, which is invisible until the moment it is needed.'],
            ['code' => 'FIN.010', 'module' => 'FIN', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What is the most significant financial constraint on services here?',
                'why' => 'Financial constraints are often the real cause behind findings that look clinical.'],
        ];
    }

    /**
     * Person-centredness and community responsiveness.
     *
     * Whether the facility is experienced as respectful, accessible and answerable to the
     * people it serves. These matter because a clinically capable facility people avoid,
     * distrust or cannot reach delivers no health.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function personCentredness(): array
    {
        $respondent = 'Matron · Community Health Officer · Facility Manager';

        return [
            ['code' => 'PCOM.001', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are opening hours suited to the community the facility serves?',
                'why' => 'Hours set for staff convenience rather than patient need are a quiet barrier to access.'],
            ['code' => 'PCOM.002', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Can patients understand the language staff use with them?',
                'why' => 'Care delivered in a language the patient does not follow is care they cannot consent to or act on.'],
            ['code' => 'PCOM.003', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are patients treated with courtesy and respect by staff?',
                'why' => 'Disrespectful treatment is one of the most common reasons people stop using a facility.',
                'observe' => true],
            ['code' => 'PCOM.004', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is the facility physically accessible to people with mobility difficulties?',
                'why' => 'Steps, narrow doors and high counters exclude people before any clinical judgement is made.',
                'observe' => true],
            ['code' => 'PCOM.005', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are waiting times reasonable, and are patients told what to expect?',
                'why' => 'A long wait is more tolerable when it is explained; an unexplained one erodes trust.'],
            ['code' => 'PCOM.006', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are patients involved in decisions about their own care?',
                'why' => 'Shared decisions improve adherence and are a basic expression of respect.'],
            ['code' => 'PCOM.007', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Does the facility work with community health workers or community structures?',
                'why' => 'The link between facility and community is where prevention and follow-up succeed or fail.'],
            ['code' => 'PCOM.008', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly does the facility conduct outreach to its catchment population?',
                'why' => 'Outreach reaches the people least likely to come to the facility, who often need it most.'],
            ['code' => 'PCOM.009', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a way for the community to give feedback or raise concerns about services?',
                'why' => 'A facility with no feedback channel hears about problems only when they become complaints or departures.'],
            ['code' => 'PCOM.010', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are services offered without discrimination on grounds such as sex, age, disability or status?',
                'why' => 'Equitable treatment is both an ethical baseline and a determinant of who actually gets care.'],
            ['code' => 'PCOM.011', 'module' => 'COM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are health education or promotion messages provided to patients and the community?',
                'why' => 'Prevention is cheaper than treatment, and the facility is a trusted place to deliver it.'],
            ['code' => 'PCOM.012', 'module' => 'COM', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What do patients or the community most commonly complain about here?',
                'why' => 'The community usually already knows the facility weakest point; this records it in their words.'],
        ];
    }

    /**
     * Infection prevention and control.
     *
     * Structured around the WHO IPC minimum requirements: a programme and guidelines,
     * training, surveillance, multimodal strategy, monitoring, and the physical
     * enablers of hand hygiene, environment and reprocessing. IPC is where a single
     * failure harms many, so several answers here are treated as critical.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function infectionPrevention(): array
    {
        $respondent = 'IPC Focal Person · Matron · Medical Officer';

        return [
            ['code' => 'IPC.001', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a named person or team responsible for infection prevention and control?',
                'why' => 'The first WHO IPC minimum requirement. Without ownership, IPC is nobody\'s daily job.'],
            ['code' => 'IPC.002', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are written IPC guidelines available and relevant to the services provided?',
                'why' => 'Guidelines are the reference against which practice is judged and corrected.',
                'evidence' => 'Sight the IPC guidelines and check they cover the services offered.'],
            ['code' => 'IPC.003', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly do staff receive IPC training?',
                'why' => 'IPC practice decays without reinforcement; training frequency predicts adherence.'],
            ['code' => 'IPC.004', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Are functioning hand hygiene stations available at points of care?',
                'why' => 'Hand hygiene is the single most effective infection control measure; absence of the means to perform it is critical.',
                'observe' => true],
            ['code' => 'IPC.005', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are hand hygiene supplies — soap, water or alcohol rub — available?',
                'why' => 'A station without supplies is a fixture, not a control.',
                'observe' => true],
            ['code' => 'IPC.006', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Do staff perform hand hygiene at the recognised key moments during care?',
                'why' => 'Availability enables the behaviour; this checks the behaviour itself.',
                'observe' => true],
            ['code' => 'IPC.007', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently is appropriate personal protective equipment available to staff?',
                'why' => 'PPE gaps expose staff and patients alike, and were a defining failure of recent outbreaks.',
                'observe' => true],
            ['code' => 'IPC.008', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Do staff use PPE correctly for the tasks that require it?',
                'why' => 'Incorrectly used PPE offers false reassurance, which can be worse than none.',
                'observe' => true],
            ['code' => 'IPC.009', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Are sharps disposed of immediately into puncture-proof containers?',
                'why' => 'Unsafe sharps handling causes needlestick injury and bloodborne transmission; a serious and preventable failure.',
                'observe' => true],
            ['code' => 'IPC.010', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is reusable equipment cleaned, disinfected or sterilised correctly between patients?',
                'why' => 'Reprocessing failure turns shared instruments into a transmission route.',
                'observe' => true],
            ['code' => 'IPC.011', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a process to confirm that sterilisation has actually worked?',
                'why' => 'Running a steriliser is not the same as confirming a sterile result; indicators close that gap.',
                'evidence' => 'Ask how sterilisation is verified and sight the record.'],
            ['code' => 'IPC.012', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly are clinical surfaces and areas cleaned?',
                'why' => 'Environmental cleaning is a foundational and frequently neglected IPC control.'],
            ['code' => 'IPC.013', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are cleaning staff trained in IPC and provided with the right materials?',
                'why' => 'Cleaning is an IPC task, but the people doing it are often the least trained and equipped.'],
            ['code' => 'IPC.014', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there capacity to isolate or separate patients with a suspected transmissible infection?',
                'why' => 'Without separation, one infectious patient in a shared space becomes many.',
                'observe' => true],
            ['code' => 'IPC.015', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are healthcare-associated infections monitored in any form?',
                'why' => 'A facility that does not look for infections it may be causing cannot know whether IPC is working.'],
            ['code' => 'IPC.016', 'module' => 'INF', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly is IPC practice observed and audited?',
                'why' => 'IPC audit is the feedback loop the WHO multimodal strategy depends on.'],
            ['code' => 'IPC.017', 'module' => 'INF', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'Which infection control practice is hardest to maintain here, and why?',
                'why' => 'The honest answer usually points at a supply, space or staffing cause behind an IPC symptom.'],
        ];
    }

    /**
     * Water, sanitation, hygiene, waste and cleaning in health care facilities.
     *
     * Structured around WHO/UNICEF WASH FIT. Placed against the facility WASH department
     * rather than the school WASH module, because a health facility carries obligations a
     * school does not, particularly around health care waste.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function wash(): array
    {
        $respondent = 'Facility Manager · WASH Focal Person · Cleaner Supervisor';

        return [
            ['code' => 'WASH.001', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Is safe drinking water available to patients and staff at the facility?',
                'why' => 'A health facility that cannot provide safe water fails a basic condition of care; hence critical.',
                'observe' => true],
            ['code' => 'WASH.002', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How reliable is the water supply across the whole facility, including clinical areas?',
                'why' => 'Water present at a tap by the gate is not the same as water at the point of care.',
                'observe' => true],
            ['code' => 'WASH.003', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is the quality of the water source known and monitored?',
                'why' => 'Water that looks clean can still transmit disease; monitoring is what makes it safe.'],
            ['code' => 'WASH.004', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Are there functioning, clean and private toilets for patients?',
                'why' => 'Toilets that are absent, broken or unusable are a dignity failure and a disease risk at once.',
                'observe' => true],
            ['code' => 'WASH.005', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are there separate toilets for women and men, and for staff and patients?',
                'why' => 'Separation matters for dignity, safety and use, particularly for women.',
                'observe' => true],
            ['code' => 'WASH.006', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are toilets accessible to people with limited mobility?',
                'why' => 'An inaccessible toilet excludes the patients most likely to need inpatient care.',
                'observe' => true],
            ['code' => 'WASH.007', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are there facilities for managing menstrual hygiene?',
                'why' => 'A basic and routinely overlooked condition for women using or working in the facility.',
                'observe' => true],
            ['code' => 'WASH.008', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are hand washing facilities available at toilets and usable?',
                'why' => 'Sanitation without hand washing leaves the transmission route open.',
                'observe' => true],
            ['code' => 'WASH.009', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Is health care waste segregated at the point of generation into the correct categories?',
                'why' => 'Mixing sharps and infectious waste with general waste endangers staff, waste handlers and the community; a critical failure.',
                'observe' => true],
            ['code' => 'WASH.010', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are colour-coded or clearly labelled bins available where waste is generated?',
                'why' => 'Segregation is only possible if the means to segregate is present where waste arises.',
                'observe' => true],
            ['code' => 'WASH.011', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is health care waste treated and disposed of safely?',
                'why' => 'Segregation is undone if the end point is an open pit or uncontrolled burning.',
                'observe' => true],
            ['code' => 'WASH.012', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are staff who handle waste trained and given protective equipment?',
                'why' => 'Waste handlers carry real risk and are frequently the least protected people in the facility.'],
            ['code' => 'WASH.013', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly is the facility environment cleaned to a defined standard?',
                'why' => 'Environmental cleaning links WASH and IPC and is a common weak point in both.'],
            ['code' => 'WASH.014', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are cleaning materials and equipment available and maintained?',
                'why' => 'A cleaning standard with no materials to meet it is an aspiration.',
                'observe' => true],
            ['code' => 'WASH.015', 'module' => 'WSHF', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a WASH improvement plan based on an assessment of the facility?',
                'why' => 'WASH FIT is built around a cycle of assess, improve and monitor; the plan is what makes it a cycle.'],
            ['code' => 'WASH.016', 'module' => 'WSHF', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'Which WASH problem most affects patients or staff here?',
                'why' => 'WASH failures are highly visible to users and often known long before any assessment.'],
        ];
    }

    /**
     * HIV and PMTCT services.
     *
     * Half-scope programme coverage: enough to compose a credible HIV framework. Some
     * questions carry forward strong ideas from the PHSAI legacy questionnaire — notably
     * the confidentiality of the HIV register and the quality of PMTCT linkage — rewritten
     * from descriptive workflow into scored readiness.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function hiv(): array
    {
        $respondent = 'HIV Focal Person · PMTCT Focal Person · Nurse';

        return [
            ['code' => 'HIV.001', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_NO, 'respondent' => $respondent,
                'text' => 'Is HIV counselling and testing offered at this facility?',
                'why' => 'Establishes whether the service exists before anything about its quality is asked.'],
            ['code' => 'HIV.002', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are HIV test kits available?',
                'why' => 'A testing service without kits is a service on paper only.',
                'observe' => true],
            ['code' => 'HIV.003', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is pre- and post-test counselling provided in a private space?',
                'why' => 'Counselling overheard by others deters testing and breaches confidentiality at the most sensitive moment.',
                'observe' => true],
            ['code' => 'HIV.004', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => [
                ['label' => 'Separate, restricted-access HIV register', 'score' => 100],
                ['label' => 'Coded or de-identified entries', 'score' => 75],
                ['label' => 'Same general register as other patients', 'score' => 25],
                ['label' => 'No specific confidentiality measure', 'score' => 0, 'critical' => true],
            ], 'respondent' => $respondent,
                'text' => 'How is the confidentiality of HIV records maintained?',
                'why' => 'HIV status recorded without access control exposes patients to stigma and harm; no measure at all is a critical failure. Carried forward and strengthened from legacy content.'],
            ['code' => 'HIV.005', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => [
                ['label' => 'Routine ANC testing with same-day linkage to care', 'score' => 100],
                ['label' => 'Testing done, linkage delayed or tracked separately', 'score' => 60],
                ['label' => 'Referred to a separate HIV clinic', 'score' => 40],
                ['label' => 'No standardised linkage process', 'score' => 0, 'critical' => true],
            ], 'respondent' => $respondent,
                'text' => 'How are HIV-positive pregnant women identified and linked to care?',
                'why' => 'The break between testing positive and starting treatment is where PMTCT most often fails. Rewritten from legacy content into a scored judgement.'],
            ['code' => 'HIV.006', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is antiretroviral treatment initiated and continued for eligible patients?',
                'why' => 'Testing without treatment access is diagnosis without care.'],
            ['code' => 'HIV.007', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are antiretroviral medicines in stock?',
                'why' => 'An ART stockout forces treatment interruption, which drives resistance and transmission.',
                'observe' => true],
            ['code' => 'HIV.008', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a system to trace patients who miss appointments?',
                'why' => 'Retention is the hardest part of HIV care and the one that determines outcomes.'],
            ['code' => 'HIV.009', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is viral load testing accessible for patients on treatment?',
                'why' => 'Viral load is the measure of whether treatment is working; without it, care is managed blind.'],
            ['code' => 'HIV.010', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are HIV prevention commodities, such as condoms or PrEP where offered, available?',
                'why' => 'Prevention is the cheapest point of the whole HIV response.'],
            ['code' => 'HIV.011', 'module' => 'HTB', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What is the most significant obstacle to HIV or PMTCT services here?',
                'why' => 'Programme staff usually know the binding constraint before any assessment starts.'],
        ];
    }

    /**
     * Tuberculosis services.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function tuberculosis(): array
    {
        $respondent = 'TB Focal Person · Medical Officer · Laboratory Scientist';

        return [
            ['code' => 'TB.001', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_NO, 'respondent' => $respondent,
                'text' => 'Is TB screening offered at this facility?',
                'why' => 'The entry point to the TB cascade.'],
            ['code' => 'TB.002', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are patients with a cough or TB symptoms routinely screened for TB?',
                'why' => 'Passive detection misses cases; routine symptom screening is what finds them.'],
            ['code' => 'TB.003', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => [
                ['label' => 'GeneXpert or molecular testing on site', 'score' => 100],
                ['label' => 'Sputum microscopy on site', 'score' => 70],
                ['label' => 'Referral to another facility only', 'score' => 40],
                ['label' => 'No diagnostic access', 'score' => 0, 'critical' => true],
            ], 'respondent' => $respondent,
                'text' => 'What TB diagnostic capacity does the facility have?',
                'why' => 'Diagnostic delay drives transmission; no access at all is a critical gap. Improved from legacy content.'],
            ['code' => 'TB.004', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are first-line TB medicines available?',
                'why' => 'A TB treatment interruption risks resistance, which is far costlier to treat.',
                'observe' => true],
            ['code' => 'TB.005', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is treatment adherence supported and monitored through to completion?',
                'why' => 'TB is cured by completing a long course, not by starting one.'],
            ['code' => 'TB.006', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are TB patients routinely offered an HIV test, and vice versa?',
                'why' => 'The two infections drive each other; separating their services misses co-infection.'],
            ['code' => 'TB.007', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Are TB infection control measures in place in waiting and consultation areas?',
                'why' => 'A TB service with no infection control can transmit the disease it treats; hence critical.',
                'observe' => true],
            ['code' => 'TB.008', 'module' => 'HTB', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are TB treatment outcomes recorded and reviewed?',
                'why' => 'Outcome review is how a programme learns whether its patients are actually being cured.'],
            ['code' => 'TB.009', 'module' => 'HTB', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What is the most significant obstacle to TB services here?',
                'why' => 'Names the binding constraint in the words of the people running the service.'],
        ];
    }

    /**
     * Malaria services.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function malaria(): array
    {
        $respondent = 'Medical Officer · Nurse · Laboratory Scientist';

        return [
            ['code' => 'MAL.001', 'module' => 'MAL', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Is malaria diagnosis confirmed by test before treatment, rather than treated on symptoms alone?',
                'why' => 'Treating without testing wastes medicine, misses other causes of fever and drives resistance; the core of current malaria policy.'],
            ['code' => 'MAL.002', 'module' => 'MAL', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are malaria rapid diagnostic tests or microscopy available?',
                'why' => 'Test-based treatment is impossible without a functioning test supply.',
                'observe' => true],
            ['code' => 'MAL.003', 'module' => 'MAL', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently is first-line antimalarial treatment in stock?',
                'why' => 'A confirmed diagnosis with no medicine to follow it is a diagnosis wasted.',
                'observe' => true],
            ['code' => 'MAL.004', 'module' => 'MAL', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is severe malaria recognised and either treated or referred promptly?',
                'why' => 'Severe malaria kills within hours; recognition and speed are what save lives.'],
            ['code' => 'MAL.005', 'module' => 'MAL', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently is injectable artesunate available for severe malaria?',
                'why' => 'The single most important commodity for surviving severe malaria.',
                'observe' => true],
            ['code' => 'MAL.006', 'module' => 'MAL', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is malaria in pregnancy prevented and managed according to guidelines?',
                'why' => 'Pregnant women and their babies carry the heaviest malaria risk.'],
            ['code' => 'MAL.007', 'module' => 'MAL', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are insecticide-treated nets or other prevention commodities provided where indicated?',
                'why' => 'Prevention averts the case that never has to be treated.'],
            ['code' => 'MAL.008', 'module' => 'MAL', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are malaria cases recorded and reported for surveillance?',
                'why' => 'Case data is how outbreaks are seen and how the programme is steered.'],
            ['code' => 'MAL.009', 'module' => 'MAL', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What most limits malaria diagnosis or treatment here?',
                'why' => 'Surfaces the local constraint behind the numbers.'],
        ];
    }

    /**
     * Immunization services.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function immunization(): array
    {
        $respondent = 'Immunization Focal Person · Nurse · CHEW';

        return [
            ['code' => 'IMM.001', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly are routine immunization sessions held?',
                'why' => 'Session frequency determines how easily a caregiver can complete a child\'s schedule.'],
            ['code' => 'IMM.002', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are the vaccines in the national schedule available?',
                'why' => 'A missed vaccine is a child left unprotected and a caregiver who may not return.',
                'observe' => true],
            ['code' => 'IMM.003', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Is there a functioning cold chain maintaining vaccines at the correct temperature?',
                'why' => 'Vaccines exposed to wrong temperatures are silently inactivated; giving them is worse than giving nothing, so failure is critical.',
                'observe' => true],
            ['code' => 'IMM.004', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly is cold chain temperature recorded?',
                'why' => 'A fridge that is on is not the same as a fridge proven to hold temperature.',
                'evidence' => 'Sight the temperature log for the last two weeks.'],
            ['code' => 'IMM.005', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a backup plan to protect vaccines during a power failure?',
                'why' => 'Power is unreliable in much of the market; the backup is what saves the stock.'],
            ['code' => 'IMM.006', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are children who miss scheduled vaccines identified and followed up?',
                'why' => 'Defaulter tracing is the difference between starting and completing the schedule.'],
            ['code' => 'IMM.007', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are adverse events following immunization recognised and reported?',
                'why' => 'AEFI reporting protects both the child and public confidence in the programme.'],
            ['code' => 'IMM.008', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is safe injection and sharps disposal practised in immunization sessions?',
                'why' => 'High injection volumes make immunization a real sharps-injury and transmission risk if handled poorly.',
                'observe' => true],
            ['code' => 'IMM.009', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How regularly does the facility conduct immunization outreach?',
                'why' => 'Fixed sessions miss the children furthest from the facility, who are often the least protected.'],
            ['code' => 'IMM.010', 'module' => 'IMM', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What most limits immunization coverage in this area?',
                'why' => 'Coverage gaps have local causes — distance, stockouts, hesitancy — that a number cannot show.'],
        ];
    }

    /**
     * Documentation and data burden.
     *
     * The valuable seam from the PHSAI legacy questionnaire, rewritten from descriptive
     * workflow into scored findings. Vytte's information questions ask whether records are
     * accurate and used; these ask how much duplicate effort producing them costs, which
     * is a distinct and common operational drain that nothing else in the library captures.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function dataBurden(): array
    {
        $respondent = 'Records Officer · Nurse in Charge · Data Clerk';

        return [
            ['code' => 'BURD.001', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => [
                ['label' => 'Entered once and shared', 'score' => 100],
                ['label' => 'Entered twice', 'score' => 60],
                ['label' => 'Entered three or more times', 'score' => 20],
                ['label' => 'Entered in many registers separately', 'score' => 0],
            ], 'respondent' => $respondent,
                'text' => 'How many times is the same patient information written into different registers?',
                'why' => 'Duplicate entry is unpaid, error-prone work that steals time from care. The core insight the legacy questionnaire captured and the official library did not.'],
            ['code' => 'BURD.002', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::FREQUENCY, 'respondent' => $respondent,
                'text' => 'How often do staff complete documentation outside working hours to keep up?',
                'why' => 'After-hours documentation is a hidden sign that the recording burden exceeds the working day.'],
            ['code' => 'BURD.003', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => [
                ['label' => 'One or two', 'score' => 100],
                ['label' => 'Three to five', 'score' => 70],
                ['label' => 'Six to ten', 'score' => 35],
                ['label' => 'More than ten', 'score' => 0],
            ], 'respondent' => $respondent,
                'text' => 'How many separate registers or forms must be completed for a single patient encounter?',
                'why' => 'Register proliferation is a measurable, reducible drain that few facilities have ever counted.'],
            ['code' => 'BURD.004', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Do staff report that documentation takes time away from patient care?',
                'why' => 'The trade-off between recording and caring is the real cost of a heavy data burden.'],
            ['code' => 'BURD.005', 'module' => 'REC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Has any register or form been removed or merged in the last two years to reduce duplication?',
                'why' => 'Distinguishes facilities that actively manage their data burden from those that only accumulate it.'],
            ['code' => 'BURD.006', 'module' => 'REC', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'Which single register or form causes the most duplicated effort, and why?',
                'why' => 'Points directly at the highest-value target for reducing the recording burden.'],
        ];
    }

    /**
     * Maternal and newborn care.
     *
     * Antenatal, delivery, emergency obstetric and newborn care. Several questions carry
     * forward strong clinical ideas from the PHSAI legacy antenatal questionnaire — the
     * structured identification of high-risk pregnancy, the availability of the ANC card
     * on return, and escalation of complications — rewritten into scored readiness.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function maternalNewborn(): array
    {
        $respondent = 'Midwife · Nurse-Midwife · Medical Officer';

        return [
            ['code' => 'MAT.001', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is antenatal care provided according to the recommended schedule of contacts?',
                'why' => 'Contact frequency is the backbone of catching problems in pregnancy before they become emergencies.'],
            ['code' => 'MAT.002', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is there a structured tool for identifying high-risk pregnancies?',
                'why' => 'High-risk pregnancies missed at antenatal care become the emergencies that kill. Carried forward from legacy content as a scored capability.'],
            ['code' => 'MAT.003', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are high-risk pregnancies clearly flagged so any clinician seeing the woman knows?',
                'why' => 'Identifying risk is worthless if the flag is not visible at the next contact.',
                'observe' => true],
            ['code' => 'MAT.004', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'Is the woman\'s antenatal record available when she returns for care?',
                'why' => 'Care without the prior record repeats work and misses the risks already found. Carried forward from legacy content.',
                'observe' => true],
            ['code' => 'MAT.005', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Is a skilled birth attendant available for deliveries whenever the facility conducts them?',
                'why' => 'A delivery service without a skilled attendant is where preventable maternal and newborn deaths occur; hence critical.'],
            ['code' => 'MAT.006', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is the facility able to provide or promptly refer for emergency obstetric care?',
                'why' => 'Most maternal deaths are from complications that are survivable with timely emergency care.'],
            ['code' => 'MAT.007', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are the medicines for managing obstetric emergencies available?',
                'why' => 'Drugs for haemorrhage and pre-eclampsia decide whether a complication is survived.',
                'observe' => true],
            ['code' => 'MAT.008', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is immediate newborn care, including warmth, early feeding and resuscitation readiness, provided?',
                'why' => 'The first minutes of life carry the highest newborn risk.'],
            ['code' => 'MAT.009', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is postnatal care provided for mother and baby after delivery?',
                'why' => 'The postnatal period is neglected in many settings yet carries substantial late risk.'],
            ['code' => 'MAT.010', 'module' => 'ANC', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are maternal and newborn deaths reviewed to learn what happened?',
                'why' => 'Death review is how a service turns a tragedy into a prevented next one.'],
            ['code' => 'MAT.011', 'module' => 'ANC', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What most threatens safe maternal or newborn care here?',
                'why' => 'The staff conducting deliveries know the weakest link before any assessment.'],
        ];
    }

    /**
     * Child health.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function childHealth(): array
    {
        $respondent = 'Nurse · CHEW · Medical Officer';

        return [
            ['code' => 'CHD.001', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are sick children assessed using an integrated approach covering the common danger signs?',
                'why' => 'Integrated assessment catches the serious illness hiding behind a common presenting complaint.'],
            ['code' => 'CHD.002', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Are danger signs requiring urgent referral recognised and acted on?',
                'why' => 'A missed danger sign in a child is a preventable death; hence critical.'],
            ['code' => 'CHD.003', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is every child\'s growth monitored and plotted?',
                'why' => 'Growth faltering is the earliest visible sign of a child in trouble.'],
            ['code' => 'CHD.004', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is the immunization status of every child checked at each contact?',
                'why' => 'Every visit is a chance to catch a child up on missed vaccines.'],
            ['code' => 'CHD.005', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are essential child health medicines available?',
                'why' => 'Oral rehydration, zinc, antibiotics and antimalarials are cheap and decisive when in stock.',
                'observe' => true],
            ['code' => 'CHD.006', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is care for the newborn and young infant provided or promptly referred?',
                'why' => 'The youngest infants deteriorate fastest and need the lowest threshold to act.'],
            ['code' => 'CHD.007', 'module' => 'IMM', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are caregivers advised on danger signs to watch for at home?',
                'why' => 'The caregiver is the first line of detection between visits.'],
            ['code' => 'CHD.008', 'module' => 'IMM', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What most limits child health care here?',
                'why' => 'Names the local constraint on caring for children.'],
        ];
    }

    /**
     * Nutrition services.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function nutrition(): array
    {
        $respondent = 'Nutrition Focal Person · Nurse · CHEW';

        return [
            ['code' => 'NUT.001', 'module' => 'NUT', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are children screened for acute malnutrition using a recognised measure?',
                'why' => 'Malnutrition unrecognised is malnutrition untreated; screening is the entry point.'],
            ['code' => 'NUT.002', 'module' => 'NUT', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO_CRITICAL, 'respondent' => $respondent,
                'text' => 'Is severe acute malnutrition with complications recognised and treated or referred urgently?',
                'why' => 'Severe acute malnutrition has a high untreated mortality; a missed case can be fatal, hence critical.'],
            ['code' => 'NUT.003', 'module' => 'NUT', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are therapeutic and supplementary foods available?',
                'why' => 'The treatment for malnutrition is food designed for it; without stock, diagnosis leads nowhere.',
                'observe' => true],
            ['code' => 'NUT.004', 'module' => 'NUT', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Is infant and young child feeding counselling, including breastfeeding support, provided?',
                'why' => 'Feeding practice in the first two years shapes a lifetime of health.'],
            ['code' => 'NUT.005', 'module' => 'NUT', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are micronutrient supplements, such as vitamin A and iron, provided where indicated?',
                'why' => 'Cheap supplements prevent expensive and disabling deficiencies.'],
            ['code' => 'NUT.006', 'module' => 'NUT', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are malnourished children followed up until they recover?',
                'why' => 'Nutrition treatment works over weeks; follow-up is what makes it work.'],
            ['code' => 'NUT.007', 'module' => 'NUT', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What most limits nutrition services here?',
                'why' => 'Names the local constraint.'],
        ];
    }

    /**
     * Mental health services.
     *
     * Several questions carry forward strong ideas from the PHSAI legacy mental health
     * questionnaire — the use of a standardised screening tool, tracking of clients lost
     * to follow-up, confidentiality of records and psychotropic availability — rewritten
     * into scored readiness.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function mentalHealth(): array
    {
        $respondent = 'Mental Health Focal Person · Nurse · Medical Officer';

        return [
            ['code' => 'MEN.001', 'module' => 'MNH', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are common mental health conditions screened for using a recognised tool?',
                'why' => 'Mental illness is under-detected everywhere; a structured tool is what surfaces it. Carried forward from legacy content.'],
            ['code' => 'MEN.002', 'module' => 'MNH', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Can the facility provide basic treatment for common mental health conditions, or refer reliably?',
                'why' => 'Screening without a route to treatment raises expectations it cannot meet.'],
            ['code' => 'MEN.003', 'module' => 'MNH', 'type' => 'SINGLE_SELECT', 'options' => self::AVAILABILITY, 'respondent' => $respondent,
                'text' => 'How consistently are essential psychotropic medicines available?',
                'why' => 'Psychotropic stockouts force treatment interruption, which risks relapse and crisis. Carried forward from legacy content.',
                'observe' => true],
            ['code' => 'MEN.004', 'module' => 'MNH', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are mental health clients who miss appointments identified and followed up?',
                'why' => 'Loss to follow-up is the central failure mode of mental health care. Carried forward from legacy content.'],
            ['code' => 'MEN.005', 'module' => 'MNH', 'type' => 'SINGLE_SELECT', 'options' => [
                ['label' => 'Separate, restricted-access record', 'score' => 100],
                ['label' => 'Coded or de-identified entries', 'score' => 75],
                ['label' => 'Same general register as other patients', 'score' => 25],
                ['label' => 'No specific confidentiality measure', 'score' => 0, 'critical' => true],
            ], 'respondent' => $respondent,
                'text' => 'How is the confidentiality of mental health records maintained?',
                'why' => 'Mental health carries heavy stigma; records without access control expose clients to harm. Improved from legacy content.'],
            ['code' => 'MEN.006', 'module' => 'MNH', 'type' => 'SINGLE_SELECT', 'options' => self::YES_PARTIAL_NO, 'respondent' => $respondent,
                'text' => 'Are clients treated with dignity and without discrimination for their condition?',
                'why' => 'Disrespect in mental health care is both harm and a reason people never return.',
                'observe' => true],
            ['code' => 'MEN.007', 'module' => 'MNH', 'type' => 'OPEN_ENDED', 'respondent' => $respondent,
                'text' => 'What most limits mental health services here?',
                'why' => 'Names the local constraint on a routinely under-resourced service.'],
        ];
    }
}
