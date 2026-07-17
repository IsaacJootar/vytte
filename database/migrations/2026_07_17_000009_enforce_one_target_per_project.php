<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('project_targets')
            ->select('project_id')
            ->groupBy('project_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'Cannot enforce one setting per project while project '.$duplicate->project_id.' has multiple targets. Resolve it explicitly first.'
            );
        }

        DB::statement('CREATE UNIQUE INDEX project_targets_one_target_per_project ON project_targets (project_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS project_targets_one_target_per_project');
    }
};
