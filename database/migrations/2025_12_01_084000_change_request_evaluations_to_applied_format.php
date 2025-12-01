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
        // For SQLite, we need to recreate the table
        // Save existing data
        $evaluations = DB::table('request_evaluations')->get();

        // Drop and recreate table
        Schema::dropIfExists('request_evaluations');

        Schema::create('request_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            $table->foreignId('evaluation_question_id')->constrained('evaluation_questions')->onDelete('cascade');
            $table->foreignId('evaluated_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_applied'); // Applied/Not Applied instead of score
            $table->text('notes')->nullable(); // Optional notes for this answer
            $table->timestamps();

            // Unique constraint: one evaluation per question per request
            $table->unique(['request_id', 'evaluation_question_id'], 'request_question_unique');
        });

        // Restore data (convert score to is_applied: score >= 5 = true, < 5 = false)
        foreach ($evaluations as $evaluation) {
            DB::table('request_evaluations')->insert([
                'id' => $evaluation->id,
                'request_id' => $evaluation->request_id,
                'evaluation_question_id' => $evaluation->evaluation_question_id,
                'evaluated_by' => $evaluation->evaluated_by,
                'is_applied' => $evaluation->score >= 5, // Convert score to boolean
                'notes' => $evaluation->notes,
                'created_at' => $evaluation->created_at,
                'updated_at' => $evaluation->updated_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Save existing data
        $evaluations = DB::table('request_evaluations')->get();

        // Drop and recreate with old structure
        Schema::dropIfExists('request_evaluations');

        Schema::create('request_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            $table->foreignId('evaluation_question_id')->constrained('evaluation_questions')->onDelete('cascade');
            $table->foreignId('evaluated_by')->constrained('users')->onDelete('cascade');
            $table->integer('score'); // Score out of 100 for this question
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'evaluation_question_id'], 'request_question_unique');
        });

        // Restore data (convert is_applied to score: true = 10, false = 1)
        foreach ($evaluations as $evaluation) {
            DB::table('request_evaluations')->insert([
                'id' => $evaluation->id,
                'request_id' => $evaluation->request_id,
                'evaluation_question_id' => $evaluation->evaluation_question_id,
                'evaluated_by' => $evaluation->evaluated_by,
                'score' => $evaluation->is_applied ? 10 : 1,
                'notes' => $evaluation->notes,
                'created_at' => $evaluation->created_at,
                'updated_at' => $evaluation->updated_at,
            ]);
        }
    }
};
