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
        Schema::create('path_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            $table->foreignId('path_evaluation_question_id')->constrained('path_evaluation_questions')->onDelete('cascade');
            $table->foreignId('evaluated_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_applied'); // true = applied, false = not applied
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ensure one evaluation per question per request
            $table->unique(['request_id', 'path_evaluation_question_id'], 'req_path_eval_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('path_evaluations');
    }
};
