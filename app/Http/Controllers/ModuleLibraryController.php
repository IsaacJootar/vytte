<?php

namespace App\Http\Controllers;

use App\Models\AssessmentModule;
use App\Models\TargetType;
use Illuminate\Contracts\View\View;

class ModuleLibraryController extends Controller
{
    public function index(): View
    {
        $targetTypes = TargetType::with([
            'modules' => fn ($q) => $q->withCount(['questions', 'questionGroups', 'subIndices']),
        ])->get();

        return view('modules.index', compact('targetTypes'));
    }

    public function show(AssessmentModule $module): View
    {
        $module->load([
            'targetType',
            'questionGroups.questions.options',
            'subIndices.domain',
        ]);

        return view('modules.show', compact('module'));
    }
}
