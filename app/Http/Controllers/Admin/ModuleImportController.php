<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\ModuleDomain;
use App\Models\Question;
use App\Models\QuestionOption;
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
        $required = ['module_code', 'module_name', 'target_type_code', 'domains'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages(['json_file' => "Missing required field: {$field}."]);
            }
        }

        if (! is_array($data['domains'])) {
            throw ValidationException::withMessages(['json_file' => 'domains must be an array.']);
        }

        if (AssessmentModule::where('module_code', $data['module_code'])
            ->where('target_type_code', $data['target_type_code'])
            ->exists()) {
            throw ValidationException::withMessages(['json_file' => "A module with code \"{$data['module_code']}\" already exists for this target type."]);
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

        $typeId = DB::table('question_types')->where('type_code', 'SINGLE_SELECT')->value('type_id');

        foreach ($data['domains'] as $domainData) {
            $domain = ModuleDomain::create([
                'module_id' => $module->module_id,
                'domain_number' => (int) $domainData['domain_number'],
                'domain_label' => trim($domainData['domain_label']),
            ]);

            foreach ($domainData['questions'] ?? [] as $qIdx => $qData) {
                $question = Question::create([
                    'module_id' => $module->module_id,
                    'module_domain_id' => $domain->module_domain_id,
                    'question_number' => $qIdx + 1,
                    'question_code' => strtoupper(trim($qData['question_code'])),
                    'question_text' => trim($qData['question_text']),
                    'type_id' => $typeId,
                    'display_order' => $qIdx + 1,
                    'is_active' => true,
                    'is_scored' => $qData['is_scored'] ?? true,
                    'source' => 'IMPORT',
                    'question_status' => 'APPROVED',
                ]);

                foreach ($qData['options'] ?? [] as $oIdx => $oData) {
                    QuestionOption::create([
                        'question_id' => $question->question_id,
                        'option_label' => trim($oData['option_label']),
                        'option_order' => $oIdx + 1,
                        'score_weight' => isset($oData['score_weight']) ? (float) $oData['score_weight'] : null,
                    ]);
                }
            }
        }

        return $module;
    }
}
