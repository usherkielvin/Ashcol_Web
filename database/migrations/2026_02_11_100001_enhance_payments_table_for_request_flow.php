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
        Schema::table('payments', function (Blueprint $table) {
            // Add confirmed_at timestamp for when customer confirms payment
            $table->timestamp('confirmed_at')->nullable()->after('completed_at');
        });

        // Update payment_method enum to include more options
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash', 'online', 'credit_card', 'gcash', 'bank_transfer') DEFAULT 'cash'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });

        // Revert payment_method enum to original values
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash', 'online') DEFAULT 'cash'");
    }
};
