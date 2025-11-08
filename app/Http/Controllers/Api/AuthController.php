<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
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
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required|string|min:8',
                'role' => 'nullable|string|in:admin,staff,customer',
            ], [
                'email.unique' => 'Email already used',
                'email.required' => 'Email is required',
                'email.email' => 'Please enter a valid email address',
                'name.required' => 'Name is required',
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Passwords do not match',
            ]);

            // Generate verification code
            $verificationCode = EmailVerification::generateCode();
            
            // Store verification code (expires in 10 minutes)
            EmailVerification::updateOrCreate(
                ['email' => $validated['email']],
                [
                    'code' => $verificationCode,
                    'expires_at' => now()->addMinutes(10),
                    'verified' => false,
                ]
            );

            // Send verification email
            try {
                Mail::to($validated['email'])->send(new VerificationCode($verificationCode, $validated['name']));
            } catch (\Exception $e) {
                // Log error but continue (for development, email might not be configured)
                \Log::error('Failed to send verification email: ' . $e->getMessage());
            }

            // Create user but don't log them in yet (wait for verification)
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'] ?? 'customer',
                'email_verified_at' => null, // Not verified yet
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'requires_verification' => true,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMessage = 'Validation failed';
            
            // Create a user-friendly error message
            if (isset($errors['email'])) {
                foreach ($errors['email'] as $emailError) {
                    if (str_contains(strtolower($emailError), 'already') || str_contains(strtolower($emailError), 'taken')) {
                        $errorMessage = 'Email already used';
                        break;
                    }
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'errors' => [
                    'name' => $errors['name'] ?? null,
                    'email' => $errors['email'] ?? null,
                    'password' => $errors['password'] ?? null,
                ],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
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
            Mail::to($request->email)->send(new VerificationCode($verificationCode, $user->name));
            
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

