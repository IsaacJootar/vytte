<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('platform_settings')->insertOrIgnore([
            [
                'key' => 'email.notifications_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Toggle all outbound email. OFF by default until a verified Resend domain is configured.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.from_address',
                'value' => 'hello@vytte.com',
                'type' => 'string',
                'description' => 'The from address used for all outbound email.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
