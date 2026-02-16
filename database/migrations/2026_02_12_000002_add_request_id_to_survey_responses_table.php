<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            if(!Schema::hasColumn('survey_responses', 'request_id')) {
                Schema::table('survey_responses', function (Blueprint $table) {
                    $table->unsignedBigInteger('request_id')->nullable()->after('user_id');
                });
            }

            // Drop old unique constraint (survey_id, user_id) so user can respond per-idea
            $table->dropUnique(['survey_id', 'user_id']);

            // Add new unique constraint: one response per user per survey per request
            $table->unique(['survey_id', 'user_id', 'request_id'], 'survey_responses_unique_per_request');
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropUnique('survey_responses_unique_per_request');
            $table->unique(['survey_id', 'user_id']);
            $table->dropColumn('request_id');
        });
    }
};
