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
        Schema::table('tickets', function (Blueprint $table) {
            $table->date('scheduled_date')->nullable()->after('branch_id');
            $table->time('scheduled_time')->nullable()->after('scheduled_date');
            $table->text('schedule_notes')->nullable()->after('scheduled_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['scheduled_date', 'scheduled_time', 'schedule_notes']);
        });
    }
};