<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\FirestoreService;

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

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $username,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'role' => $user->role ?? 'customer',
                'phone' => $user->phone ?? null,
                'gender' => $user->gender ?? null,
                'birthdate' => $user->birthdate ? $user->birthdate->format('Y-m-d') : null,
                'region' => $user->region ?? null,
                'city' => $user->city ?? null,
                'branch' => $user->branch ?? null,
                'profile_photo' => $profilePhotoUrl,
            ],
        ]);
    }

    /**
     * Update authenticated user profile
     */
    public function updateUser(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|max:50|unique:users,username,' . $user->id,
            'firstName' => 'nullable|string|max:50',
            'lastName' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|string|max:20',
            'birthdate' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->filled('username')) {
            $user->username = $request->input('username');
        }
        if ($request->filled('firstName')) {
            $user->firstName = $request->input('firstName');
        }
        if ($request->filled('lastName')) {
            $user->lastName = $request->input('lastName');
        }
        if ($request->filled('phone')) {
            $user->phone = $request->input('phone');
        }
        if ($request->filled('gender')) {
            $user->gender = $request->input('gender');
        }
        if ($request->filled('birthdate')) {
            $user->birthdate = $request->input('birthdate');
        }

        $user->save();

        return $this->user($request);
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
            
            // clear cache
            Cache::forget('user_' . $user->id);

            // Sync to Firestore
            try {
                $firestoreService = new FirestoreService();
                $firestoreService->database()
                    ->collection('users')
                    ->document((string)$user->id)
                    ->set([
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'branch' => $user->branch,
                        'role' => $user->role,
                        'profilePhoto' => asset('storage/' . $path),
                        'updatedAt' => new \DateTime(),
                    ], ['merge' => true]);
            } catch (\Exception $e) {
                // Log error but continue
                \Illuminate\Support\Facades\Log::error('Firestore sync failed: ' . $e->getMessage());
            }

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
     * Delete authenticated user account
     */
    public function deleteUser(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$user->password || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 403);
        }

        // Remove stored profile photo if present
        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        // Revoke tokens before deleting account
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }

    /**
     * Get employees for the current manager's branch
     */
    public function getEmployees(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is a manager or admin
            if (!in_array($user->role, ['manager', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only managers and admins can view employees.',
                ], 403);
            }

            // For admins, get all employees; for managers, get only their branch employees
            if ($user->role === 'admin') {
                // Admin can see all employees
                $employees = DB::table('users')
                    ->whereIn('role', ['technician', 'manager'])
                    ->select('id', 'username', 'firstName', 'lastName', 'email', 'role', 'branch', 'profile_photo')
                    ->orderBy('branch')
                    ->orderBy('firstName')
                    ->get();

                // Get ticket counts for these employees (excluding completed/paid/cancelled)
                $ticketCounts = DB::table('tickets')
                    ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
                    ->select('assigned_staff_id', DB::raw('count(*) as total'))
                    ->whereIn('assigned_staff_id', $employees->pluck('id'))
                    ->whereNotIn('ticket_statuses.name', ['Completed', 'Cancelled'])
                    ->groupBy('assigned_staff_id')
                    ->pluck('total', 'assigned_staff_id');

                // Format employee data
                $formattedEmployees = [];
                foreach ($employees as $employee) {
                    // Build profile photo URL
                    $profilePhotoUrl = null;
                    if ($employee->profile_photo) {
                        if (strpos($employee->profile_photo, 'http') === 0) {
                            $profilePhotoUrl = $employee->profile_photo;
                        } else {
                            $profilePhotoUrl = asset('storage/' . $employee->profile_photo);
                        }
                    }
                    
                    $formattedEmployees[] = [
                        'id' => $employee->id,
                        'username' => $employee->username,
                        'firstName' => $employee->firstName,
                        'lastName' => $employee->lastName,
                        'email' => $employee->email,
                        'role' => $employee->role,
                        'branch' => $employee->branch,
                        'ticket_count' => $ticketCounts[$employee->id] ?? 0,
                        'profile_photo' => $profilePhotoUrl,
                    ];
                }

                return response()->json([
                    'success' => true,
                    'employees' => $formattedEmployees,
                    'branch' => 'All Branches',
                    'employee_count' => count($formattedEmployees),
                ]);
            } else {
                // Manager - get only their branch employees
                // Get manager's branch directly from database
                $managerBranch = DB::table('users')->where('id', $user->id)->value('branch');
                
                if (!$managerBranch) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Manager has no branch assigned.',
                    ], 400);
                }

                // Get employees with the same branch name
                $employees = DB::table('users')
                    ->whereIn('role', ['technician'])
                    ->where('branch', $managerBranch)
                    ->select('id', 'username', 'firstName', 'lastName', 'email', 'role', 'branch', 'profile_photo')
                    ->get();

                // Get ticket counts for these employees (excluding completed/paid/cancelled)
                $ticketCounts = DB::table('tickets')
                    ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
                    ->select('assigned_staff_id', DB::raw('count(*) as total'))
                    ->whereIn('assigned_staff_id', $employees->pluck('id'))
                    ->whereNotIn('ticket_statuses.name', ['Completed', 'Cancelled'])
                    ->groupBy('assigned_staff_id')
                    ->pluck('total', 'assigned_staff_id');

                // Format employee data
                $formattedEmployees = [];
                foreach ($employees as $employee) {
                    // Build profile photo URL
                    $profilePhotoUrl = null;
                    if ($employee->profile_photo) {
                        if (strpos($employee->profile_photo, 'http') === 0) {
                            $profilePhotoUrl = $employee->profile_photo;
                        } else {
                            $profilePhotoUrl = asset('storage/' . $employee->profile_photo);
                        }
                    }
                    
                    $formattedEmployees[] = [
                        'id' => $employee->id,
                        'username' => $employee->username,
                        'firstName' => $employee->firstName,
                        'lastName' => $employee->lastName,
                        'email' => $employee->email,
                        'role' => $employee->role,
                        'branch' => $employee->branch,
                        'ticket_count' => $ticketCounts[$employee->id] ?? 0,
                        'profile_photo' => $profilePhotoUrl,
                    ];
                }

                return response()->json([
                    'success' => true,
                    'employees' => $formattedEmployees,
                    'branch' => $managerBranch,
                    'employee_count' => count($formattedEmployees),
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading employees: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register FCM token for push notifications
     */
    public function registerFCMToken(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->fcm_token = $request->input('fcm_token');
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'FCM token saved',
        ]);
    }

    /**
     * Get employees for a specific branch (admin only)
     */
    public function getEmployeesByBranch(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only admins can view employees by branch.',
                ], 403);
            }

            $branchName = $request->query('branch');
            if (!$branchName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch name is required.',
                ], 400);
            }

            // Get employees for the specified branch
            $employees = DB::table('users')
                ->whereIn('role', ['technician', 'manager'])
                ->where('branch', $branchName)
                ->select('id', 'username', 'firstName', 'lastName', 'email', 'role', 'branch', 'profile_photo')
                ->orderBy('firstName')
                ->get();

            // Get ticket counts for these employees (excluding completed/paid/cancelled)
            $ticketCounts = DB::table('tickets')
                ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
                ->select('assigned_staff_id', DB::raw('count(*) as total'))
                ->whereIn('assigned_staff_id', $employees->pluck('id'))
                ->whereNotIn('ticket_statuses.name', ['Completed', 'Cancelled'])
                ->groupBy('assigned_staff_id')
                ->pluck('total', 'assigned_staff_id');

            // Format employee data
            $formattedEmployees = [];
            foreach ($employees as $employee) {
                // Build profile photo URL
                $profilePhotoUrl = null;
                if ($employee->profile_photo) {
                    if (strpos($employee->profile_photo, 'http') === 0) {
                        $profilePhotoUrl = $employee->profile_photo;
                    } else {
                        $profilePhotoUrl = asset('storage/' . $employee->profile_photo);
                    }
                }
                
                $formattedEmployees[] = [
                    'id' => $employee->id,
                    'username' => $employee->username,
                    'firstName' => $employee->firstName,
                    'lastName' => $employee->lastName,
                    'email' => $employee->email,
                    'role' => $employee->role,
                    'branch' => $employee->branch,
                    'ticket_count' => $ticketCounts[$employee->id] ?? 0,
                    'profile_photo' => $profilePhotoUrl,
                ];
            }

            return response()->json([
                'success' => true,
                'employees' => $formattedEmployees,
                'branch' => $branchName,
                'employee_count' => count($formattedEmployees),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading employees: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear employee cache for a specific branch (called when employees are added/removed)
     */
    public static function clearEmployeeCache($branchName)
    {
        // Simple implementation - no complex caching
        return true;
    }


    /**
     * Get all branches
     */
    public function getBranches(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is admin or manager (both can view branches)
            if (!in_array($user->role, ['admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only admins and managers can view branches.',
                ], 403);
            }

            // Get all branches from database
            $branches = DB::table('branches')
                ->where('is_active', true)
                ->select('id', 'name', 'location', 'address', 'latitude', 'longitude')
                ->orderBy('name')
                ->get();

            // Get employee counts and managers for each branch
            $branchData = [];
            foreach ($branches as $branch) {
                // Count employees for this branch
                $employeeCount = DB::table('users')
                    ->whereIn('role', ['technician'])
                    ->where('branch', $branch->name)
                    ->count();

                // Find manager for this branch
                $manager = DB::table('users')
                    ->where('role', 'manager')
                    ->where('branch', $branch->name)
                    ->select('firstName', 'lastName')
                    ->first();

                $managerName = 'No manager assigned';
                if ($manager) {
                    $managerName = trim(($manager->firstName ?? '') . ' ' . ($manager->lastName ?? ''));
                    if (empty($managerName)) {
                        $managerName = 'No manager assigned';
                    }
                }

                $branchData[] = [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'location' => $branch->location,
                    'address' => $branch->address,
                    'latitude' => $branch->latitude,
                    'longitude' => $branch->longitude,
                    'manager' => $managerName,
                    'employee_count' => $employeeCount,
                    'description' => 'Branch serving ' . strtolower(str_replace('ASHCOL ', '', $branch->name)) . ' area.',
                ];
            }

            return response()->json([
                'success' => true,
                'branches' => $branchData,
                'total_branches' => count($branchData),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading branches: ' . $e->getMessage(),
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
            
            // Clear cache
            Cache::forget('user_' . $user->id);

            // Sync to Firestore
            try {
                $firestoreService = new FirestoreService();
                $firestoreService->database()
                    ->collection('users')
                    ->document((string)$user->id)
                    ->set([
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'branch' => $user->branch,
                        'role' => $user->role,
                        'profilePhoto' => null,
                        'updatedAt' => new \DateTime(),
                    ], ['merge' => true]);
            } catch (\Exception $e) {
                // Log error but continue
                \Illuminate\Support\Facades\Log::error('Firestore sync failed: ' . $e->getMessage());
            }

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

    /**
     * Register FCM token for push notifications
     */

    /**
     * Admin delete user by ID (no password required)
     */
    public function adminDeleteUser(Request $request, $userId)
    {
        $admin = $request->user();

        // Check if the authenticated user is an admin
        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        // Find the user to delete
        $userToDelete = \App\Models\User::find($userId);

        if (!$userToDelete) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Prevent admin from deleting themselves
        if ($userToDelete->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account',
            ], 400);
        }

        try {
            // Remove stored profile photo if present
            if ($userToDelete->profile_photo && Storage::disk('public')->exists($userToDelete->profile_photo)) {
                Storage::disk('public')->delete($userToDelete->profile_photo);
            }

            // Revoke tokens before deleting account
            if (method_exists($userToDelete, 'tokens')) {
                $userToDelete->tokens()->delete();
            }

            $userName = $userToDelete->name ?? $userToDelete->email;
            $userToDelete->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
                'deleted_user' => $userName,
            ]);
        } catch (\Exception $e) {
            \Log::error('Admin delete user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage(),
            ], 500);
        }
    }
}


