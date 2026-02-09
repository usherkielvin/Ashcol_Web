<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $desiredStatuses = [
            ['name' => 'Pending', 'color' => '#F59E0B', 'is_default' => true],
            ['name' => 'Scheduled', 'color' => '#6366F1', 'is_default' => false],
            ['name' => 'Ongoing', 'color' => '#3B82F6', 'is_default' => false],
            ['name' => 'Completed', 'color' => '#10B981', 'is_default' => false],
            ['name' => 'Cancelled', 'color' => '#EF4444', 'is_default' => false],
        ];

        foreach ($desiredStatuses as $status) {
            $existing = DB::table('ticket_statuses')->where('name', $status['name'])->first();
            if ($existing) {
                DB::table('ticket_statuses')
                    ->where('id', $existing->id)
                    ->update([
                        'color' => $status['color'],
                        'is_default' => $status['is_default'],
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('ticket_statuses')->insert([
                    'name' => $status['name'],
                    'color' => $status['color'],
                    'is_default' => $status['is_default'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $targetIds = DB::table('ticket_statuses')
            ->whereIn('name', ['Pending', 'Scheduled', 'Ongoing', 'Completed', 'Cancelled'])
            ->pluck('id', 'name');

        $mapping = [
            'Open' => 'Pending',
            'Accepted' => 'Ongoing',
            'In Progress' => 'Ongoing',
            'Resolved' => 'Completed',
            'Closed' => 'Completed',
            'Rejected' => 'Cancelled',
            'Failed' => 'Cancelled',
        ];

        foreach ($mapping as $from => $to) {
            $fromId = DB::table('ticket_statuses')->where('name', $from)->value('id');
            $toId = $targetIds[$to] ?? null;

            if ($fromId && $toId) {
                DB::table('tickets')
                    ->where('status_id', $fromId)
                    ->update([
                        'status_id' => $toId,
                        'updated_at' => $now,
                    ]);
            }
        }

        DB::table('ticket_statuses')
            ->whereIn('name', array_keys($mapping))
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank to avoid reintroducing deprecated statuses.
    }
};
