<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FirestoreService
{
    private $firestore;
    private $isAvailable = false;

    public function __construct()
    {
        try {
            // Check if Firebase classes are available
            if (!class_exists('Kreait\Firebase\Factory')) {
                Log::warning('Firebase Factory class not found. Firestore sync disabled.');
                return;
            }

            // Use the existing service account file
            $serviceAccountPath = storage_path('app/firebase-credentials.json');
            
            if (!file_exists($serviceAccountPath)) {
                Log::warning('Firebase credentials file not found. Firestore sync disabled.');
                return;
            }

            $factory = (new \Kreait\Firebase\Factory)->withServiceAccount($serviceAccountPath);
            $this->firestore = $factory->createFirestore();
            $this->isAvailable = true;
            
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firestore: ' . $e->getMessage());
            $this->isAvailable = false;
        }
    }

    public function database()
    {
        if (!$this->isAvailable) {
            throw new \Exception('Firestore is not available. Check logs for details.');
        }
        
        return $this->firestore->database();
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }
}
