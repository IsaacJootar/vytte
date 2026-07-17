<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentScore;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();
        if (! $tier) {
            return;
        }

        // OPD module (module_id = 1)
        $opdModuleId = DB::table('assessment_modules')->where('module_code', 'OPD')->value('module_id');
        $labModuleId = DB::table('assessment_modules')->where('module_code', 'LAB')->value('module_id');
        $ipdModuleId = DB::table('assessment_modules')->where('module_code', 'IPD')->value('module_id');

        // Maturity level IDs (1–5, seeded by ReferenceDataSeeder)
        $maturityLevels = DB::table('maturity_levels')
            ->orderBy('level_id')
            ->pluck('level_id', 'level_number');

        $this->seedProWorkspace($tier, $opdModuleId, $labModuleId, $maturityLevels);
        $this->seedFreeWorkspace($tier, $opdModuleId, $maturityLevels);
        $this->seedAgencyWorkspace($tier, $opdModuleId, $ipdModuleId, $maturityLevels);
    }

    private function seedProWorkspace($tier, $opdModuleId, $labModuleId, $maturityLevels): void
    {
        $workspace = Workspace::where('name', 'Pro Demo Workspace')->first();
        $user = User::where('email', 'pro@vytte.test')->first();
        if (! $workspace || ! $user) {
            return;
        }

        // Project 1 — PHC, Lagos, two completed assessments
        $target1 = $this->makeTarget($workspace, 'HEALTH_FACILITY', 'Surulere PHC', 'Nigeria', 'Lagos');
        $project1 = $this->makeProject($workspace, $user, 'Lagos PHC Assessment — 2026');
        $this->attachTarget($project1, $target1);

        $a1 = $this->makeAssessment($target1, $project1, $tier, $user, 'COMPLETE', now()->subMonths(3));
        $this->makeScope($a1, $opdModuleId, 'COMPLETED', now()->subMonths(3));
        $this->makeScore($a1, 38.4, $maturityLevels[2]);

        $a2 = $this->makeAssessment($target1, $project1, $tier, $user, 'COMPLETE', now()->subMonth());
        $this->makeScope($a2, $opdModuleId, 'COMPLETED', now()->subMonth());
        $this->makeScore($a2, 52.7, $maturityLevels[3]);

        // Project 2 — General Hospital, Abuja, one completed assessment
        $target2 = $this->makeTarget($workspace, 'HEALTH_FACILITY', 'Abuja General Hospital', 'Nigeria', 'FCT');
        $project2 = $this->makeProject($workspace, $user, 'Abuja General Hospital — Q1 2026');
        $this->attachTarget($project2, $target2);

        $a3 = $this->makeAssessment($target2, $project2, $tier, $user, 'COMPLETE', now()->subWeeks(6));
        $this->makeScope($a3, $labModuleId, 'COMPLETED', now()->subWeeks(6));
        $this->makeScore($a3, 71.2, $maturityLevels[4]);

        // Project 3 — PHC, Ibadan, in progress
        $target3 = $this->makeTarget($workspace, 'HEALTH_FACILITY', 'Ibadan PHC Pilot', 'Nigeria', 'Oyo');
        $project3 = $this->makeProject($workspace, $user, 'Ibadan PHC Pilot — Q2 2026');
        $this->attachTarget($project3, $target3);

        $a4 = $this->makeAssessment($target3, $project3, $tier, $user, 'IN_PROGRESS', null);
        $this->makeScope($a4, $opdModuleId, 'PENDING', null);
    }

    private function seedFreeWorkspace($tier, $opdModuleId, $maturityLevels): void
    {
        $workspace = Workspace::where('name', 'Free Demo Workspace')->first();
        $user = User::where('email', 'free@vytte.test')->first();
        if (! $workspace || ! $user) {
            return;
        }

        // One project, one completed assessment (weak score — shows free tier reality)
        $target = $this->makeTarget($workspace, 'HEALTH_FACILITY', 'Accra Community Health Post', 'Ghana', 'Greater Accra');
        $project = $this->makeProject($workspace, $user, 'Accra Baseline Assessment');
        $this->attachTarget($project, $target);

        $a = $this->makeAssessment($target, $project, $tier, $user, 'COMPLETE', now()->subWeeks(2));
        $this->makeScope($a, $opdModuleId, 'COMPLETED', now()->subWeeks(2));
        $this->makeScore($a, 34.6, $maturityLevels[2]);
    }

    private function seedAgencyWorkspace($tier, $opdModuleId, $ipdModuleId, $maturityLevels): void
    {
        $workspace = Workspace::where('name', 'Agency Demo Workspace')->first();
        $user = User::where('email', 'agency@vytte.test')->first();
        if (! $workspace || ! $user) {
            return;
        }

        // Project 1 — Cross River General Hospital
        $target1 = $this->makeTarget($workspace, 'HEALTH_FACILITY', 'Cross River State General Hospital', 'Nigeria', 'Cross River');
        $project1 = $this->makeProject($workspace, $user, 'Cross River State Facility Survey');
        $this->attachTarget($project1, $target1);

        $a1 = $this->makeAssessment($target1, $project1, $tier, $user, 'COMPLETE', now()->subMonths(2));
        $this->makeScope($a1, $opdModuleId, 'COMPLETED', now()->subMonths(2));
        $this->makeScore($a1, 63.1, $maturityLevels[4]);

        // Project 2 — Delta PHC, two assessments showing improvement
        $target2 = $this->makeTarget($workspace, 'HEALTH_FACILITY', 'Warri PHC Cluster', 'Nigeria', 'Delta');
        $project2 = $this->makeProject($workspace, $user, 'Delta State Health Centre Network');
        $this->attachTarget($project2, $target2);

        $a2 = $this->makeAssessment($target2, $project2, $tier, $user, 'COMPLETE', now()->subMonths(4));
        $this->makeScope($a2, $opdModuleId, 'COMPLETED', now()->subMonths(4));
        $this->makeScore($a2, 41.3, $maturityLevels[3]);

        $a3 = $this->makeAssessment($target2, $project2, $tier, $user, 'COMPLETE', now()->subWeeks(3));
        $this->makeScope($a3, $opdModuleId, 'COMPLETED', now()->subWeeks(3));
        $this->makeScore($a3, 55.8, $maturityLevels[3]);

        // Project 3 — Kenya Referral Hospital, not yet assessed
        $target3 = $this->makeTarget($workspace, 'HEALTH_FACILITY', 'Nairobi District Referral Hospital', 'Kenya', 'Nairobi');
        $project3 = $this->makeProject($workspace, $user, 'Nairobi Referral Hospital Baseline');
        $this->attachTarget($project3, $target3);

        // Project 4 — Uganda PHC, in progress
        $target4 = $this->makeTarget($workspace, 'HEALTH_FACILITY', 'Kampala Health Centre IV', 'Uganda', 'Kampala');
        $project4 = $this->makeProject($workspace, $user, 'Kampala Health Centre Assessment');
        $this->attachTarget($project4, $target4);

        $a4 = $this->makeAssessment($target4, $project4, $tier, $user, 'IN_PROGRESS', null);
        $this->makeScope($a4, $ipdModuleId, 'PENDING', null);
    }

    private function makeTarget(Workspace $workspace, string $typeCode, string $name, string $country, string $region): Target
    {
        return Target::create([
            'owner_workspace_id' => $workspace->workspace_id,
            'target_type_code' => $typeCode,
            'name' => $name,
            'country' => $country,
            'region' => $region,
        ]);
    }

    private function makeProject(Workspace $workspace, User $user, string $name): Project
    {
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

    private function makeAssessment(Target $target, Project $project, object $tier, User $user, string $status, ?Carbon $completedAt): Assessment
    {
        return Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tier->assessment_tier_id,
            'scope_type' => 'FULL_TARGET',
            'status' => $status,
            'publish_status' => $status === 'COMPLETE' ? 'PUBLISHED' : 'DRAFT',
            'assessor_name' => $user->name,
            'started_at' => $completedAt ? $completedAt->copy()->subHours(2) : now()->subHour(),
            'completed_at' => $completedAt,
        ]);
    }

    private function makeScope(Assessment $assessment, int $moduleId, string $status, ?\DateTimeInterface $completedAt): void
    {
        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $moduleId,
            'in_scope' => true,
            'is_category_default' => true,
            'status' => $status,
            'completed_at' => $completedAt,
        ]);
    }

    private function makeScore(Assessment $assessment, float $score, int $maturityLevelId): void
    {
        AssessmentScore::create([
            'assessment_id' => $assessment->assessment_id,
            'overall_score' => $score,
            'maturity_level_id' => $maturityLevelId,
            'calibration_status' => 'CALIBRATED',
            'calculated_at' => now(),
        ]);
    }
}
