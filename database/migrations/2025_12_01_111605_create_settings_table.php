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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Setting key (e.g., 'site_name', 'logo')
            $table->text('value')->nullable(); // Setting value
            $table->string('type')->default('text'); // Type: text, image, number, boolean, json
            $table->text('description')->nullable(); // Description of the setting
            $table->string('group')->default('general'); // Group: general, appearance, system, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
