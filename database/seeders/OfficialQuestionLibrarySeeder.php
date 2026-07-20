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
}
