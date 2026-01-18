<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'facebook_id')) {
            return;
        }

        // Drop unique index FIRST (required before dropColumn on SQLite/PostgreSQL)
        $indexName = 'users_facebook_id_unique';
        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE users DROP INDEX IF EXISTS ' . $indexName);
            } else {
                DB::statement('DROP INDEX IF EXISTS ' . $indexName);
            }
        } catch (\Throwable $e) {
            // Index may not exist (e.g. if add_facebook_id never ran)
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('facebook_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore facebook_id column (for rollback)
            if (!Schema::hasColumn('users', 'facebook_id')) {
                $table->string('facebook_id')->nullable()->unique()->after('email');
            }
        });
    }
};
