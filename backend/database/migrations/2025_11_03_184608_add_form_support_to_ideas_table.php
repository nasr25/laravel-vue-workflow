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
        Schema::table('ideas', function (Blueprint $table) {
            $table->foreignId('form_type_id')->nullable()->after('id')->constrained('form_types')->onDelete('cascade');
            $table->foreignId('workflow_template_id')->nullable()->after('form_type_id')->constrained('workflow_templates')->onDelete('set null');
            $table->json('form_data')->nullable()->after('description'); // For custom form fields in future
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropForeign(['form_type_id']);
            $table->dropForeign(['workflow_template_id']);
            $table->dropColumn(['form_type_id', 'workflow_template_id', 'form_data']);
        });
    }
};
