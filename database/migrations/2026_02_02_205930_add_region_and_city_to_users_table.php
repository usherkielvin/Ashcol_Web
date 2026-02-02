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
            // Region and city for all roles (customer, technician, manager, admin)
            // Kept as simple strings; normalization to a separate table can be done later if needed.
            if (!Schema::hasColumn('users', 'region')) {
                $table->string('region')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable()->after('region');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'region')) {
                $table->dropColumn('region');
            }
            if (Schema::hasColumn('users', 'city')) {
                $table->dropColumn('city');
            }
        });
    }
};
