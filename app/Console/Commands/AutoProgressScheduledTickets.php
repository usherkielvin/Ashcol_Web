<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\FirestoreService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoProgressScheduledTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:auto-progress
                            {--date= : Override today date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move Scheduled tickets to In Progress when the scheduled date is today or earlier';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateOverride = $this->option('date');
        $today = $dateOverride ? Carbon::parse($dateOverride)->toDateString() : Carbon::today()->toDateString();

        $scheduledStatus = TicketStatus::where('name', 'Scheduled')->first();
        $inProgressStatus = TicketStatus::where('name', 'In Progress')->first();

        if (! $scheduledStatus || ! $inProgressStatus) {
            $this->error('Missing TicketStatus records for Scheduled or In Progress.');
            return Command::FAILURE;
        }

        $query = Ticket::with(['customer', 'assignedStaff', 'status', 'branch'])
            ->where('status_id', $scheduledStatus->id)
            ->whereDate('scheduled_date', '<=', $today);

        $total = $query->count();
        if ($total === 0) {
            $this->info('No scheduled tickets to auto-progress.');
            return Command::SUCCESS;
        }

        $firestoreService = new FirestoreService();
        $updated = 0;

        $query->chunkById(100, function ($tickets) use (&$updated, $inProgressStatus, $firestoreService) {
            foreach ($tickets as $ticket) {
                $ticket->status_id = $inProgressStatus->id;
                $ticket->save();
                $ticket->setRelation('status', $inProgressStatus);

                $updated++;

                try {
                    if ($firestoreService->isAvailable()) {
                        $firestoreService->database()
                            ->collection('tickets')
                            ->document($ticket->ticket_id)
                            ->set([
                                'status' => $inProgressStatus->name,
                                'statusColor' => $inProgressStatus->color ?? '#gray',
                                'updatedAt' => new \DateTime(),
                            ], ['merge' => true]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Firestore sync failed in AutoProgressScheduledTickets: ' . $e->getMessage());
                }
            }
        });

        $this->info("Auto-progressed {$updated} ticket(s) to In Progress.");
        return Command::SUCCESS;
    }
}
