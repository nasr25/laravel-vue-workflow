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
        // SQLite doesn't support changing ENUM to VARCHAR directly
        // We need to recreate the table

        // Get all existing user data
        $users = DB::table('users')->get();

        // Drop and recreate the column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('email');
        });

        // Restore the role data
        foreach ($users as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['role' => $user->role]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get all existing user data
        $users = DB::table('users')->get();

        // Drop and recreate as enum
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'employee', 'user'])->default('user')->after('email');
        });

        // Restore the role data (only if it matches enum values)
        foreach ($users as $user) {
            $validRoles = ['admin', 'manager', 'employee', 'user'];
            $role = in_array($user->role, $validRoles) ? $user->role : 'user';
            DB::table('users')
                ->where('id', $user->id)
                ->update(['role' => $role]);
        }
    }
};
