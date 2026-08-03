<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->string('response_state', 25)->default('ANSWERED');
            $table->json('typed_value')->nullable();
            $table->index(['assessment_id', 'response_state']);
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropIndex(['assessment_id', 'response_state']);
            $table->dropColumn(['response_state', 'typed_value']);
        });
    }
};
