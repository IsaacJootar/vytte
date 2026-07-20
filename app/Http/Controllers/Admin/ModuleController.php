<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Request $request): View
    {
        $query = AssessmentModule::withCount('questions')
            ->with('targetType')
            ->orderBy('target_type_code')
            ->orderBy('module_name');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->where(function ($inner) use ($search): void {
                $inner->whereRaw('LOWER(module_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(module_code) LIKE ?', [$search]);
            });
        }

        if ($request->filled('target_type_code')) {
            $query->where('target_type_code', $request->string('target_type_code'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->value() === 'active');
        }

        return view('admin.modules.index', [
            'modules' => $query->paginate(20)->withQueryString(),
            'targetTypes' => AssessmentModule::query()
                ->select('target_type_code')
                ->distinct()
                ->orderBy('target_type_code')
                ->pluck('target_type_code'),
        ]);
    }

    public function show(AssessmentModule $module): View
    {
        $module->load([
            'questionGroups.questions.options',
            'targetType',
        ]);

        return view('admin.modules.show', compact('module'));
    }

    public function edit(AssessmentModule $module): View
    {
        return view('admin.modules.edit', compact('module'));
    }

    public function update(Request $request, AssessmentModule $module): RedirectResponse
    {
        $validated = $request->validate([
            'module_name' => ['required', 'string', 'max:150'],
            'primary_respondent' => ['nullable', 'string', 'max:255'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'data_collection_methods' => ['nullable', 'string', 'max:255'],
        ]);

        $module->update($validated);

        return redirect()
            ->route('admin.modules.show', $module)
            ->with('success', 'Module updated.');
    }

    public function toggleActive(AssessmentModule $module): RedirectResponse
    {
        $module->update(['is_active' => ! $module->is_active]);

        $status = $module->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Module {$status}.");
    }
}
