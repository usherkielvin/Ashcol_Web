<?php

namespace Database\Seeders;

use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

class TicketStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'Pending',
                'color' => '#F59E0B', // Orange
                'is_default' => true,
            ],
            [
                'name' => 'Scheduled',
                'color' => '#6366F1', // Indigo
                'is_default' => false,
            ],
            [
                'name' => 'Ongoing',
                'color' => '#3B82F6', // Blue
                'is_default' => false,
            ],
            [
                'name' => 'Completed',
                'color' => '#10B981', // Green
                'is_default' => false,
            ],
            [
                'name' => 'Cancelled',
                'color' => '#EF4444', // Red
                'is_default' => false,
            ],
        ];

        foreach ($statuses as $status) {
            TicketStatus::updateOrCreate(
                ['name' => $status['name']],
                $status
            );
        }
    }
}

