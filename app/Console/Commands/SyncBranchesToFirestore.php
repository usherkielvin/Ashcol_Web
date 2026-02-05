<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\FirestoreService;
use Illuminate\Console\Command;

class SyncBranchesToFirestore extends Command
{
    protected $signature = 'firestore:sync-branches';
    protected $description = 'Sync all branches to Firestore';

    public function handle()
    {
        $this->info('Starting branch sync to Firestore...');
        
        try {
            $firestoreService = new FirestoreService();
            $db = $firestoreService->database();
            $branches = Branch::active()->get();
            
            $this->info("Found {$branches->count()} active branches to sync");
            
            foreach ($branches as $branch) {
                $db->collection('branches')
                    ->document((string)$branch->id)
                    ->set([
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'location' => $branch->location,
                        'address' => $branch->address,
                        'latitude' => (float)$branch->latitude,
                        'longitude' => (float)$branch->longitude,
                        'isActive' => (bool)$branch->is_active,
                        'updatedAt' => new \DateTime(),
                    ]);
                
                $this->info("Synced: {$branch->name}");
            }
            
            $this->info('Branch sync completed successfully!');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to sync branches: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}
