<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Target;
use App\Models\TargetType;
use App\Services\PlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::with(['targets.targetType', 'targets.category'])
            ->latest()
            ->paginate(20);

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $targetTypes = TargetType::with('categories')->get();

        $categoriesByType = $targetTypes->mapWithKeys(fn ($type) => [
            $type->target_type_code => $type->categories->map(fn ($cat) => [
                'category_id' => $cat->category_id,
                'category_name' => $cat->category_name,
            ])->values(),
        ]);

        return view('projects.create', compact('targetTypes', 'categoriesByType'));
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = app('current.workspace');

        if (PlanService::hasReachedProjectLimit($workspace)) {
            return redirect()->route('billing.index')
                ->with('limit_error', 'You have reached the project limit on your current plan. Upgrade to create more projects.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_name' => ['required', 'string', 'max:255'],
            'target_type_code' => ['required', 'string', 'exists:target_types,target_type_code'],
            'category_id' => ['required', 'integer', 'exists:target_categories,category_id'],
            'state' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
        ]);

        $project = DB::transaction(function () use ($validated) {
            $project = Project::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'owner_user_id' => auth()->user()->user_id,
            ]);

            $target = Target::create([
                'target_type_code' => $validated['target_type_code'],
                'name' => $validated['target_name'],
                'category_id' => $validated['category_id'],
                'state' => $validated['state'] ?? null,
                'lga' => $validated['lga'] ?? null,
            ]);

            $project->targets()->attach($target->target_id, [
                'added_at' => now(),
            ]);

            return $project;
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created.');
    }

    public function show(Project $project): View
    {
        $project->load([
            'targets.targetType',
            'targets.category',
            'owner',
            'assessments.moduleScope.module',
            'assessments.score',
        ]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $project->update($validated);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project updated.');
    }

    public function archive(Project $project): RedirectResponse
    {
        $project->update([
            'status' => $project->isArchived() ? 'ACTIVE' : 'ARCHIVED',
        ]);

        $action = $project->isArchived() ? 'archived' : 'reactivated';

        return back()->with('success', "Project {$action}.");
    }
}
