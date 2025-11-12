<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\EmailVerification;
use App\Mail\VerificationCode;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Create token using Sanctum
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'firstName' => $user->firstName,
                    'lastName' => $user->lastName,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ], [
            'email.unique' => 'Email already used',
            'email.required' => 'Email is required',
            'email.email' => 'Please enter a valid email address',
            'username.unique' => 'Username already taken',
            'username.required' => 'Username is required',
            'firstName.required' => 'First name is required',
            'lastName.required' => 'Last name is required',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Passwords do not match',
            'password_confirmation.required' => 'Password confirmation is required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate verification code
            $verificationCode = EmailVerification::generateCode();
            
            // Store verification code (expires in 10 minutes)
            EmailVerification::updateOrCreate(
                ['email' => $request->email],
                [
                    'code' => $verificationCode,
                    'expires_at' => now()->addMinutes(10),
                    'verified' => false,
                ]
            );

            // Create user
            $user = User::create([
                'username' => $request->username,
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'name' => trim($request->firstName . ' ' . $request->lastName),
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'customer',
            ]);

            // Send verification email
            try {
                $userName = trim($request->firstName . ' ' . $request->lastName);
                Mail::to($request->email)->send(new VerificationCode($verificationCode, $userName));
            } catch (\Exception $e) {
                // Log error but continue (for development, email might not be configured)
                \Log::error('Failed to send verification email: ' . $e->getMessage());
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'firstName' => $user->firstName,
                        'lastName' => $user->lastName,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                    'requires_verification' => true,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send verification code
     */
    public function sendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Generate verification code
        $verificationCode = EmailVerification::generateCode();
        
        // Store verification code (expires in 10 minutes)
        EmailVerification::updateOrCreate(
            ['email' => $request->email],
            [
                'code' => $verificationCode,
                'expires_at' => now()->addMinutes(10),
                'verified' => false,
            ]
        );

        // Send verification email
        try {
            // Use name if available, otherwise use firstName + lastName
            $userName = $user->name ?? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));
            Mail::to($request->email)->send(new VerificationCode($verificationCode, $userName));
            
            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to your email',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify email with code
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string|size:6',
        ]);

        $verification = EmailVerification::where('email', $request->email)
            ->where('code', $request->code)
            ->where('verified', false)
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code',
            ], 400);
        }

        if ($verification->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code has expired',
            ], 400);
        }

        // Mark as verified
        $verification->update(['verified' => true]);

        // Update user email_verified_at
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->update(['email_verified_at' => now()]);
            
            // Create token for the verified user
            $token = $user->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'firstName' => $user->firstName,
                        'lastName' => $user->lastName,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'User not found',
        ], 404);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}

