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
        // Step 1: Add 'temporarily_pending' to the ENUM
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft','pending','temporarily_pending','first_screening','final_review','in_review','in_progress','need_more_details','missing_requirement','approved','rejected','completed') NOT NULL DEFAULT 'draft'");

        // Step 2: Migrate existing 'pending' records to 'temporarily_pending'
        DB::table('requests')->where('status', 'pending')->update(['status' => 'temporarily_pending']);

        // Step 3: Also update any transition records that reference 'pending'
        DB::table('request_transitions')->where('from_status', 'pending')->update(['from_status' => 'temporarily_pending']);
        DB::table('request_transitions')->where('to_status', 'pending')->update(['to_status' => 'temporarily_pending']);

        // Step 4: Remove 'pending' from the ENUM
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft','temporarily_pending','first_screening','final_review','in_review','in_progress','need_more_details','missing_requirement','approved','rejected','completed') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add 'pending' back to the ENUM
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft','pending','temporarily_pending','first_screening','final_review','in_review','in_progress','need_more_details','missing_requirement','approved','rejected','completed') NOT NULL DEFAULT 'draft'");

        // Step 2: Migrate 'temporarily_pending' records back to 'pending'
        DB::table('requests')->where('status', 'temporarily_pending')->update(['status' => 'pending']);

        // Step 3: Revert transition records
        DB::table('request_transitions')->where('from_status', 'temporarily_pending')->update(['from_status' => 'pending']);
        DB::table('request_transitions')->where('to_status', 'temporarily_pending')->update(['to_status' => 'pending']);

        // Step 4: Remove 'temporarily_pending' from the ENUM
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft','pending','first_screening','final_review','in_review','in_progress','need_more_details','missing_requirement','approved','rejected','completed') NOT NULL DEFAULT 'draft'");
    }
};
