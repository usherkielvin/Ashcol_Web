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
                'name' => 'Open',
                'color' => '#10B981', // Green
                'is_default' => true,
            ],
            [
                'name' => 'Pending',
                'color' => '#F59E0B', // Orange
                'is_default' => false,
            ],
            [
                'name' => 'Accepted',
                'color' => '#3B82F6', // Blue
                'is_default' => false,
            ],
            [
                'name' => 'In Progress',
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
            [
                'name' => 'Resolved',
                'color' => '#6B7280', // Gray
                'is_default' => false,
            ],
            [
                'name' => 'Closed',
                'color' => '#1F2937', // Dark Gray
                'is_default' => false,
            ],
        ];

        foreach ($statuses as $status) {
            TicketStatus::create($status);
        }
    }
}

