<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->performanceStages() as $stage) {
            DB::table('maturity_levels')->where('level_number', $stage['level_number'])->update($stage);
        }
    }

    public function down(): void
    {
        $legacy = [
            ['level_number' => 1, 'level_name' => 'Data Collection', 'description' => 'Collects routine data but rarely analyzes or uses it.'],
            ['level_number' => 2, 'level_name' => 'Data Reporting', 'description' => 'Submits reports consistently with limited internal use.'],
            ['level_number' => 3, 'level_name' => 'Data Analysis', 'description' => 'Reviews and interprets data for selected activities.'],
            ['level_number' => 4, 'level_name' => 'Data-Driven Management', 'description' => 'Uses data regularly to guide operational and clinical decisions.'],
            ['level_number' => 5, 'level_name' => 'Learning Health System', 'description' => 'Continuously improves through data, feedback, and innovation.'],
        ];
        foreach ($legacy as $level) {
            DB::table('maturity_levels')->where('level_number', $level['level_number'])->update($level);
        }
    }

    private function performanceStages(): array
    {
        return [
            ['level_number' => 1, 'level_name' => 'Urgent Action', 'description' => 'Severe gaps require immediate stabilization and follow-up.'],
            ['level_number' => 2, 'level_name' => 'Foundational', 'description' => 'Essential foundations exist in part but major gaps remain.'],
            ['level_number' => 3, 'level_name' => 'Developing', 'description' => 'Core capabilities are present but performance is inconsistent.'],
            ['level_number' => 4, 'level_name' => 'Established', 'description' => 'Most expected capabilities work reliably; targeted improvement remains.'],
            ['level_number' => 5, 'level_name' => 'Leading', 'description' => 'Performance is strong; sustain it, learn from it, and share what works.'],
        ];
    }
};
