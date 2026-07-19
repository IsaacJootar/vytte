<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkSection;
use App\Services\AssessmentBuilderService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Section management inside the assessment builder. All governed work is delegated to
 * AssessmentBuilderService; this controller validates author input and translates
 * outcomes into plain language.
 */
class AssessmentSectionController extends Controller
{
    public function __construct(private readonly AssessmentBuilderService $builder) {}

    public function store(Request $request, DepartmentFrameworkVersion $assessment, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'section_name' => ['required', 'string', 'max:180'],
            'purpose' => ['nullable', 'string', 'max:1000'],
        ], [], ['section_name' => 'section name']);

        try {
            $section = $this->builder->addSection($assessment, $validated['section_name'], $validated['purpose'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $audit->record('assessment.section.added', $assessment, newValues: [
            'framework_section_id' => $section->framework_section_id,
            'section_name' => $section->section_name,
        ]);

        return back()->with('success', 'Section added.');
    }

    public function update(Request $request, DepartmentFrameworkVersion $assessment, FrameworkSection $section, AuditService $audit): RedirectResponse
    {
        $this->assertBelongs($assessment, $section);

        $validated = $request->validate([
            'section_name' => ['required', 'string', 'max:180'],
            'purpose' => ['nullable', 'string', 'max:1000'],
        ], [], ['section_name' => 'section name']);

        $old = $section->only(['section_name', 'purpose']);

        try {
            $this->builder->renameSection($section, $validated['section_name'], $validated['purpose'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $audit->record('assessment.section.updated', $assessment, $old, $validated);

        return back()->with('success', 'Section updated.');
    }

    public function move(Request $request, DepartmentFrameworkVersion $assessment, FrameworkSection $section): RedirectResponse
    {
        $this->assertBelongs($assessment, $section);

        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);

        try {
            $this->builder->moveSection($section, $validated['direction']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Section order updated.');
    }

    public function destroy(DepartmentFrameworkVersion $assessment, FrameworkSection $section, AuditService $audit): RedirectResponse
    {
        $this->assertBelongs($assessment, $section);
        $name = $section->section_name;

        try {
            $this->builder->deleteSection($section);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $audit->record('assessment.section.removed', $assessment, ['section_name' => $name]);

        return back()->with('success', 'Section removed.');
    }

    private function assertBelongs(DepartmentFrameworkVersion $assessment, FrameworkSection $section): void
    {
        abort_unless($section->framework_version_id === $assessment->framework_version_id, 404);
    }
}
