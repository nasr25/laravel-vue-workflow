<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add column only if it doesn't exist
        if (!Schema::hasColumn('survey_responses', 'request_id')) {
            Schema::table('survey_responses', function (Blueprint $table) {
                $table->unsignedBigInteger('request_id')->nullable()->after('user_id');
            });
        }

        $hasOldIndex = $this->indexExists('survey_responses', 'survey_responses_survey_id_user_id_unique');
        $hasNewIndex = $this->indexExists('survey_responses', 'survey_responses_unique_per_request');

        if ($hasOldIndex) {
            Schema::table('survey_responses', function (Blueprint $table) {
                // Drop foreign key on survey_id first (MySQL requires this before dropping the unique index it depends on)
                if ($this->foreignKeyExists('survey_responses', 'survey_responses_survey_id_foreign')) {
                    $table->dropForeign(['survey_id']);
                }

                // Now drop the unique index
                $table->dropUnique(['survey_id', 'user_id']);
            });

            // Re-add the foreign key
            Schema::table('survey_responses', function (Blueprint $table) {
                if (!$this->foreignKeyExists('survey_responses', 'survey_responses_survey_id_foreign')) {
                    $table->foreign('survey_id')->references('id')->on('surveys')->cascadeOnDelete();
                }
            });
        }

        if (!$hasNewIndex) {
            Schema::table('survey_responses', function (Blueprint $table) {
                $table->unique(['survey_id', 'user_id', 'request_id'], 'survey_responses_unique_per_request');
            });
        }
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropUnique('survey_responses_unique_per_request');
            $table->unique(['survey_id', 'user_id']);
            $table->dropColumn('request_id');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");
            return collect($indexes)->contains('name', $indexName);
        }

        $indexes = $connection->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

    private function foreignKeyExists(string $table, string $keyName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return false; // SQLite doesn't enforce foreign keys the same way
        }

        $database = $connection->getDatabaseName();
        $keys = $connection->select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$database, $table, $keyName]
        );
        return count($keys) > 0;
    }
};
