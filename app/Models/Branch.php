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
}