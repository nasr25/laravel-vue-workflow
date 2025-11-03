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
        Schema::create('form_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Budget Request", "Leave Request"
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Bootstrap icon name
            $table->boolean('has_file_upload')->default(true);
            $table->json('file_types_allowed')->nullable(); // ["pdf", "docx", "xlsx"]
            $table->integer('max_file_size_mb')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_types');
    }
};
