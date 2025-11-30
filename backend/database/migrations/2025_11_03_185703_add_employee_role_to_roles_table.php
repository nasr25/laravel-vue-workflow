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
        // Check if employee role already exists
        $exists = DB::table('roles')->where('name', 'employee')->exists();

        if (!$exists) {
            DB::table('roles')->insert([
                'name' => 'employee',
                'description' => 'Department Employee',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', 'employee')->delete();
    }
};
