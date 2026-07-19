<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\Domain;
use App\Models\FrameworkQuestionPlacement;
use App\Models\SubIndex;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Scores, which the schema calls sub-indices.
 *
 * A department needs at least one score before any of its questions can affect a result:
 * publication rejects a scored question that belongs to no score. Until now the only way to
 * create one was the inline form on a question's scoring screen, so a department without a
 * score was a dead end reached only at the moment someone tried to use it.
 */
class ScoreController extends Controller
{
    public function index(Request $request): View
    {
        $query = SubIndex::query()->with(['module', 'domain'])->orderBy('full_name');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->whereRaw('LOWER(full_name) LIKE ?', [$search]);
        }

        if ($request->filled('department')) {
            $query->where('module_id', $request->integer('department'));
        }

        $scores = $query->paginate(20)->withQueryString();

        // Usage tells an administrator whether a score is doing anything.
        $usage = FrameworkQuestionPlacement::query()
            ->selectRaw('sub_index_id, count(*) as total')
            ->whereNotNull('sub_index_id')
            ->groupBy('sub_index_id')
            ->pluck('total', 'sub_index_id');

        return view('admin.scores.index', [
            'scores' => $scores,
            'usage' => $usage,
            'departments' => AssessmentModule::where('is_active', true)->orderBy('module_name')->get(['module_id', 'module_name']),
            'departmentsWithoutScore' => AssessmentModule::where('is_active', true)
                ->whereNotIn('module_id', SubIndex::query()->select('module_id'))
                ->orderBy('module_name')
                ->get(['module_id', 'module_name']),
            'areas' => Domain::orderBy('display_order')->get(['domain_id', 'domain_name']),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'integer', Rule::exists('assessment_modules', 'module_id')->where('is_active', true)],
            'full_name' => ['required', 'string', 'max:120'],
            'domain_id' => ['required', 'integer', Rule::exists('domains', 'domain_id')],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], [
            'module_id' => 'department',
            'full_name' => 'score name',
            'domain_id' => 'measurement area',
        ]);

        $score = SubIndex::create([
            'module_id' => $validated['module_id'],
            'domain_id' => $validated['domain_id'],
            'acronym' => $this->uniqueAcronym($validated['full_name'], (int) $validated['module_id']),
            'full_name' => $validated['full_name'],
            'description' => $validated['description'] ?? null,
        ]);

        $audit->record('platform.score.created', null, newValues: [
            'sub_index_id' => $score->sub_index_id,
            'module_id' => $score->module_id,
            'full_name' => $score->full_name,
        ]);

        return back()->with('success', 'Score "'.$score->full_name.'" created.');
    }

    public function update(Request $request, SubIndex $score, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['full_name' => 'score name']);

        $old = $score->only(['full_name', 'description']);
        $score->update($validated);
        $audit->record('platform.score.updated', null, $old, $validated);

        return back()->with('success', 'Score updated.');
    }

    public function destroy(SubIndex $score, AuditService $audit): RedirectResponse
    {
        $inUse = FrameworkQuestionPlacement::where('sub_index_id', $score->sub_index_id)->count();

        if ($inUse > 0) {
            return back()->withErrors([
                'score' => 'This score is used by '.$inUse.' '.str('question')->plural($inUse).' and cannot be removed. Move those questions to another score first.',
            ]);
        }

        $name = $score->full_name;
        $audit->record('platform.score.removed', null, ['full_name' => $name]);
        $score->delete();

        return back()->with('success', 'Score "'.$name.'" removed.');
    }

    /**
     * Acronyms are stored on every score but are never shown to an administrator, so one
     * is derived rather than asked for.
     */
    private function uniqueAcronym(string $name, int $moduleId): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'SCR', 0, 3));
        $candidate = $base;
        $suffix = 2;

        while (SubIndex::where('module_id', $moduleId)->where('acronym', $candidate)->exists()) {
            $candidate = substr($base, 0, 2).$suffix++;
        }

        return $candidate;
    }
}
