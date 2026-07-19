<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the seed-once optimisation.
 *
 * The reference taxonomy and governed demonstration catalogue are seeded a single time per
 * PHPUnit process, immediately after RefreshDatabase migrates. These tests assert that the
 * baseline is present without any test seeding it, and that transaction rollback still
 * isolates every test from the ones around it.
 *
 * The two mutation tests below run alphabetically before the verification test, so if
 * rollback ever stopped working the verification would fail.
 */
class SeededBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_baseline_is_available_without_seeding_in_the_test(): void
    {
        $this->assertGreaterThan(0, AssessmentModule::count(), 'Reference taxonomy is missing.');
        $this->assertGreaterThan(0, Question::count(), 'Demonstration questions are missing.');
        $this->assertGreaterThan(
            0,
            QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->count(),
            'Published question versions are missing.'
        );
        $this->assertGreaterThan(
            0,
            DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->count(),
            'Published framework versions are missing.'
        );
        $this->assertGreaterThan(
            0,
            AssessmentCatalogueRelease::where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)->count(),
            'Published catalogue releases are missing.'
        );
    }

    public function test_b_mutations_made_in_one_test_are_rolled_back(): void
    {
        Workspace::factory()->create(['name' => 'Isolation Probe Workspace']);
        AssessmentModule::query()->update(['is_active' => false]);
        Question::query()->update(['question_text' => 'Mutated by the isolation probe.']);

        $this->assertDatabaseHas('workspaces', ['name' => 'Isolation Probe Workspace']);
        $this->assertSame(0, AssessmentModule::where('is_active', true)->count());
        $this->assertDatabaseHas('questions', ['question_text' => 'Mutated by the isolation probe.']);
    }

    public function test_c_the_next_test_sees_the_untouched_baseline(): void
    {
        $this->assertDatabaseMissing('workspaces', ['name' => 'Isolation Probe Workspace']);
        $this->assertGreaterThan(
            0,
            AssessmentModule::where('is_active', true)->count(),
            'A previous test deactivated every department and the change leaked.'
        );
        $this->assertDatabaseMissing('questions', ['question_text' => 'Mutated by the isolation probe.']);
    }

    public function test_d_demo_accounts_are_not_seeded_by_default(): void
    {
        // DemoAccountSeeder and DemoDataSeeder stay opt-in: tests asserting an empty or
        // self-built state depend on them being absent.
        $this->assertDatabaseMissing('users', ['email' => 'admin@vytte.test']);
        $this->assertSame(0, Assessment::count());
    }
}
