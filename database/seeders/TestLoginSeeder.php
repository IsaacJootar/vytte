<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Two accounts for hands-on testing of the official platform.
 *
 * Deliberately NOT part of the production DatabaseSeeder — a real deployment ships with no
 * accounts. This is run explicitly:
 *
 *     php artisan db:seed --class=TestLoginSeeder
 *
 * One platform administrator, who governs Vytte itself and lands on /admin. One
 * professional workspace owner on the Professional plan, who lands on their own workspace
 * and can create projects and assessments from the official catalogue.
 *
 * Both use the same test password. Remove this seeder, or these accounts, before real
 * customers arrive.
 */
class TestLoginSeeder extends Seeder
{
    private const PASSWORD = 'Vytte2026!';

    public function run(): void
    {
        $this->platformAdmin();
        $this->professionalOwner();

        $this->command?->info('Test logins ready. Password for both: '.self::PASSWORD);
        $this->command?->info('  Platform admin:  platform@vytte.test  ->  /admin');
        $this->command?->info('  Professional:    pro@vytte.test       ->  /dashboard');
    }

    private function platformAdmin(): void
    {
        User::updateOrCreate(
            ['email' => 'platform@vytte.test'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make(self::PASSWORD),
                'platform_role' => 'PLATFORM_ADMIN',
                'account_type' => 'PLATFORM',
                'email_verified_at' => now(),
            ]
        );
    }

    private function professionalOwner(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'pro@vytte.test'],
            [
                'name' => 'Test Professional',
                'password' => Hash::make(self::PASSWORD),
                'account_type' => 'CUSTOMER',
                'email_verified_at' => now(),
            ]
        );

        $workspace = Workspace::firstOrCreate(
            ['slug' => 'test-professional-workspace'],
            [
                'name' => 'Test Professional Workspace',
                'workspace_type' => 'ORGANISATION',
                'plan' => 'PROFESSIONAL',
                'status' => 'ACTIVE',
                'settings' => [],
            ]
        );

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id],
            ['role' => 'OWNER']
        );

        $user->update(['active_workspace_id' => $workspace->workspace_id]);
    }
}
