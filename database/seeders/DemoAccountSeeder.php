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
                'name' => 'Demo Free User',
                'email' => 'free@vytte.test',
                'plan' => 'FREE',
                'workspace_name' => 'Free Demo Workspace',
                'platform_role' => null,
            ],
            [
                'name' => 'Demo Pro User',
                'email' => 'pro@vytte.test',
                'plan' => 'PRO',
                'workspace_name' => 'Pro Demo Workspace',
                'platform_role' => null,
            ],
            [
                'name' => 'Demo Agency User',
                'email' => 'agency@vytte.test',
                'plan' => 'AGENCY',
                'workspace_name' => 'Agency Demo Workspace',
                'platform_role' => null,
            ],
            [
                'name' => 'Platform Admin',
                'email' => 'admin@vytte.test',
                'plan' => 'AGENCY',
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

                WorkspaceMember::updateOrCreate(
                    ['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id],
                    ['role' => 'OWNER']
                );
            }

            $user->update(['active_workspace_id' => $workspace->workspace_id]);
        }
    }
}
