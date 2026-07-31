<?php

namespace Database\Seeders;

use App\Models\AssessmentModule;
use App\Models\Question;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Services\QuestionVersionPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the human-approved V1 department question package.
 *
 * The wording is Vytte-authored and source-informed. Published identities and versions
 * are immutable, so rerunning this seeder only fills content that is not yet published.
 */
class OfficialDepartmentQuestionLibrarySeeder extends Seeder
{
    private const OPTIONS = [
        ['option_id' => 1, 'option_label' => 'Yes', 'option_order' => 1, 'score_weight' => 100, 'critical_failure' => false],
        ['option_id' => 2, 'option_label' => 'Partially', 'option_order' => 2, 'score_weight' => 50, 'critical_failure' => false],
        ['option_id' => 3, 'option_label' => 'No', 'option_order' => 3, 'score_weight' => 0, 'critical_failure' => false],
    ];

    public function run(): void
    {
        $package = require database_path('content/official_department_questions_v1.php');
        $this->seedStandards($package['sources']);

        $typeId = QuestionType::where('type_code', 'SINGLE_SELECT')->value('type_id');
        $publishing = app(QuestionVersionPublishingService::class);
        $published = 0;
        $skipped = 0;

        foreach ($package['departments'] as $moduleCode => $department) {
            $module = AssessmentModule::where('target_type_code', 'HEALTH_FACILITY')
                ->where('module_code', $moduleCode)
                ->first();

            if (! $module || ! $typeId) {
                $this->command?->warn("Department {$moduleCode} or SINGLE_SELECT question type is missing.");
                $skipped += count($department['questions']);

                continue;
            }

            $source = $package['sources'][$department['source']];
            $standardId = DB::table('standards_registry')
                ->where('standard_code', $department['source'])
                ->value('standard_id');

            foreach ($department['questions'] as $index => $text) {
                $code = sprintf('%s.%03d', $moduleCode, $index + 1);
                $created = DB::transaction(fn (): bool => $this->publishQuestion(
                    $code,
                    $text,
                    $module,
                    (int) $typeId,
                    $department['source'],
                    (int) $standardId,
                    $source,
                    $publishing,
                ));

                $created ? $published++ : $skipped++;
            }
        }

        $this->command?->info("Approved department question library: {$published} published, {$skipped} skipped.");
    }

    /** @param array<string, array{0: string, 1: string, 2: string, 3: string}> $sources */
    private function seedStandards(array $sources): void
    {
        foreach ($sources as $code => [$name, $body, $description, $url]) {
            DB::table('standards_registry')->updateOrInsert(
                ['standard_code' => $code],
                [
                    'standard_name' => $name,
                    'issuing_body' => $body,
                    'description' => $description,
                    'reference_url' => $url,
                ],
            );
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string}  $source
     */
    private function publishQuestion(
        string $code,
        string $text,
        AssessmentModule $module,
        int $typeId,
        string $sourceCode,
        int $standardId,
        array $source,
        QuestionVersionPublishingService $publishing,
    ): bool {
        $question = Question::where('question_code', $code)->first();

        if ($question?->versions()->where('status', QuestionVersion::STATUS_PUBLISHED)->exists()) {
            return false;
        }

        if (! $question) {
            $question = Question::create([
                'module_id' => $module->module_id,
                'question_number' => ((int) Question::where('module_id', $module->module_id)->max('question_number')) + 1,
                'question_code' => $code,
                'question_text' => $text,
                'type_id' => $typeId,
                'respondent_role_hint' => $module->primary_respondent,
                'display_order' => ((int) Question::where('module_id', $module->module_id)->max('display_order')) + 1,
                'is_active' => true,
                'is_scored' => true,
                'source' => $sourceCode,
                'question_status' => 'DRAFT',
                'standard_reference_id' => $standardId,
                'standard_alignment_status' => 'SOURCE_INFORMED',
            ]);
        }

        $version = QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => ((int) $question->versions()->max('version_number')) + 1,
            'status' => QuestionVersion::STATUS_APPROVED,
            'question_text' => $text,
            'type_id' => $typeId,
            'options' => self::OPTIONS,
            'requires_observation' => false,
            'respondent_role_hint' => $module->primary_respondent,
            'methodology_notes' => 'Assesses whether the department has a practical readiness or quality safeguard in place and functioning.',
            'source_summary' => "Vytte-authored wording informed by {$source[0]}. Source: {$source[3]}. No source instrument reproduced.",
        ]);

        $publishing->publish($version);

        return true;
    }
}
