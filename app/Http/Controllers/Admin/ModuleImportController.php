<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\QuestionGroup;
use App\Models\Question;
use App\Models\QuestionNumericBand;
use App\Models\QuestionOption;
use App\Support\ResponseInputContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModuleImportController extends Controller
{
    public function create(): View
    {
        return view('admin.modules.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'json_file' => ['required', 'file', 'mimes:json,txt', 'max:512'],
        ]);

        $content = file_get_contents($request->file('json_file')->getRealPath());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages(['json_file' => 'Invalid JSON file.']);
        }

        $this->validateStructure($data);

        $module = DB::transaction(function () use ($data) {
            return $this->importModule($data);
        });

        return redirect()
            ->route('admin.modules.show', $module)
            ->with('success', "Module \"{$module->module_name}\" imported successfully.");
    }

    private function validateStructure(array $data): void
    {
        $required = ['module_code', 'module_name', 'target_type_code', 'question_groups'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages(['json_file' => "Missing required field: {$field}."]);
            }
        }

        if (! is_array($data['question_groups'])) {
            throw ValidationException::withMessages(['json_file' => 'question_groups must be an array.']);
        }

        if (AssessmentModule::where('module_code', $data['module_code'])
            ->where('target_type_code', $data['target_type_code'])
            ->exists()) {
            throw ValidationException::withMessages(['json_file' => "A module with code \"{$data['module_code']}\" already exists for this target type."]);
        }

        foreach ($data['question_groups'] as $groupIndex => $group) {
            foreach ($group['questions'] ?? [] as $questionIndex => $question) {
                $path = 'question_groups.'.($groupIndex + 1).'.questions.'.($questionIndex + 1);
                $type = strtoupper($question['response_type'] ?? 'SINGLE_SELECT');
                $isScored = (bool) ($question['is_scored'] ?? true);
                if (! ResponseInputContract::supports($type)) {
                    throw ValidationException::withMessages(['json_file' => "{$path} uses unsupported response type {$type}."]);
                }
                if (in_array($type, ResponseInputContract::OPTION_TYPES, true) && empty($question['options'])) {
                    throw ValidationException::withMessages(['json_file' => "{$path} requires at least one answer option."]);
                }
                if ($type === 'OPEN_ENDED' && $isScored) {
                    throw ValidationException::withMessages(['json_file' => "{$path} must be unscored because open text has no scoring contract."]);
                }
                if ($type === 'NUMERIC' && $isScored && empty($question['numeric_bands'])) {
                    throw ValidationException::withMessages(['json_file' => "{$path} requires numeric scoring bands."]);
                }
                if ($type === 'NUMERIC'
                    && isset($question['numeric_min'], $question['numeric_max'])
                    && (float) $question['numeric_min'] > (float) $question['numeric_max']) {
                    throw ValidationException::withMessages(['json_file' => "{$path} has a minimum greater than its maximum."]);
                }
            }
        }
    }

    private function importModule(array $data): AssessmentModule
    {
        $module = AssessmentModule::create([
            'module_code' => strtoupper(trim($data['module_code'])),
            'module_name' => trim($data['module_name']),
            'target_type_code' => $data['target_type_code'],
            'primary_respondent' => $data['primary_respondent'] ?? null,
            'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null,
            'data_collection_methods' => $data['data_collection_methods'] ?? null,
            'is_active' => true,
        ]);

        foreach ($data['question_groups'] as $groupData) {
            $group = QuestionGroup::create([
                'module_id' => $module->module_id,
                'group_number' => (int) $groupData['group_number'],
                'group_label' => trim($groupData['group_label']),
            ]);

            foreach ($groupData['questions'] ?? [] as $qIdx => $qData) {
                $responseType = strtoupper($qData['response_type'] ?? 'SINGLE_SELECT');
                $typeId = DB::table('question_types')->where('type_code', $responseType)->value('type_id');
                $question = Question::create([
                    'module_id' => $module->module_id,
                    'question_group_id' => $group->question_group_id,
                    'question_number' => $qIdx + 1,
                    'question_code' => strtoupper(trim($qData['question_code'])),
                    'question_text' => trim($qData['question_text']),
                    'type_id' => $typeId,
                    'display_order' => $qIdx + 1,
                    'is_active' => true,
                    'is_scored' => $qData['is_scored'] ?? true,
                    'source' => 'IMPORT',
                    'question_status' => 'APPROVED',
                    'numeric_unit' => $qData['numeric_unit'] ?? null,
                    'numeric_min' => $qData['numeric_min'] ?? null,
                    'numeric_max' => $qData['numeric_max'] ?? null,
                    'numeric_step' => $qData['numeric_step'] ?? null,
                ]);

                foreach ($qData['options'] ?? [] as $oIdx => $oData) {
                    QuestionOption::create([
                        'question_id' => $question->question_id,
                        'option_label' => trim($oData['option_label']),
                        'option_order' => $oIdx + 1,
                        'score_weight' => isset($oData['score_weight']) ? (float) $oData['score_weight'] : null,
                    ]);
                }

                foreach ($qData['numeric_bands'] ?? [] as $bandIndex => $bandData) {
                    QuestionNumericBand::create([
                        'question_id' => $question->question_id,
                        'min_value' => $bandData['min_value'] ?? null,
                        'max_value' => $bandData['max_value'] ?? null,
                        'score_weight' => (float) $bandData['score_weight'],
                        'band_order' => $bandIndex + 1,
                    ]);
                }
            }
        }

        return $module;
    }
}
