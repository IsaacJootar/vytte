<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Services\AssessmentLogicService;
use App\Services\AuditService;
use App\Support\ResponseInputContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssessmentLogicController extends Controller
{
    public function index(DepartmentFrameworkVersion $assessment): View
    {
        $assessment->load([
            'questionPlacements.section',
            'questionPlacements.questionVersion.questionType',
        ]);

        return view('admin.assessment-builder.logic', [
            'assessment' => $assessment,
            'placements' => $assessment->questionPlacements->sortBy('display_order')->values(),
            'steps' => AssessmentBuilderController::STEPS,
            'currentStep' => 'logic',
            'isEditable' => $assessment->status === DepartmentFrameworkVersion::STATUS_DRAFT,
            'comparisons' => [
                'IS_ANSWERED' => 'has a direct answer',
                'IS_NOT_ANSWERED' => 'does not have a direct answer',
                'OPTION_SELECTED' => 'includes this answer',
                'OPTION_NOT_SELECTED' => 'does not include this answer',
                'STATE_IS' => 'has this response state',
                'NUMBER_EQUALS' => 'number equals',
                'NUMBER_GREATER_THAN' => 'number is greater than',
                'NUMBER_AT_LEAST' => 'number is at least',
                'NUMBER_LESS_THAN' => 'number is less than',
                'NUMBER_AT_MOST' => 'number is at most',
                'TEXT_PRESENT' => 'contains written text',
            ],
            'states' => collect(ResponseInputContract::RESPONSE_STATES)
                ->mapWithKeys(fn ($state) => [$state => str($state)->replace('_', ' ')->lower()->ucfirst()->value()]),
        ]);
    }

    public function update(
        Request $request,
        DepartmentFrameworkVersion $assessment,
        FrameworkQuestionPlacement $placement,
        AssessmentLogicService $logic,
        AuditService $audit,
    ): RedirectResponse {
        $this->assertEditablePlacement($assessment, $placement);
        $validated = $request->validate([
            'operator' => ['required', 'in:ALL,ANY'],
            'conditions' => ['required', 'array', 'max:10'],
            'conditions.*.source_question_id' => ['nullable', 'uuid'],
            'conditions.*.comparison' => ['nullable', 'string'],
            'conditions.*.option_value' => ['nullable'],
            'conditions.*.number_value' => ['nullable'],
            'conditions.*.state_value' => ['nullable', 'string'],
        ]);

        $conditions = collect($validated['conditions'])
            ->filter(fn ($condition) => filled($condition['source_question_id'] ?? null) && filled($condition['comparison'] ?? null))
            ->map(function ($condition): array {
                $comparison = $condition['comparison'];
                $value = match (true) {
                    in_array($comparison, ['OPTION_SELECTED', 'OPTION_NOT_SELECTED'], true) => $condition['option_value'] ?? null,
                    str_starts_with($comparison, 'NUMBER_') => $condition['number_value'] ?? null,
                    $comparison === 'STATE_IS' => $condition['state_value'] ?? null,
                    default => null,
                };

                return [
                    'source_question_id' => $condition['source_question_id'],
                    'comparison' => $comparison,
                    'value' => $value,
                ];
            })->values()->all();

        try {
            $rule = $logic->saveRule($assessment, $placement, [
                'operator' => $validated['operator'],
                'conditions' => $conditions,
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $old = $placement->applicability;
        $placement->update(['applicability' => $rule]);
        $audit->record('assessment.logic.updated', $assessment, ['applicability' => $old], [
            'framework_question_placement_id' => $placement->framework_question_placement_id,
            'applicability' => $rule,
        ]);

        return back()->with('success', 'Question logic saved. Test it in respondent preview before publishing.');
    }

    public function destroy(
        DepartmentFrameworkVersion $assessment,
        FrameworkQuestionPlacement $placement,
        AuditService $audit,
    ): RedirectResponse {
        $this->assertEditablePlacement($assessment, $placement);
        $old = $placement->applicability;
        $placement->update(['applicability' => null]);
        $audit->record('assessment.logic.cleared', $assessment, ['applicability' => $old], [
            'framework_question_placement_id' => $placement->framework_question_placement_id,
            'applicability' => null,
        ]);

        return back()->with('success', 'Question is now always shown.');
    }

    private function assertEditablePlacement(DepartmentFrameworkVersion $assessment, FrameworkQuestionPlacement $placement): void
    {
        abort_unless($placement->framework_version_id === $assessment->framework_version_id, 404);
        if ($assessment->status !== DepartmentFrameworkVersion::STATUS_DRAFT) {
            abort(422, 'Published assessment logic is frozen. Create a new version to change it.');
        }
    }
}
