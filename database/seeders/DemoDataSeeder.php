<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\FacilityProfile;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AssessmentCreationService;
use App\Services\ReportSnapshotService;
use App\Services\ScoringService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    private AssessmentCatalogueRelease $clinicRelease;

    private FacilityProfile $clinicProfile;

    public function run(): void
    {
        $this->clinicRelease = AssessmentCatalogueRelease::where('release_code', 'DEMO_CLINIC_COMPREHENSIVE_V1')->firstOrFail();
        $this->clinicProfile = FacilityProfile::where('profile_code', 'CLINIC')->firstOrFail();

        $this->seedProfessionalWorkspace();
        $this->seedStarterWorkspace();
        $this->seedOrganizationWorkspace();
    }

    /**
     * Demo assessment data depends on the demo accounts created by DemoAccountSeeder.
     * A missing account is skipped rather than fatal, but it must never be silent:
     * a rename on either side previously disabled the whole demo dataset unnoticed.
     */
    private function demoAccountIsAvailable(?Workspace $workspace, ?User $user, string $workspaceName): bool
    {
        if ($workspace && $user) {
            return true;
        }

        $this->command?->warn(
            "DemoDataSeeder skipped \"{$workspaceName}\": run DemoAccountSeeder first, or the demo accounts have been renamed."
        );

        return false;
    }

    private function seedProfessionalWorkspace(): void
    {
        $workspace = Workspace::where('name', 'Professional Demo Workspace')->first();
        $user = User::where('email', 'professional@vytte.test')->first();
        if (! $this->demoAccountIsAvailable($workspace, $user, 'Professional Demo Workspace')) {
            return;
        }

        $target1 = $this->makeTarget($workspace, 'Surulere PHC', 'Nigeria', 'Lagos');
        $project1 = $this->makeProject($workspace, $user, 'Lagos PHC Assessment — 2026');
        $this->attachTarget($project1, $target1);

        $this->makeGovernedAssessment($workspace, $project1, $user, 'COMPLETE', now()->subMonths(3), 'low');
        $this->makeGovernedAssessment($workspace, $project1, $user, 'COMPLETE', now()->subMonth(), 'middle');

        $target2 = $this->makeTarget($workspace, 'Abuja General Hospital', 'Nigeria', 'FCT');
        $project2 = $this->makeProject($workspace, $user, 'Abuja General Hospital — Q1 2026');
        $this->attachTarget($project2, $target2);

        $this->makeGovernedAssessment($workspace, $project2, $user, 'COMPLETE', now()->subWeeks(6), 'high');

        $target3 = $this->makeTarget($workspace, 'Ibadan PHC Pilot', 'Nigeria', 'Oyo');
        $project3 = $this->makeProject($workspace, $user, 'Ibadan PHC Pilot — Q2 2026');
        $this->attachTarget($project3, $target3);

        $this->makeGovernedAssessment($workspace, $project3, $user, 'IN_PROGRESS', null, 'middle');
    }

    private function seedStarterWorkspace(): void
    {
        $workspace = Workspace::where('name', 'Starter Demo Workspace')->first();
        $user = User::where('email', 'starter@vytte.test')->first();
        if (! $this->demoAccountIsAvailable($workspace, $user, 'Starter Demo Workspace')) {
            return;
        }

        $target = $this->makeTarget($workspace, 'Accra Community Health Post', 'Ghana', 'Greater Accra');
        $project = $this->makeProject($workspace, $user, 'Accra Baseline Assessment');
        $this->attachTarget($project, $target);

        $this->makeGovernedAssessment($workspace, $project, $user, 'COMPLETE', now()->subWeeks(2), 'low');
    }

    private function seedOrganizationWorkspace(): void
    {
        $workspace = Workspace::where('name', 'Organization Demo Workspace')->first();
        $user = User::where('email', 'organization@vytte.test')->first();
        if (! $this->demoAccountIsAvailable($workspace, $user, 'Organization Demo Workspace')) {
            return;
        }

        $target1 = $this->makeTarget($workspace, 'Cross River State General Hospital', 'Nigeria', 'Cross River');
        $project1 = $this->makeProject($workspace, $user, 'Cross River State Facility Survey');
        $this->attachTarget($project1, $target1);
        $this->makeGovernedAssessment($workspace, $project1, $user, 'COMPLETE', now()->subMonths(2), 'middle');

        $target2 = $this->makeTarget($workspace, 'Warri PHC Cluster', 'Nigeria', 'Delta');
        $project2 = $this->makeProject($workspace, $user, 'Delta State Health Centre Network');
        $this->attachTarget($project2, $target2);
        $this->makeGovernedAssessment($workspace, $project2, $user, 'COMPLETE', now()->subMonths(4), 'low');
        $this->makeGovernedAssessment($workspace, $project2, $user, 'COMPLETE', now()->subWeeks(3), 'middle');

        $target3 = $this->makeTarget($workspace, 'Nairobi District Referral Hospital', 'Kenya', 'Nairobi');
        $project3 = $this->makeProject($workspace, $user, 'Nairobi Referral Hospital Baseline');
        $this->attachTarget($project3, $target3);

        $target4 = $this->makeTarget($workspace, 'Kampala Health Centre IV', 'Uganda', 'Kampala');
        $project4 = $this->makeProject($workspace, $user, 'Kampala Health Centre Assessment');
        $this->attachTarget($project4, $target4);
        $this->makeGovernedAssessment($workspace, $project4, $user, 'IN_PROGRESS', null, 'middle');
    }

    private function makeTarget(Workspace $workspace, string $name, string $country, string $region): Target
    {
        app()->instance('current.workspace', $workspace);

        return Target::create([
            'owner_workspace_id' => $workspace->workspace_id,
            'target_type_code' => 'HEALTH_FACILITY',
            'facility_profile_id' => $this->clinicProfile->facility_profile_id,
            'name' => $name,
            'country' => $country,
            'region' => $region,
            'uses_departments' => true,
        ]);
    }

    private function makeProject(Workspace $workspace, User $user, string $name): Project
    {
        app()->instance('current.workspace', $workspace);

        return Project::create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $user->user_id,
            'name' => $name,
            'status' => 'ACTIVE',
        ]);
    }

    private function attachTarget(Project $project, Target $target): void
    {
        DB::table('project_targets')->insertOrIgnore([
            'project_id' => $project->project_id,
            'target_id' => $target->target_id,
            'added_at' => now(),
        ]);
    }

    private function makeGovernedAssessment(
        Workspace $workspace,
        Project $project,
        User $user,
        string $status,
        ?Carbon $completedAt,
        string $answerLevel,
    ): Assessment {
        app()->instance('current.workspace', $workspace);
        $user->forceFill(['active_workspace_id' => $workspace->workspace_id])->save();

        $assessment = app(AssessmentCreationService::class)->createFromCatalogue(
            $project,
            $this->clinicRelease,
            creatorId: $user->user_id,
        );
        $assessment->update([
            'assessor_name' => $user->name,
            'started_at' => $completedAt ? $completedAt->copy()->subHours(2) : now()->subHour(),
        ]);

        if ($status !== Assessment::STATUS_COMPLETE) {
            return $assessment;
        }

        $this->answerOfficialScoredQuestions($assessment, $answerLevel);

        $assessment->update([
            'status' => Assessment::STATUS_COMPLETE,
            'publish_status' => Assessment::PUBLISH_PUBLISHED,
            'completed_at' => $completedAt,
        ]);
        $assessment->moduleScope()->where('in_scope', true)->update([
            'status' => 'COMPLETED',
            'completed_at' => $completedAt,
        ]);

        app(ScoringService::class)->calculate($assessment->fresh(['snapshot']));
        app(ReportSnapshotService::class)->createFor($assessment->fresh(['snapshot', 'score']));

        return $assessment;
    }

    private function answerOfficialScoredQuestions(Assessment $assessment, string $answerLevel): void
    {
        $selector = match ($answerLevel) {
            'high' => fn ($options) => $options->sortByDesc('score_weight')->first(),
            'low' => fn ($options) => $options->sortBy('score_weight')->first(),
            default => fn ($options) => $options->sortBy('score_weight')->values()->get(1) ?? $options->sortByDesc('score_weight')->first(),
        };

        collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true)
            ->each(function (array $question) use ($assessment, $selector): void {
                $option = $selector(collect($question['options']));
                Response::create([
                    'assessment_id' => $assessment->assessment_id,
                    'question_id' => $question['question_id'],
                    'value_option_id' => $option['option_id'],
                    'answered_at' => now(),
                ]);
            });
    }
}
