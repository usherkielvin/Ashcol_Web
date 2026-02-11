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
        $now = now();
        
        // Add new payment-related statuses
        $statuses = [
            ['name' => 'Pending Payment', 'color' => '#F59E0B', 'is_default' => false],
            ['name' => 'Paid', 'color' => '#10B981', 'is_default' => false],
        ];

        foreach ($statuses as $status) {
            // Check if status already exists
            $exists = DB::table('ticket_statuses')
                ->where('name', $status['name'])
                ->exists();
            
            if (!$exists) {
                DB::table('ticket_statuses')->insert([
                    'name' => $status['name'],
                    'color' => $status['color'],
                    'is_default' => $status['is_default'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the payment-related statuses
        DB::table('ticket_statuses')
            ->whereIn('name', ['Pending Payment', 'Paid'])
            ->delete();
    }
};
