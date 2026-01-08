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
        // For SQLite, we need to recreate the table to modify the enum
        // Save existing data
        $transitions = DB::table('request_transitions')->get();

        // Drop and recreate table
        Schema::dropIfExists('request_transitions');

        Schema::create('request_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_department_id')->nullable()->constrained('departments');
            $table->foreignId('to_department_id')->nullable()->constrained('departments');
            $table->foreignId('from_user_id')->nullable()->constrained('users');
            $table->foreignId('to_user_id')->nullable()->constrained('users');
            $table->foreignId('actioned_by')->constrained('users');
            $table->enum('action', [
                'submit',
                'approve',
                'reject',
                'request_details',
                'provide_details',
                'assign',
                'assign_path',
                'complete',
                'accept_later',
                'reject_idea',
                'activate',
                'employee_accept',
                'employee_reject',
                'employee_complete',
                'progress_update',
                'resubmit'
            ]);
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        // Restore data
        foreach ($transitions as $transition) {
            DB::table('request_transitions')->insert((array) $transition);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed - this is a fix
    }
};
