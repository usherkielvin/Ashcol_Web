<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'priority')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tickets', 'priority')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                    ->default('medium')
                    ->after('branch_id');
            });
        }
    }
};
