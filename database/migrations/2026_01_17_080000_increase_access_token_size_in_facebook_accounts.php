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
        Schema::table('facebook_accounts', function (Blueprint $table) {
            // Change access_token from VARCHAR(255) to TEXT to accommodate long Facebook tokens
            $table->text('access_token')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facebook_accounts', function (Blueprint $table) {
            $table->string('access_token')->nullable()->change();
        });
    }
};
