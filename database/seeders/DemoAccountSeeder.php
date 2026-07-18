<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Demo Starter User',
                'email' => 'starter@vytte.test',
                'plan' => 'STARTER',
                'workspace_name' => 'Starter Demo Workspace',
                'platform_role' => null,
            ],
            [
                'name' => 'Demo Professional User',
                'email' => 'professional@vytte.test',
                'plan' => 'PROFESSIONAL',
                'workspace_name' => 'Professional Demo Workspace',
                'platform_role' => null,
            ],
            [
                'name' => 'Demo Organization User',
                'email' => 'organization@vytte.test',
                'plan' => 'ORGANIZATION',
                'workspace_name' => 'Organization Demo Workspace',
                'platform_role' => null,
            ],
            [
                'name' => 'Platform Admin',
                'email' => 'admin@vytte.test',
                'plan' => 'STARTER',
                'workspace_name' => 'Admin Workspace',
                'platform_role' => 'PLATFORM_ADMIN',
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'platform_role' => $account['platform_role'],
                ]
            );

            $workspace = Workspace::where('name', $account['workspace_name'])->first();

            if (! $workspace) {
                $workspace = Workspace::create([
                    'name' => $account['workspace_name'],
                    'workspace_type' => 'INDIVIDUAL',
                    'slug' => Str::slug($account['workspace_name']),
                    'plan' => $account['plan'],
                    'status' => 'ACTIVE',
                ]);
            } else {
                $workspace->update([
                    'plan' => $account['plan'],
                    'status' => 'ACTIVE',
                ]);
            }

            WorkspaceMember::updateOrCreate(
                ['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id],
                ['role' => 'OWNER']
            );

            $user->update(['active_workspace_id' => $workspace->workspace_id]);
        }
    }
}
