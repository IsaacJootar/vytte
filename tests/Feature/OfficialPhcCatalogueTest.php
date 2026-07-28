<?php

namespace Tests\Feature;

use App\Models\AssessmentCatalogueRelease;
use App\Models\FacilityProfile;
use App\Models\Project;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficialPhcCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_phc_successor_release_composes_content_ready_departments_without_duplicate_questions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $release = AssessmentCatalogueRelease::where('release_code', 'VYTTE_PHC_ASSESSMENT_V2')
            ->with(['facilityProfile.departments', 'departmentFrameworkVersions.module'])
            ->firstOrFail();

        $this->assertSame(AssessmentCatalogueRelease::STATUS_PUBLISHED, $release->status);
        $this->assertDatabaseHas('assessment_catalogue_releases', [
            'release_code' => 'VYTTE_PHC_ASSESSMENT_V1',
            'status' => AssessmentCatalogueRelease::STATUS_SUPERSEDED,
        ]);
        $this->assertCount(15, $release->departmentFrameworkVersions);
        $this->assertSame(6, $release->departmentFrameworkVersions->where('pivot.applicability', 'REQUIRED')->count());
        $this->assertSame(5, $release->departmentFrameworkVersions->where('pivot.applicability', 'DEFAULT')->count());
        $this->assertSame(4, $release->departmentFrameworkVersions->where('pivot.applicability', 'OPTIONAL')->count());

        $frameworkIds = $release->departmentFrameworkVersions->pluck('framework_version_id');
        $questionCounts = DB::table('framework_question_placements')
            ->whereIn('framework_version_id', $frameworkIds)
            ->selectRaw('COUNT(*) AS placements, COUNT(DISTINCT question_id) AS questions')
            ->first();
        $this->assertSame(220, (int) $questionCounts->placements);
        $this->assertSame(220, (int) $questionCounts->questions);

        $clinicRelease = AssessmentCatalogueRelease::where('release_code', 'VYTTE_CLINIC_ASSESSMENT_V1')->firstOrFail();
        $hospitalRelease = AssessmentCatalogueRelease::where('release_code', 'VYTTE_HOSPITAL_READINESS_V2')->firstOrFail();
        $pharmacyFrameworkIds = DB::table('assessment_catalogue_department_versions as composition')
            ->join('assessment_catalogue_releases as releases', 'releases.catalogue_release_id', '=', 'composition.catalogue_release_id')
            ->join('assessment_modules as departments', 'departments.module_id', '=', 'composition.module_id')
            ->whereIn('releases.catalogue_release_id', [
                $clinicRelease->catalogue_release_id,
                $release->catalogue_release_id,
                $hospitalRelease->catalogue_release_id,
            ])
            ->where('departments.module_code', 'PHM')
            ->pluck('composition.framework_version_id');
        $this->assertCount(3, $pharmacyFrameworkIds);
        $this->assertCount(1, $pharmacyFrameworkIds->unique());

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        $project = Project::create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $user->user_id,
            'name' => 'PHC comprehensive test',
        ]);
        $profile = FacilityProfile::where('profile_code', 'PRIMARY_HEALTH_CENTRE')->firstOrFail();
        $target = Target::create([
            'owner_workspace_id' => $workspace->workspace_id,
            'target_type_code' => 'HEALTH_FACILITY',
            'facility_profile_id' => $profile->facility_profile_id,
            'name' => 'Example PHC',
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $this->actingAs($user)
            ->get(route('assessments.create', $project))
            ->assertOk()
            ->assertSee('15 services')
            ->assertSee('Antenatal Care')
            ->assertSee('Child Health &amp; Immunization', false)
            ->assertSee('Family Planning')
            ->assertSee('coming soon');

        $selectedIds = $release->departmentFrameworkVersions
            ->whereIn('pivot.applicability', ['REQUIRED', 'DEFAULT'])
            ->pluck('module_id')
            ->all();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue(
            $project,
            $release,
            $selectedIds,
            creatorId: $user->user_id,
        );

        $this->assertCount(11, $assessment->snapshot->payload);
        $this->assertSame(176, collect($assessment->snapshot->payload)->sum(
            fn (array $department): int => count($department['questions'])
        ));
    }
}
