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
        Schema::table('users', function (Blueprint $table) {
            // Add index on branch column for fast employee lookups
            $table->index('branch', 'users_branch_index');
            // Add composite index for role + branch queries
            $table->index(['role', 'branch'], 'users_role_branch_index');
        });

        Schema::table('branches', function (Blueprint $table) {
            // Add index on name for fast branch lookups
            $table->index('name', 'branches_name_index');
            // Add index on is_active for filtering active branches
            $table->index('is_active', 'branches_active_index');
            // Add composite index for active branch lookups
            $table->index(['is_active', 'name'], 'branches_active_name_index');
        });

        Schema::table('tickets', function (Blueprint $table) {
            // Add index on branch_id for fast ticket filtering by branch
            $table->index('branch_id', 'tickets_branch_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_branch_index');
            $table->dropIndex('users_role_branch_index');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex('branches_name_index');
            $table->dropIndex('branches_active_index');
            $table->dropIndex('branches_active_name_index');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_branch_id_index');
        });
    }
};