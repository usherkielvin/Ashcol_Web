<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'address',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    /**
     * Get all tickets for this branch
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get all users assigned to this branch
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch', 'name');
    }

    /**
     * Get active branches only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find the nearest branch to given coordinates
     */
    public static function findNearestBranch($userLatitude, $userLongitude)
    {
        $branches = self::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($branches->isEmpty()) {
            return null;
        }

        $nearestBranch = null;
        $shortestDistance = PHP_FLOAT_MAX;

        foreach ($branches as $branch) {
            $distance = self::calculateDistance(
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

        return $nearestBranch;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private static function calculateDistance($lat1, $lon1, $lat2, $lon2)
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
     * Find branch by location name (fallback method)
     */
    public static function findByLocation($locationName)
    {
        return self::active()
            ->where('location', 'LIKE', '%' . $locationName . '%')
            ->first();
    }

    /**
     * Guess branch from region and city
     * Centralized logic used by AuthController and TicketController
     */
    public static function guessFromRegionCity(?string $region, ?string $city): ?string
    {
        // 1. Try DB lookup by exact location match
        if ($city) {
            $branch = self::findByLocation($city);
            if ($branch) return $branch->name;
        }

        $region = mb_strtolower(trim($region ?? ''), 'UTF-8');
        $city   = mb_strtolower(trim($city ?? ''), 'UTF-8');

        // NCR branches
        if (str_contains($region, 'ncr') || str_contains($region, 'national capital')) {
            if (str_contains($city, 'valenzuela')) {
                return 'ASHCOL Valenzuela';
            }
            if (str_contains($city, 'taguig') || str_contains($city, 'bgc')) {
                return 'ASHCOL TAGUIG';
            }
            if (str_contains($city, 'rodriguez') || str_contains($city, 'montalban')) {
                return 'ASHCOL Rodriguez Rizal';
            }
        }

        // Central Luzon branches (Pampanga, Bulacan, etc.)
        if (str_contains($region, 'central luzon') || str_contains($city, 'pampanga') || str_contains($city, 'bulacan')) {
            if (str_contains($city, 'san fernando') || str_contains($city, 'pampanga')) {
                return 'ASHCOL PAMPANGA';
            }
            if (str_contains($city, 'malolos') || str_contains($city, 'bulacan')) {
                return 'ASHCOL Bulacan';
            }
        }

        // CALABARZON branches (Cavite, Laguna, Batangas, Rizal)
        if (
            str_contains($region, 'calabarzon') ||
            str_contains($city, 'cavite') ||
            str_contains($city, 'laguna') ||
            str_contains($city, 'batangas') ||
            str_contains($city, 'rizal')
        ) {
            if (str_contains($city, 'general trias') || str_contains($city, 'gentri')) {
                return 'ASHCOL GENTRI CAVITE';
            }
            if (str_contains($city, 'dasmariñas') || str_contains($city, 'dasmarinas') || str_contains($city, 'dasma')) {
                return 'ASHCOL DASMARINAS CAVITE';
            }
            if (str_contains($city, 'santa rosa') || str_contains($city, 'sta rosa')) {
                return 'ASHCOL STA ROSA – TAGAYTAY RD';
            }
            if (str_contains($city, 'santa cruz') || str_contains($city, 'sta cruz')) {
                return 'ASHCOL LAGUNA';
            }
            if (str_contains($city, 'batangas')) {
                return 'ASHCOL BATANGAS';
            }
            if (str_contains($city, 'rodriguez') || str_contains($city, 'montalban') || str_contains($city, 'rizal')) {
                return 'ASHCOL Rodriguez Rizal';
            }
        }

        // Fallback: no automatic branch
        return null;
    }

    /**
     * Extract region and city from branch name
     * This is the inverse of guessFromRegionCity()
     */
    public static function extractRegionCityFromBranch(string $branchName): ?array
    {
        $branch = mb_strtolower(trim($branchName), 'UTF-8');

        // NCR branches
        if (str_contains($branch, 'valenzuela')) {
            return ['region' => 'NCR', 'city' => 'Valenzuela'];
        }
        if (str_contains($branch, 'taguig')) {
            return ['region' => 'NCR', 'city' => 'Taguig'];
        }
        if (str_contains($branch, 'rodriguez') || str_contains($branch, 'rizal')) {
            return ['region' => 'NCR', 'city' => 'Rodriguez, Rizal'];
        }

        // Central Luzon branches
        if (str_contains($branch, 'pampanga')) {
            return ['region' => 'Central Luzon', 'city' => 'San Fernando, Pampanga'];
        }
        if (str_contains($branch, 'bulacan')) {
            return ['region' => 'Central Luzon', 'city' => 'Malolos, Bulacan'];
        }

        // CALABARZON branches
        if (str_contains($branch, 'gentri') || str_contains($branch, 'general trias')) {
            return ['region' => 'CALABARZON', 'city' => 'General Trias, Cavite'];
        }
        if (str_contains($branch, 'dasmarinas') || str_contains($branch, 'dasma')) {
            return ['region' => 'CALABARZON', 'city' => 'Dasmariñas, Cavite'];
        }
        if (str_contains($branch, 'sta rosa') || str_contains($branch, 'santa rosa') || str_contains($branch, 'tagaytay')) {
            return ['region' => 'CALABARZON', 'city' => 'Santa Rosa, Laguna'];
        }
        if (str_contains($branch, 'laguna') && !str_contains($branch, 'sta rosa') && !str_contains($branch, 'santa rosa')) {
            return ['region' => 'CALABARZON', 'city' => 'Santa Cruz, Laguna'];
        }
        if (str_contains($branch, 'batangas')) {
            return ['region' => 'CALABARZON', 'city' => 'Batangas City, Batangas'];
        }

        // If no match found, return null
        return null;
    }
}