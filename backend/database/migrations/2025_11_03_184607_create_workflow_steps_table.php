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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained('workflow_templates')->onDelete('cascade');
            $table->integer('step_order'); // 1, 2, 3...
            $table->string('step_name'); // e.g., "Finance Review", "Manager Approval"
            $table->enum('approver_type', ['employee', 'manager', 'either'])->default('employee');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->integer('required_approvals_count')->default(1); // How many approvals needed
            $table->enum('approval_mode', ['all', 'any_count'])->default('any_count');
            // all: All assigned approvers must approve
            // any_count: Any X approvers out of assigned must approve
            $table->boolean('can_skip')->default(false);
            $table->integer('timeout_hours')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
