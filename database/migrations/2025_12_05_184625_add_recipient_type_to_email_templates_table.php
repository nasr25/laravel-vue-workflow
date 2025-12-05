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
        Schema::table('email_templates', function (Blueprint $table) {
            $table->enum('recipient_type', ['user', 'admin', 'manager'])
                  ->default('user')
                  ->after('event_type');
        });

        // Update existing records to set recipient_type = 'user'
        \DB::table('email_templates')
            ->whereNull('recipient_type')
            ->update(['recipient_type' => 'user']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('recipient_type');
        });
    }
};
