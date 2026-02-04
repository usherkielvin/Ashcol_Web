<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    /**
     * Get all active branches
     */
    public function index(Request $request)
    {
        try {
            $branches = Branch::active()->get();
            
            return response()->json([
                'success' => true,
                'branches' => $branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'location' => $branch->location,
                        'address' => $branch->address,
                        'latitude' => $branch->latitude,
                        'longitude' => $branch->longitude,
                        'isActive' => $branch->is_active,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch branches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
            ], 500);
        }
    }

    /**
     * Sync branches to Firestore (manual trigger)
     * Note: Requires google/cloud-firestore with gRPC extension
     * This is a placeholder for when the extension is available
     */
    public function syncToFirestore(Request $request)
    {
        try {
            $firestoreService = new \App\Services\FirestoreService();
            $db = $firestoreService->database();
            $branches = Branch::active()->get();
            
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
            }
            
            return response()->json([
                'success' => true,
                'message' => "Synced {$branches->count()} branches to Firestore",
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync branches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync branches: ' . $e->getMessage(),
            ], 500);
        }
    }
}
