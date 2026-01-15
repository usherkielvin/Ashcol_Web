<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}

