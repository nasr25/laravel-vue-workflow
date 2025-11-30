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
        Schema::table('idea_approvals', function (Blueprint $table) {
            $table->timestamp('arrived_at')->nullable()->after('comments');
            $table->timestamp('reminder_sent_at')->nullable()->after('arrived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('idea_approvals', function (Blueprint $table) {
            $table->dropColumn(['arrived_at', 'reminder_sent_at']);
        });
    }
};
