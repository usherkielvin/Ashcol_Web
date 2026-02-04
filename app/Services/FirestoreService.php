<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;

class FirestoreService
{
    private $firestore;

    public function __construct()
    {
        // Use the existing service account file
        $serviceAccountPath = storage_path('app/firebase-service-account.json');
        
        $factory = (new Factory)->withServiceAccount($serviceAccountPath);
        $this->firestore = $factory->createFirestore();
    }

    public function database()
    {
        return $this->firestore->database();
    }
}
