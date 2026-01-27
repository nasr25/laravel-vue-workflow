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
        // Create pivot table for many-to-many relationship
        Schema::create('request_idea_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->onDelete('cascade');
            $table->foreignId('idea_type_id')->constrained('idea_types')->onDelete('cascade');
            $table->timestamps();

            // Ensure unique combination
            $table->unique(['request_id', 'idea_type_id']);
        });

        // Migrate existing data from requests.idea_type_id to pivot table
        DB::table('requests')
            ->whereNotNull('idea_type_id')
            ->orderBy('id')
            ->chunk(100, function ($requests) {
                foreach ($requests as $request) {
                    DB::table('request_idea_type')->insert([
                        'request_id' => $request->id,
                        'idea_type_id' => $request->idea_type_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // Drop the old idea_type_id column from requests table
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['idea_type_id']);
            $table->dropColumn('idea_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore idea_type_id column
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('idea_type_id')->nullable()->after('description')->constrained('idea_types')->onDelete('set null');
        });

        // Migrate first idea type back to requests table
        DB::table('request_idea_type')
            ->orderBy('request_id')
            ->orderBy('id')
            ->chunk(100, function ($pivotRecords) {
                $processedRequests = [];
                foreach ($pivotRecords as $pivot) {
                    // Only set the first idea type for each request
                    if (!in_array($pivot->request_id, $processedRequests)) {
                        DB::table('requests')
                            ->where('id', $pivot->request_id)
                            ->update(['idea_type_id' => $pivot->idea_type_id]);
                        $processedRequests[] = $pivot->request_id;
                    }
                }
            });

        // Drop pivot table
        Schema::dropIfExists('request_idea_type');
    }
};
