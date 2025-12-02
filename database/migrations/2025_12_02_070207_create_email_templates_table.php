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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->unique(); // e.g., 'request.created', 'request.approved', 'request.rejected'
            $table->string('subject_en');
            $table->string('subject_ar');
            $table->text('body_en');
            $table->text('body_ar');
            $table->text('description')->nullable(); // Description of when this template is used
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
