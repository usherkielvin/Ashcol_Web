<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Build name from firstName and lastName if name field is empty
        $firstName = $user->firstName ?? null;
        $lastName = $user->lastName ?? null;
        $name = $user->name ?? null;
        
        // If name is empty, build it from firstName and lastName
        if (empty($name) && (!empty($firstName) || !empty($lastName))) {
            $nameParts = array_filter([$firstName, $lastName]); // Remove null/empty values
            $name = trim(implode(' ', $nameParts));
        }
        
        // Ensure we don't return "null" as a string
        if ($name === 'null' || $name === null || $name === '') {
            $name = null;
        }
        if ($firstName === 'null' || $firstName === '') {
            $firstName = null;
        }
        if ($lastName === 'null' || $lastName === '') {
            $lastName = null;
        }

        // Get email directly from database email column - ensure we're reading the correct field
        // Use DB query to explicitly get email column to avoid any model attribute issues
        $email = DB::table('users')->where('id', $user->id)->value('email');
        $username = $user->username ?? null;
        
        // CRITICAL: Validate email is actually an email and not username
        // Email MUST contain @ symbol and MUST NOT equal username
        if ($email) {
            $email = trim($email);
            $hasAtSymbol = strpos($email, '@') !== false;
            
            // Reject if:
            // 1. No @ symbol (not a valid email)
            // 2. Same as username (data corruption - email column contains username)
            // 3. Same as firstName (data corruption)
            // 4. Empty or "null" string
            if (!$hasAtSymbol || 
                $email === $username || 
                $email === $user->firstName || 
                $email === '' || 
                $email === 'null') {
                // Return null instead of invalid data - prevents username from being returned as email
                $email = null;
            }
        } else {
            $email = null;
        }

        // Get profile photo URL
        $profilePhotoUrl = null;
        if ($user->profile_photo) {
            // If it's a full URL (starts with http), use as-is
            if (strpos($user->profile_photo, 'http') === 0) {
                $profilePhotoUrl = $user->profile_photo;
            } else {
                // Otherwise, construct the full URL
                $profilePhotoUrl = asset('storage/' . $user->profile_photo);
            }
        }

        // Check if user has Facebook account linked
        $hasFacebookAccount = $user->facebookAccount !== null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $username,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'name' => $name,
                'email' => $email,
                'role' => $user->role ?? 'customer',
                'location' => $user->location ?? null,
                'branch' => $user->branch ?? null,
                'profile_photo' => $profilePhotoUrl,
                'has_facebook_account' => $hasFacebookAccount,
            ],
        ]);
    }

    /**
     * Get user profile
     */
    public function show(Request $request)
    {
        return $this->user($request);
    }

    /**
     * Upload profile photo
     */
    public function uploadPhoto(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
            ], [
                'photo.required' => 'Please select a photo to upload',
                'photo.image' => 'File must be an image',
                'photo.mimes' => 'Photo must be a JPEG, PNG, JPG, or GIF',
                'photo.max' => 'Photo size must not exceed 5MB',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Delete old photo if exists
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Store new photo
            $photo = $request->file('photo');
            $filename = 'profile_' . $user->id . '_' . time() . '.' . $photo->getClientOriginalExtension();
            $path = $photo->storeAs('profile_photos', $filename, 'public');

            // Update user profile_photo field
            $user->profile_photo = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile photo uploaded successfully',
                'data' => [
                    'profile_photo' => asset('storage/' . $path),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Profile photo upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update profile photo (alias for uploadPhoto)
     */
    public function updatePhoto(Request $request)
    {
        return $this->uploadPhoto($request);
    }

    /**
     * Get employees for the current manager's branch
     */
    public function getEmployees(Request $request)
    {
        $user = $request->user();

        \Log::info('getEmployees called', ['user_id' => $user->id ?? 'null', 'user_role' => $user->role ?? 'null', 'user_branch' => $user->branch ?? 'null']);

        if (!$user) {
            \Log::error('getEmployees: User not authenticated');
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Check if user is a manager
        if ($user->role !== 'manager') {
            \Log::error('getEmployees: Access denied', ['user_role' => $user->role]);
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only managers can view employees.',
            ], 403);
        }

        // Get manager's branch
        $managerBranch = $user->branch;
        
        if (!$managerBranch) {
            \Log::error('getEmployees: Manager has no branch assigned');
            return response()->json([
                'success' => false,
                'message' => 'Manager has no branch assigned.',
            ], 400);
        }

        \Log::info('getEmployees: Manager branch found', ['branch' => $managerBranch]);

        // Cache key for this manager's employee data
        $cacheKey = "manager_employees_{$user->id}_{$managerBranch}";
        
        // Try to get from cache first (cache for 5 minutes)
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            \Log::info('getEmployees: Returning cached data', ['employee_count' => count($cachedData['employees'])]);
            return response()->json([
                'success' => true,
                'employees' => $cachedData['employees'],
                'branch' => $cachedData['branch'],
                'employee_count' => $cachedData['employee_count'],
                'cached' => true,
            ]);
        }

        \Log::info('getEmployees: No cache, querying database');

        // Simplified and optimized query - only get essential data
        $employees = DB::table('users')
            ->whereIn('role', ['employee', 'staff']) // Check both possible role names
            ->where('branch', $managerBranch)
            ->select('id', 'username', 'firstName', 'lastName', 'email', 'role', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        \Log::info('getEmployees: Database query completed', ['found_employees' => $employees->count(), 'manager_branch' => $managerBranch]);

        // Log each employee found
        foreach ($employees as $emp) {
            \Log::info('Employee found', ['id' => $emp->id, 'username' => $emp->username, 'role' => $emp->role, 'branch' => $emp->branch ?? 'NULL']);
        }

        // Get employee count efficiently
        $employeeCount = $employees->count();

        // Simplified employee data formatting (remove expensive operations)
        $formattedEmployees = $employees->map(function ($employee) {
            $firstName = $employee->firstName ?? '';
            $lastName = $employee->lastName ?? '';
            $name = trim($firstName . ' ' . $lastName);

            return [
                'id' => $employee->id,
                'username' => $employee->username,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'name' => $name,
                'email' => $employee->email,
                'role' => $employee->role, // Use actual role from database
                'branch' => $managerBranch, // All employees in this response are from manager's branch
                'created_at' => $employee->created_at,
            ];
        });

        $responseData = [
            'branch' => $managerBranch,
            'employee_count' => $employeeCount,
            'employees' => $formattedEmployees,
        ];

        // Cache the result for 5 minutes
        Cache::put($cacheKey, $responseData, 300);

        \Log::info('getEmployees: Returning response', ['employee_count' => $employeeCount]);

        return response()->json([
            'success' => true,
            'employees' => $formattedEmployees,
            'branch' => $managerBranch,
            'employee_count' => $employeeCount,
        ]);
    }

    /**
     * Clear employee cache for a specific branch (called when employees are added/removed)
     */
    public static function clearEmployeeCache($branchName)
    {
        // Clear cache for all managers in this branch
        $managers = DB::table('users')
            ->where('role', 'manager')
            ->where('branch', $branchName)
            ->select('id')
            ->get();

        foreach ($managers as $manager) {
            $cacheKey = "manager_employees_{$manager->id}_{$branchName}";
            Cache::forget($cacheKey);
        }
    }

    /**
     * Update user location
     */
    public function updateLocation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'location' => 'required|string|max:255',
            ], [
                'location.required' => 'Location is required',
                'location.max' => 'Location must not exceed 255 characters',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $user->location = $request->location;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => [
                    'location' => $user->location,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Update location error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete profile photo
     */
    public function deletePhoto(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->profile_photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No profile photo to delete',
                ], 404);
            }

            // Delete photo file if exists
            if (Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Update user profile_photo field
            $user->profile_photo = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile photo deleted successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Profile photo delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile photo: ' . $e->getMessage(),
            ], 500);
        }
    }
}

