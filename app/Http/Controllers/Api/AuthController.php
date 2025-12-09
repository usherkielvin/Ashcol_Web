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
use Illuminate\Database\QueryException;

class AuthController extends Controller
{
    /**
     * Handle login request
     * Supports both email and username login
     */
    public function login(Request $request)
    {
        try {
            // Validate input - accept email/username field and password
            $validator = Validator::make($request->all(), [
                'email' => 'nullable|string',
                'username' => 'nullable|string',
                'password' => 'required|string',
            ], [
                'password.required' => 'Password is required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get login identifier (email or username)
            $loginValue = $request->input('username') ?? $request->input('email');
            
            if (empty($loginValue)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email or username is required',
                ], 422);
            }

            // Determine if login value is an email or username
            $isEmail = filter_var($loginValue, FILTER_VALIDATE_EMAIL) !== false;

            // Try to find user by email first if it's an email, otherwise try username
            if ($isEmail) {
                $user = User::where('email', $loginValue)->first();
                // If not found by email, try username (in case user entered email but wants to login with username)
                if (!$user) {
                    $user = User::where('username', $loginValue)->first();
                }
            } else {
                // It's a username, try username first
                $user = User::where('username', $loginValue)->first();
                // If not found by username, try email (in case user entered username but wants to login with email)
                if (!$user) {
                    $user = User::where('email', $loginValue)->first();
                }
            }

            // Verify password
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
                        'name' => $user->name ?? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')),
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        try {
            // Normalize input data to handle both camelCase and snake_case
            $input = $request->all();
            
            // Handle alternative field names
            if (isset($input['first_name']) && !isset($input['firstName'])) {
                $input['firstName'] = $input['first_name'];
            }
            if (isset($input['last_name']) && !isset($input['lastName'])) {
                $input['lastName'] = $input['last_name'];
            }
            if (isset($input['passwordConfirmation']) && !isset($input['password_confirmation'])) {
                $input['password_confirmation'] = $input['passwordConfirmation'];
            }
            
            // Log incoming request data for debugging (excluding passwords)
            \Log::info('Registration request received', [
                'data' => array_intersect_key($input, array_flip(['username', 'firstName', 'lastName', 'email'])),
                'has_password' => isset($input['password']),
                'has_password_confirmation' => isset($input['password_confirmation']),
            ]);

            $validator = Validator::make($input, [
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
                'password_confirmation.min' => 'Password confirmation must be at least 8 characters',
            ]);

            if ($validator->fails()) {
                \Log::warning('Registration validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => array_intersect_key($input, array_flip(['username', 'firstName', 'lastName', 'email'])),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate verification code
            $verificationCode = EmailVerification::generateCode();
            
            // Store verification code (expires in 10 minutes)
            EmailVerification::updateOrCreate(
                ['email' => $input['email']],
                [
                    'code' => $verificationCode,
                    'expires_at' => now()->addMinutes(10),
                    'verified' => false,
                ]
            );

            // Create user
            $user = User::create([
                'username' => $input['username'],
                'firstName' => $input['firstName'],
                'lastName' => $input['lastName'],
                'name' => trim($input['firstName'] . ' ' . $input['lastName']),
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'role' => 'customer',
            ]);

            // Send verification email
            try {
                $userName = trim($input['firstName'] . ' ' . $input['lastName']);
                Mail::to($input['email'])->send(new VerificationCode($verificationCode, $userName));
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Log database errors with full details
            \Log::error('Registration database error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => array_intersect_key($input ?? $request->all(), array_flip(['username', 'firstName', 'lastName', 'email']))
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server error. Please check your Laravel server logs and try again.'
            ], 500);
        } catch (\Exception $e) {
            // Log all other errors with full details
            \Log::error('Registration error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => array_intersect_key($input ?? $request->all(), array_flip(['username', 'firstName', 'lastName', 'email']))
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server error. Please check your Laravel server logs and try again.'
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

