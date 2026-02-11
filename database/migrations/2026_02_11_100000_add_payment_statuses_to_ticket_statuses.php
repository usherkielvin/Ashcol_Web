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
        
        // Add new payment-related status
        $statuses = [
            ['name' => 'Pending Payment', 'color' => '#F59E0B', 'is_default' => false],
            // Note: "Paid" status removed - using "Completed" instead
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
        
        // Update any existing "Paid" tickets to "Completed"
        $completedStatus = DB::table('ticket_statuses')->where('name', 'Completed')->first();
        $paidStatus = DB::table('ticket_statuses')->where('name', 'Paid')->first();
        
        if ($completedStatus && $paidStatus) {
            DB::table('tickets')
                ->where('status_id', $paidStatus->id)
                ->update(['status_id' => $completedStatus->id]);
        }
        
        // Remove "Paid" status if it exists
        DB::table('ticket_statuses')->where('name', 'Paid')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the payment-related status
        DB::table('ticket_statuses')
            ->where('name', 'Pending Payment')
            ->delete();
    }
};
