<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class BranchAssignmentService
{
    private $googleMapsApiKey;

    public function __construct()
    {
        $this->googleMapsApiKey = config('services.google.maps_api_key', env('GOOGLE_MAPS_API_KEY'));
    }

    /**
     * Assign branch to user based on their location using Google Maps API
     */
    public function assignBranchToUser(User $user)
    {
        if (!$user->location) {
            Log::warning("User {$user->id} has no location set, assigning default branch");
            return $this->assignDefaultBranch($user);
        }

        // Get coordinates from user's location using Google Maps API
        $coordinates = $this->getCoordinatesFromAddress($user->location);
        
        if ($coordinates) {
            // Update user with coordinates for future use
            $user->update([
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng']
            ]);

            // Find nearest branch using coordinates
            $branch = $this->findNearestBranchWithDistance($coordinates['lat'], $coordinates['lng']);
            
            if ($branch) {
                $user->update(['branch' => $branch['branch']->name]);
                Log::info("Assigned branch {$branch['branch']->name} to user {$user->id} (distance: {$branch['distance']}km)");
                return $branch['branch'];
            }
        }

        // Fallback: Try to find branch by location name similarity
        $branch = $this->findBranchByLocationSimilarity($user->location);
        if ($branch) {
            $user->update(['branch' => $branch->name]);
            Log::info("Assigned branch {$branch->name} to user {$user->id} based on location similarity");
            return $branch;
        }

        // If no specific branch found, assign default branch
        return $this->assignDefaultBranch($user);
    }

    /**
     * Assign default branch to user
     */
    private function assignDefaultBranch(User $user)
    {
        $defaultBranch = Branch::active()->first();
        if ($defaultBranch) {
            $user->update(['branch' => $defaultBranch->name]);
            Log::info("Assigned default branch {$defaultBranch->name} to user {$user->id}");
            return $defaultBranch;
        }

        Log::warning("No branches available to assign to user {$user->id}");
        return null;
    }

    /**
     * Get branch for ticket creation based on user location
     */
    public function getBranchForTicket(User $user)
    {
        // First check if user already has a branch assigned
        if ($user->branch) {
            $branch = Branch::where('name', $user->branch)->active()->first();
            if ($branch) {
                return $branch;
            }
        }

        // If no branch assigned or branch not found, assign one
        return $this->assignBranchToUser($user);
    }

    /**
     * Get coordinates from address using Google Maps Geocoding API
     */
    private function getCoordinatesFromAddress($address)
    {
        if (!$this->googleMapsApiKey) {
            Log::warning("Google Maps API key not configured, falling back to manual assignment");
            return null;
        }

        // Cache the result for 24 hours to avoid repeated API calls
        $cacheKey = 'geocode_' . md5($address);
        
        return Cache::remember($cacheKey, 86400, function () use ($address) {
            try {
                $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $address . ', Philippines', // Ensure we're searching within Philippines
                    'key' => $this->googleMapsApiKey,
                    'region' => 'ph', // Bias results to Philippines
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if ($data['status'] === 'OK' && !empty($data['results'])) {
                        $location = $data['results'][0]['geometry']['location'];
                        
                        // Verify the result is within Philippines bounds
                        if ($this->isWithinPhilippines($location['lat'], $location['lng'])) {
                            Log::info("Geocoded address '{$address}' to coordinates: {$location['lat']}, {$location['lng']}");
                            return $location;
                        } else {
                            Log::warning("Geocoded address '{$address}' is outside Philippines bounds");
                        }
                    } else {
                        Log::warning("Google Maps API could not geocode address: {$address}. Status: {$data['status']}");
                    }
                } else {
                    Log::error("Google Maps API request failed for address: {$address}");
                }
            } catch (\Exception $e) {
                Log::error("Error geocoding address '{$address}': " . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Find nearest branch with distance calculation
     */
    private function findNearestBranchWithDistance($userLatitude, $userLongitude)
    {
        $branches = Branch::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($branches->isEmpty()) {
            return null;
        }

        $nearestBranch = null;
        $shortestDistance = PHP_FLOAT_MAX;

        foreach ($branches as $branch) {
            $distance = $this->calculateDistance(
                $userLatitude,
                $userLongitude,
                $branch->latitude,
                $branch->longitude
            );

            if ($distance < $shortestDistance) {
                $shortestDistance = $distance;
                $nearestBranch = $branch;
            }
        }

        return $nearestBranch ? [
            'branch' => $nearestBranch,
            'distance' => round($shortestDistance, 2)
        ] : null;
    }

    /**
     * Find branch by location name similarity using fuzzy matching
     */
    private function findBranchByLocationSimilarity($userLocation)
    {
        $branches = Branch::active()->get();
        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($branches as $branch) {
            // Check similarity with branch location
            $similarity = $this->calculateStringSimilarity($userLocation, $branch->location);
            
            // Also check similarity with branch name (in case location contains branch area)
            $nameSimilarity = $this->calculateStringSimilarity($userLocation, $branch->name);
            
            $maxSimilarity = max($similarity, $nameSimilarity);
            
            if ($maxSimilarity > $highestSimilarity && $maxSimilarity > 0.6) { // 60% similarity threshold
                $highestSimilarity = $maxSimilarity;
                $bestMatch = $branch;
            }
        }

        if ($bestMatch) {
            Log::info("Found branch '{$bestMatch->name}' with {$highestSimilarity}% similarity to location '{$userLocation}'");
        }

        return $bestMatch;
    }

    /**
     * Calculate string similarity percentage
     */
    private function calculateStringSimilarity($str1, $str2)
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        // Use Levenshtein distance for similarity
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen == 0) return 1.0;
        
        $distance = levenshtein($str1, $str2);
        return (1 - $distance / $maxLen);
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if coordinates are within Philippines bounds
     */
    private function isWithinPhilippines($latitude, $longitude)
    {
        // Philippines approximate bounds
        $minLat = 4.5;   // Southernmost point
        $maxLat = 21.0;  // Northernmost point
        $minLng = 116.0; // Westernmost point
        $maxLng = 127.0; // Easternmost point

        return $latitude >= $minLat && $latitude <= $maxLat && 
               $longitude >= $minLng && $longitude <= $maxLng;
    }

    /**
     * Get distance between user and branch using Google Maps Distance Matrix API
     * (Optional: for more accurate travel distance vs straight-line distance)
     */
    public function getTravelDistance($userLat, $userLng, $branchLat, $branchLng)
    {
        if (!$this->googleMapsApiKey) {
            return null;
        }

        $cacheKey = 'distance_' . md5("{$userLat},{$userLng}_{$branchLat},{$branchLng}");
        
        return Cache::remember($cacheKey, 3600, function () use ($userLat, $userLng, $branchLat, $branchLng) {
            try {
                $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                    'origins' => "{$userLat},{$userLng}",
                    'destinations' => "{$branchLat},{$branchLng}",
                    'key' => $this->googleMapsApiKey,
                    'units' => 'metric',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if ($data['status'] === 'OK' && 
                        !empty($data['rows'][0]['elements'][0]) && 
                        $data['rows'][0]['elements'][0]['status'] === 'OK') {
                        
                        $element = $data['rows'][0]['elements'][0];
                        return [
                            'distance' => $element['distance']['value'] / 1000, // Convert to km
                            'duration' => $element['duration']['text'],
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error getting travel distance: " . $e->getMessage());
            }

            return null;
        });
    }
}