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
        Schema::table('request_transitions', function (Blueprint $table) {
            // Add bilingual comment columns
            $table->text('comments_ar')->nullable()->after('to_status');
            $table->text('comments_en')->nullable()->after('comments_ar');

            // Drop the old comments column
            $table->dropColumn('comments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_transitions', function (Blueprint $table) {
            // Restore the old comments column
            $table->text('comments')->nullable()->after('to_status');

            // Drop bilingual columns
            $table->dropColumn(['comments_ar', 'comments_en']);
        });
    }
};
