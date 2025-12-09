<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'firstName' => $user->firstName,
                    'lastName' => $user->lastName,
                    'name' => $user->name ?? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')),
                    'email' => $user->email,
                    'role' => $user->role,
                ],
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

