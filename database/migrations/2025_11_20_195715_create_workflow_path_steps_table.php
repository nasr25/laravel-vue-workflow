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
        Schema::create('workflow_path_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_path_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->integer('step_order'); // Order of steps in the workflow
            $table->boolean('requires_approval')->default(true);
            $table->timestamps();

            $table->unique(['workflow_path_id', 'department_id', 'step_order'], 'wp_steps_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_path_steps');
    }
};
