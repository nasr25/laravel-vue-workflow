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
            // Add idea type foreign key
            $table->foreignId('idea_type_id')->nullable()->after('description')->constrained('idea_types')->onDelete('set null');

            // Add department_id for the initially selected department (different from current_department_id)
            $table->foreignId('department_id')->nullable()->after('idea_type_id')->constrained('departments')->onDelete('set null');

            // Add benefits field
            $table->text('benefits')->nullable()->after('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['idea_type_id']);
            $table->dropColumn('idea_type_id');

            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');

            $table->dropColumn('benefits');
        });
    }
};
