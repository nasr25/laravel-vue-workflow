<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alter the status ENUM to include first_screening and final_review
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft','pending','first_screening','final_review','in_review','in_progress','need_more_details','missing_requirement','approved','rejected','completed') NOT NULL DEFAULT 'draft'");

        // Migrate existing data: update pending requests at Dept A
        // (without expected_execution_date and without workflow_path_id) to first_screening
        $deptA = DB::table('departments')->where('is_department_a', true)->first();
        if ($deptA) {
            DB::table('requests')
                ->where('status', 'pending')
                ->where('current_department_id', $deptA->id)
                ->whereNull('expected_execution_date')
                ->whereNull('workflow_path_id')
                ->update(['status' => 'first_screening']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert first_screening back to pending
        DB::table('requests')
            ->where('status', 'first_screening')
            ->update(['status' => 'pending']);

        // Revert final_review back to in_review
        DB::table('requests')
            ->where('status', 'final_review')
            ->update(['status' => 'in_review']);

        // Remove the new enum values
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft','pending','in_review','in_progress','need_more_details','missing_requirement','approved','rejected','completed') NOT NULL DEFAULT 'draft'");
    }
};
