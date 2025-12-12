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
        // For SQLite, we need to recreate the table with the new enum values
        // First, check if we're using SQLite
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, so we'll use a workaround
            // by allowing any status value (removing CHECK constraint)
            DB::statement('PRAGMA foreign_keys=off;');

            // Create new table with updated status column
            DB::statement('
                CREATE TABLE requests_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    title VARCHAR NOT NULL,
                    description TEXT NOT NULL,
                    user_id INTEGER NOT NULL,
                    current_department_id INTEGER,
                    current_user_id INTEGER,
                    workflow_path_id INTEGER,
                    status VARCHAR DEFAULT \'draft\' NOT NULL,
                    rejection_reason TEXT,
                    additional_details TEXT,
                    submitted_at DATETIME,
                    completed_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    deleted_at DATETIME,
                    expected_execution_date DATE,
                    idea_type VARCHAR,
                    department_id INTEGER,
                    benefits TEXT,
                    idea_type_id INTEGER,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (current_department_id) REFERENCES departments(id),
                    FOREIGN KEY (current_user_id) REFERENCES users(id),
                    FOREIGN KEY (workflow_path_id) REFERENCES workflow_paths(id),
                    FOREIGN KEY (department_id) REFERENCES departments(id),
                    FOREIGN KEY (idea_type_id) REFERENCES idea_types(id)
                )
            ');

            // Copy data from old table to new table
            DB::statement('INSERT INTO requests_new SELECT * FROM requests');

            // Drop old table
            DB::statement('DROP TABLE requests');

            // Rename new table to original name
            DB::statement('ALTER TABLE requests_new RENAME TO requests');

            DB::statement('PRAGMA foreign_keys=on;');
        } else {
            // For other databases like MySQL/PostgreSQL
            DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft', 'pending', 'in_review', 'in_progress', 'need_more_details', 'missing_requirement', 'approved', 'rejected', 'completed') DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible as we're removing constraints
        // Existing data with new statuses would be lost
    }
};
