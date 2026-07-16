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
    public function index(Request $request): View
    {
        $query = Project::with(['targets.targetType', 'targets.category'])->latest();

        if ($request->filled('search')) {
            $query->whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.$request->search.'%']);
        }

        $projects = $query->paginate(20)->withQueryString();

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

        $countries = $this->countries();

        return view('projects.create', compact('targetTypes', 'categoriesByType', 'countries'));
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
            'country' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'sub_region' => ['nullable', 'string', 'max:100'],
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
                'country' => $validated['country'],
                'region' => $validated['region'] ?? null,
                'sub_region' => $validated['sub_region'] ?? null,
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

    private function countries(): array
    {
        return [
            'Africa' => [
                'Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi',
                'Cabo Verde', 'Cameroon', 'Central African Republic', 'Chad', 'Comoros',
                'Congo', 'Democratic Republic of the Congo', "Côte d'Ivoire", 'Djibouti',
                'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini', 'Ethiopia',
                'Gabon', 'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau', 'Kenya',
                'Lesotho', 'Liberia', 'Libya', 'Madagascar', 'Malawi', 'Mali',
                'Mauritania', 'Mauritius', 'Morocco', 'Mozambique', 'Namibia', 'Niger',
                'Nigeria', 'Rwanda', 'São Tomé and Príncipe', 'Senegal', 'Seychelles',
                'Sierra Leone', 'Somalia', 'South Africa', 'South Sudan', 'Sudan',
                'Tanzania', 'Togo', 'Tunisia', 'Uganda', 'Zambia', 'Zimbabwe',
            ],
            'Rest of the World' => [
                'Afghanistan', 'Albania', 'Andorra', 'Antigua and Barbuda', 'Argentina',
                'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain',
                'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Bhutan',
                'Bolivia', 'Bosnia and Herzegovina', 'Brazil', 'Brunei', 'Bulgaria',
                'Cambodia', 'Canada', 'Chile', 'China', 'Colombia', 'Costa Rica',
                'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Dominica',
                'Dominican Republic', 'Ecuador', 'El Salvador', 'Estonia', 'Fiji',
                'Finland', 'France', 'Georgia', 'Germany', 'Greece', 'Grenada',
                'Guatemala', 'Guyana', 'Haiti', 'Honduras', 'Hungary', 'Iceland',
                'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy',
                'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kiribati', 'Kuwait',
                'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Liechtenstein', 'Lithuania',
                'Luxembourg', 'Malaysia', 'Maldives', 'Malta', 'Marshall Islands',
                'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro',
                'Myanmar', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua',
                'North Korea', 'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau',
                'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines',
                'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Saint Kitts and Nevis',
                'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino',
                'Saudi Arabia', 'Serbia', 'Singapore', 'Slovakia', 'Slovenia',
                'Solomon Islands', 'South Korea', 'Spain', 'Sri Lanka', 'Suriname',
                'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Thailand',
                'Timor-Leste', 'Tonga', 'Trinidad and Tobago', 'Turkey', 'Turkmenistan',
                'Tuvalu', 'Ukraine', 'United Arab Emirates', 'United Kingdom',
                'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City',
                'Venezuela', 'Vietnam', 'Yemen',
            ],
        ];
    }
}
