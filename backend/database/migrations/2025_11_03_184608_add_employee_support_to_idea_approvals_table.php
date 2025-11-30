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
            // Rename manager_id to approver_id for flexibility
            $table->renameColumn('manager_id', 'approver_id');
        });

        // Add new columns after rename
        Schema::table('idea_approvals', function (Blueprint $table) {
            $table->enum('approver_type', ['employee', 'manager'])->default('manager')->after('approver_id');
            $table->foreignId('workflow_step_id')->nullable()->after('department_id')->constrained('workflow_steps')->onDelete('set null');
            $table->integer('approvals_received')->default(0)->after('status'); // Count of approvals for this step
            $table->integer('approvals_required')->default(1)->after('approvals_received'); // Required approvals for this step
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('idea_approvals', function (Blueprint $table) {
            $table->dropForeign(['workflow_step_id']);
            $table->dropColumn(['approver_type', 'workflow_step_id', 'approvals_received', 'approvals_required']);
        });

        Schema::table('idea_approvals', function (Blueprint $table) {
            $table->renameColumn('approver_id', 'manager_id');
        });
    }
};
