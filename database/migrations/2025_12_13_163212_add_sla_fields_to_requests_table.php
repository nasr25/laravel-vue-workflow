<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->timestamp('current_stage_started_at')->nullable()->after('updated_at');
            $table->timestamp('sla_reminder_sent_at')->nullable()->after('current_stage_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['current_stage_started_at', 'sla_reminder_sent_at']);
        });
    }
};
