<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\ModuleDomain;
use App\Models\PlatformSetting;
use App\Models\Question;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PlatformGovernedDemoSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return $user;
    }

    // ─── Admin gate ────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_admin(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_admin(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform Overview');
    }

    // ─── Workspaces ───────────────────────────────────────────────

    public function test_admin_can_list_workspaces(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Test Workspace']);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.workspaces.index'))
            ->assertOk()
            ->assertSee('Test Workspace');
    }

    public function test_admin_can_search_workspaces(): void
    {
        Workspace::factory()->create(['name' => 'Alpha Corp']);
        Workspace::factory()->create(['name' => 'Beta Inc']);

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.workspaces.index', ['search' => 'Alpha']));

        $response->assertOk()
            ->assertSee('Alpha Corp')
            ->assertDontSee('Beta Inc');
    }

    public function test_admin_can_view_workspace_detail(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Detail Workspace']);
        $user = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.workspaces.show', $workspace))
            ->assertOk()
            ->assertSee('Detail Workspace')
            ->assertSee($user->name);
    }

    public function test_non_admin_cannot_list_workspaces(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.workspaces.index'))
            ->assertForbidden();
    }

    // ─── Platform settings ────────────────────────────────────────

    public function test_admin_can_view_settings(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Email Notifications');
    }

    public function test_admin_can_enable_email_notifications(): void
    {
        Cache::forget('platform_setting_email.notifications_enabled');

        $this->actingAs($this->makeAdmin())
            ->put(route('admin.settings.update'), ['email_notifications_enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue(PlatformSetting::get('email.notifications_enabled', false));
    }

    public function test_admin_can_disable_email_notifications(): void
    {
        PlatformSetting::set('email.notifications_enabled', true, 'boolean');

        $this->actingAs($this->makeAdmin())
            ->put(route('admin.settings.update'), [])
            ->assertRedirect();

        $this->assertFalse(PlatformSetting::get('email.notifications_enabled', false));
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $this->actingAs($this->makeUser())
            ->put(route('admin.settings.update'), ['email_notifications_enabled' => '1'])
            ->assertForbidden();
    }

    // ─── Module management ───────────────────────────────────────

    public function test_admin_can_list_modules(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('Module Library');
    }

    public function test_admin_can_view_module_detail(): void
    {
        $module = AssessmentModule::first();
        $this->assertNotNull($module, 'Reference data must be seeded');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.modules.show', $module))
            ->assertOk()
            ->assertSee($module->module_name);
    }

    public function test_admin_can_edit_module(): void
    {
        $module = AssessmentModule::first();

        $this->actingAs($this->makeAdmin())
            ->put(route('admin.modules.update', $module), [
                'module_name' => 'Updated Module Name',
                'primary_respondent' => 'Senior Nurse',
                'estimated_duration_minutes' => 30,
                'data_collection_methods' => 'Interview',
            ])
            ->assertRedirect(route('admin.modules.show', $module));

        $this->assertDatabaseHas('assessment_modules', [
            'module_id' => $module->module_id,
            'module_name' => 'Updated Module Name',
        ]);
    }

    public function test_admin_can_deactivate_module(): void
    {
        $module = AssessmentModule::first();
        $module->update(['is_active' => true]);

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.modules.toggle', $module))
            ->assertRedirect();

        $this->assertFalse($module->fresh()->is_active);
    }

    public function test_admin_can_reactivate_module(): void
    {
        $module = AssessmentModule::first();
        $module->update(['is_active' => false]);

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.modules.toggle', $module))
            ->assertRedirect();

        $this->assertTrue($module->fresh()->is_active);
    }

    public function test_non_admin_cannot_edit_module(): void
    {
        $module = AssessmentModule::first();

        $this->actingAs($this->makeUser())
            ->put(route('admin.modules.update', $module), ['module_name' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Domain management ───────────────────────────────────────

    public function test_admin_can_edit_domain_label(): void
    {
        $domain = ModuleDomain::first();
        $this->assertNotNull($domain, 'Reference data must provide domains');

        $this->actingAs($this->makeAdmin())
            ->put(route('admin.domains.update', $domain), ['domain_label' => 'New Domain Label'])
            ->assertRedirect();

        $this->assertDatabaseHas('module_domains', [
            'module_domain_id' => $domain->module_domain_id,
            'domain_label' => 'New Domain Label',
        ]);
    }

    // ─── Question management ─────────────────────────────────────

    public function test_admin_can_toggle_question_active(): void
    {
        $question = Question::first();
        $this->assertNotNull($question, 'Reference data must provide questions');

        $originalActive = $question->is_active;

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.questions.toggle', $question))
            ->assertRedirect();

        $this->assertNotEquals($originalActive, $question->fresh()->is_active);
    }

    public function test_admin_can_edit_question_text(): void
    {
        $question = Question::first();

        $this->actingAs($this->makeAdmin())
            ->put(route('admin.questions.update', $question), [
                'question_text' => 'Updated question text for the platform?',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('questions', [
            'question_id' => $question->question_id,
            'question_text' => 'Updated question text for the platform?',
        ]);
    }

    public function test_non_admin_cannot_toggle_question(): void
    {
        $question = Question::first();

        $this->actingAs($this->makeUser())
            ->patch(route('admin.questions.toggle', $question))
            ->assertForbidden();
    }

    // ─── Module import ───────────────────────────────────────────

    public function test_admin_can_view_import_form(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.modules.import'))
            ->assertOk()
            ->assertSee('Import Assessment Module');
    }

    public function test_admin_can_import_module_from_json(): void
    {
        $json = json_encode([
            'module_code' => 'TESTMOD',
            'module_name' => 'Test Import Module',
            'target_type_code' => 'COMMUNITY',
            'primary_respondent' => 'Community Leader',
            'estimated_duration_minutes' => 20,
            'domains' => [
                [
                    'domain_number' => 1,
                    'domain_label' => 'Test Domain',
                    'questions' => [
                        [
                            'question_code' => 'TMOD_Q001',
                            'question_text' => 'Is the community engaged?',
                            'options' => [
                                ['option_label' => 'Yes', 'score_weight' => 100],
                                ['option_label' => 'No', 'score_weight' => 0],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('module.json', $json);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.modules.import.store'), ['json_file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_modules', [
            'module_code' => 'TESTMOD',
            'module_name' => 'Test Import Module',
        ]);
        $this->assertDatabaseHas('module_domains', ['domain_label' => 'Test Domain']);
        $this->assertDatabaseHas('questions', ['question_code' => 'TMOD_Q001']);
        $this->assertDatabaseHas('question_options', ['option_label' => 'Yes']);
    }

    public function test_import_rejects_duplicate_module_code(): void
    {
        $existingModule = AssessmentModule::first();

        $json = json_encode([
            'module_code' => $existingModule->module_code,
            'module_name' => 'Duplicate',
            'target_type_code' => $existingModule->target_type_code,
            'domains' => [],
        ]);

        $file = UploadedFile::fake()->createWithContent('module.json', $json);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.modules.import.store'), ['json_file' => $file])
            ->assertSessionHasErrors('json_file');
    }

    public function test_admin_can_import_numeric_question_contract(): void
    {
        $json = json_encode([
            'module_code' => 'METRICS',
            'module_name' => 'Facility Metrics',
            'target_type_code' => 'HEALTH_FACILITY',
            'domains' => [[
                'domain_number' => 1,
                'domain_label' => 'Utilization',
                'questions' => [[
                    'question_code' => 'METRICS.Q1',
                    'question_text' => 'Average bed occupancy rate',
                    'response_type' => 'NUMERIC',
                    'is_scored' => true,
                    'numeric_unit' => '%',
                    'numeric_min' => 0,
                    'numeric_max' => 100,
                    'numeric_step' => 0.1,
                    'numeric_bands' => [
                        ['min_value' => 0, 'max_value' => 50, 'score_weight' => 0],
                        ['min_value' => 50, 'max_value' => 80, 'score_weight' => 100],
                        ['min_value' => 80, 'max_value' => 100, 'score_weight' => 50],
                    ],
                ]],
            ]],
        ]);

        $file = UploadedFile::fake()->createWithContent('numeric-module.json', $json);
        $this->actingAs($this->makeAdmin())
            ->post(route('admin.modules.import.store'), ['json_file' => $file])
            ->assertRedirect();

        $question = Question::where('question_code', 'METRICS.Q1')->firstOrFail();
        $this->assertSame('NUMERIC', $question->questionType->type_code);
        $this->assertSame('%', $question->numeric_unit);
        $this->assertCount(3, $question->numericBands);
    }

    public function test_import_rejects_response_type_without_working_input(): void
    {
        $json = json_encode([
            'module_code' => 'BROKEN',
            'module_name' => 'Broken Input',
            'target_type_code' => 'COMMUNITY',
            'domains' => [[
                'domain_number' => 1,
                'domain_label' => 'Broken',
                'questions' => [[
                    'question_code' => 'BROKEN.Q1',
                    'question_text' => 'Rank these choices',
                    'response_type' => 'RANKING',
                ]],
            ]],
        ]);

        $file = UploadedFile::fake()->createWithContent('broken-module.json', $json);
        $this->actingAs($this->makeAdmin())
            ->post(route('admin.modules.import.store'), ['json_file' => $file])
            ->assertSessionHasErrors('json_file');

        $this->assertDatabaseMissing('assessment_modules', ['module_code' => 'BROKEN']);
    }

    public function test_import_rejects_invalid_json(): void
    {
        $file = UploadedFile::fake()->createWithContent('module.json', 'not json at all');

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.modules.import.store'), ['json_file' => $file])
            ->assertSessionHasErrors('json_file');
    }

    public function test_non_admin_cannot_import_module(): void
    {
        $json = json_encode(['module_code' => 'X', 'module_name' => 'X', 'target_type_code' => 'COMMUNITY', 'domains' => []]);
        $file = UploadedFile::fake()->createWithContent('module.json', $json);

        $this->actingAs($this->makeUser())
            ->post(route('admin.modules.import.store'), ['json_file' => $file])
            ->assertForbidden();
    }
}
