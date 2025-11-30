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
        // For SQLite, we need to recreate the table to modify enum
        if (DB::getDriverName() === 'sqlite') {
            // Disable foreign key constraints
            DB::statement('PRAGMA foreign_keys = OFF');

            // Create new table with updated enum
            Schema::create('idea_approvals_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('idea_id')->constrained('ideas')->onDelete('cascade');
                $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
                $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');
                $table->integer('step');
                $table->enum('status', ['pending', 'approved', 'rejected', 'returned', 'returned_to_dept'])->default('pending');
                $table->text('comments')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
            });

            // Copy data from old table to new table
            DB::statement('INSERT INTO idea_approvals_new SELECT * FROM idea_approvals');

            // Drop old table
            Schema::drop('idea_approvals');

            // Rename new table to original name
            Schema::rename('idea_approvals_new', 'idea_approvals');

            // Re-enable foreign key constraints
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            // For MySQL/PostgreSQL
            DB::statement("ALTER TABLE idea_approvals MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'returned', 'returned_to_dept') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For SQLite, recreate table with old enum
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('idea_approvals_old', function (Blueprint $table) {
                $table->id();
                $table->foreignId('idea_id')->constrained('ideas')->onDelete('cascade');
                $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
                $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');
                $table->integer('step');
                $table->enum('status', ['pending', 'approved', 'rejected', 'returned'])->default('pending');
                $table->text('comments')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
            });

            DB::statement('INSERT INTO idea_approvals_old SELECT * FROM idea_approvals WHERE status != "returned_to_dept"');
            Schema::drop('idea_approvals');
            Schema::rename('idea_approvals_old', 'idea_approvals');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("ALTER TABLE idea_approvals MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'returned') DEFAULT 'pending'");
        }
    }
};
