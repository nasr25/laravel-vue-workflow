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
        Schema::table('request_attachments', function (Blueprint $table) {
            $table->string('stage')->nullable()->after('file_size');
            $table->timestamp('uploaded_at')->nullable()->after('stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_attachments', function (Blueprint $table) {
            $table->dropColumn(['stage', 'uploaded_at']);
        });
    }
};
