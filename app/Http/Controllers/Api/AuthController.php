<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\EmailVerification;
use App\Models\FacebookAccount;
use App\Mail\VerificationCode;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class AuthController extends Controller
{
    /**
     * Format user data for API responses
     */
    private function formatUserData($user)
    {
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

        return [
            'id' => $user->id,
            'username' => $user->username ?? null,
            'firstName' => $user->firstName ?? null,
            'lastName' => $user->lastName ?? null,
            'name' => $user->name ?? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')),
            'email' => $user->email ?? null,
            'role' => $user->role ?? 'customer',
            'location' => $user->location ?? null,
            'profile_photo' => $profilePhotoUrl,
            'has_facebook_account' => $hasFacebookAccount,
        ];
    }

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
                    'token' => $token,
                    'user' => $this->formatUserData($user),
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
     * Handle Google Sign-In
     * Verifies Google ID token and creates/logs in user
     */
    public function googleSignIn(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_token' => 'nullable|string', // Optional - may be null if not configured
                'email' => 'required|string|email',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify Google ID token (simplified - in production, verify with Google's API)
            // For now, we'll trust the token from the app and verify email matches
            $email = $request->email;
            $firstName = $request->input('first_name', '');
            $lastName = $request->input('last_name', '');
            $phone = $request->input('phone', '');

            // Check if user exists
            $user = User::where('email', $email)->first();

            if ($user) {
                // User exists - log them in
                $token = $user->createToken('mobile-app')->plainTextToken;
                
                // Update user info if provided
                if ($firstName) $user->firstName = $firstName;
                if ($lastName) $user->lastName = $lastName;
                if ($phone) $user->phone = $phone;
                if ($firstName || $lastName) {
                    $user->name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));
                }
                $user->email_verified_at = now(); // Google emails are verified
                $user->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'token' => $token,
                        'user' => $this->formatUserData($user),
                    ],
                ]);
            } else {
                // New user - create account
                // Generate username from email
                $username = explode('@', $email)[0];
                $baseUsername = $username;
                $counter = 1;
                
                // Ensure username is unique
                while (User::where('username', $username)->exists()) {
                    $username = $baseUsername . $counter;
                    $counter++;
                }

                // Create user
                $user = User::create([
                    'username' => $username,
                    'firstName' => $firstName ?: 'User',
                    'lastName' => $lastName ?: '',
                    'name' => trim(($firstName ?: 'User') . ' ' . ($lastName ?: '')),
                    'email' => $email,
                    'password' => Hash::make(uniqid('google_', true)), // Random password - will be updated when user sets password
                    'role' => 'customer', // Default role
                    'phone' => $phone,
                    'email_verified_at' => now(), // Google emails are verified
                ]);

                $token = $user->createToken('mobile-app')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Account created successfully',
                    'data' => [
                        'token' => $token,
                        'user' => $this->formatUserData($user),
                    ],
                ], 201);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Google Sign-In error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Google Sign-In failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Facebook Sign-In
     * Verifies Facebook access token and creates/logs in user
     */
    public function facebookSignIn(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'access_token' => 'nullable|string', // Optional - may be null if not configured
                'facebook_id' => 'required|string', // Facebook ID is required for pure FB authentication
                'email' => 'nullable|string|email', // Email is now optional
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify Facebook access token (simplified - in production, verify with Facebook's API)
            // For now, we'll trust the token from the app
            $facebookId = $request->facebook_id;
            $email = $request->input('email');
            // Convert empty string to null for email (to allow multiple NULL emails with unique constraint)
            if ($email === '' || $email === null) {
                $email = null;
            }
            $firstName = $request->input('first_name', '');
            $lastName = $request->input('last_name', '');
            $phone = $request->input('phone', '');

            // Check if user exists by Facebook ID (in facebook_accounts table) first, then by email if provided
            $user = null;
            $facebookAccount = null;
            
            if ($facebookId) {
                $facebookAccount = FacebookAccount::where('facebook_id', $facebookId)->first();
                if ($facebookAccount) {
                    $user = $facebookAccount->user;
                }
            }
            
            // If not found by Facebook ID and email is provided, check by email
            if (!$user && $email) {
                $user = User::where('email', $email)->first();
                // If user exists but no Facebook account, create the link
                if ($user && $facebookId) {
                    $facebookAccount = FacebookAccount::create([
                        'user_id' => $user->id,
                        'facebook_id' => $facebookId,
                        'access_token' => $request->input('access_token'),
                        'linked_at' => now(),
                    ]);
                }
            }

            if ($user) {
                // User exists - log them in
                $token = $user->createToken('mobile-app')->plainTextToken;
                
                // Update or create Facebook account link if not exists
                if ($facebookId && !$facebookAccount) {
                    $facebookAccount = FacebookAccount::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'facebook_id' => $facebookId,
                            'access_token' => $request->input('access_token'),
                            'linked_at' => now(),
                        ]
                    );
                } elseif ($facebookAccount && $request->input('access_token')) {
                    // Update access token if provided
                    $facebookAccount->access_token = $request->input('access_token');
                    $facebookAccount->save();
                }
                
                // Update user info if provided
                if ($firstName) $user->firstName = $firstName;
                if ($lastName) $user->lastName = $lastName;
                if ($phone) $user->phone = $phone;
                if ($email && !$user->email) {
                    $user->email = $email; // Update email if not set
                }
                if ($firstName || $lastName) {
                    $user->name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));
                }
                if ($email) {
                    $user->email_verified_at = now(); // Facebook emails are verified
                }
                $user->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'token' => $token,
                        'user' => $this->formatUserData($user),
                    ],
                ]);
            } else {
                // New user - create account
                // Generate username from email if available, otherwise from Facebook ID
                if ($email) {
                    $username = explode('@', $email)[0];
                } else {
                    $username = 'fb_' . $facebookId;
                }
                $baseUsername = $username;
                $counter = 1;
                
                // Ensure username is unique
                while (User::where('username', $username)->exists()) {
                    $username = $baseUsername . $counter;
                    $counter++;
                }

                // Use database transaction to ensure both user and FacebookAccount are created together
                DB::beginTransaction();
                try {
                    // Create user (without facebook_id - stored in separate table)
                    $user = User::create([
                        'username' => $username,
                        'firstName' => $firstName ?: 'User',
                        'lastName' => $lastName ?: '',
                        'name' => trim(($firstName ?: 'User') . ' ' . ($lastName ?: '')),
                        'email' => $email, // Can be null for pure FB users
                        'password' => Hash::make(uniqid('facebook_', true)), // Random password
                        'role' => 'customer', // Default role
                        'phone' => $phone,
                        'email_verified_at' => $email ? now() : null, // Only verify if email exists
                    ]);

                    // Create Facebook account link
                    FacebookAccount::create([
                        'user_id' => $user->id,
                        'facebook_id' => $facebookId,
                        'access_token' => $request->input('access_token'),
                        'linked_at' => now(),
                    ]);

                    // Commit transaction
                    DB::commit();
                } catch (\Exception $e) {
                    // Rollback transaction on any error
                    DB::rollBack();
                    \Log::error('Error creating Facebook user/account: ' . $e->getMessage(), [
                        'username' => $username,
                        'email' => $email,
                        'facebook_id' => $facebookId,
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e; // Re-throw to be caught by outer catch
                }

                $token = $user->createToken('mobile-app')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Account created successfully',
                    'data' => [
                        'token' => $token,
                        'user' => $this->formatUserData($user),
                    ],
                ], 201);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Facebook Sign-In database error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'facebook_id' => $request->input('facebook_id'),
                    'email' => $request->input('email'),
                    'has_first_name' => $request->has('first_name'),
                    'has_last_name' => $request->has('last_name'),
                ]
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Server error. Please check your Laravel server logs and try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Facebook Sign-In error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => [
                    'facebook_id' => $request->input('facebook_id'),
                    'email' => $request->input('email'),
                ]
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Facebook Sign-In failed: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Set initial password for Google users (no current password required)
     */
    public function setInitialPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|string|same:password',
            ], [
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
                'password_confirmation.required' => 'Password confirmation is required',
                'password_confirmation.same' => 'Passwords do not match',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Update password (no current password check for initial setup)
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password set successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Set initial password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set password: ' . $e->getMessage()
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
                'data' => array_intersect_key($input, array_flip(['username', 'firstName', 'lastName', 'email', 'role'])),
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
                'role' => 'required|string|in:customer,manager',
                'location' => 'nullable|string|max:255',
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
                'role.required' => 'Role is required',
                'role.in' => 'Invalid role specified',
            ]);

            if ($validator->fails()) {
                \Log::warning('Registration validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => array_intersect_key($input, array_flip(['username', 'firstName', 'lastName', 'email', 'role'])),
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
                'role' => $input['role'],
                'location' => $input['location'] ?? null,
            ]);

            // Send verification code email asynchronously (non-blocking)
            // Registration continues immediately, email sent in background
            try {
                $userName = trim($input['firstName'] . ' ' . $input['lastName']);
                Mail::to($input['email'])->queue(new VerificationCode($verificationCode, $userName));
                \Log::info('Registration verification code email queued', ['email' => $input['email']]);
            } catch (\Exception $e) {
                // Try synchronous send as fallback
                try {
                    $userName = trim($input['firstName'] . ' ' . $input['lastName']);
                    Mail::to($input['email'])->send(new VerificationCode($verificationCode, $userName));
                    \Log::info('Registration verification code email sent (sync fallback)', ['email' => $input['email']]);
                } catch (\Exception $e2) {
                    \Log::error('Failed to send registration verification code email: ' . $e2->getMessage(), [
                        'email' => $input['email'],
                        'error' => $e2->getMessage()
                    ]);
                    // Continue with registration even if email fails - user can request resend
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email.',
                'data' => [
                    'user' => $this->formatUserData($user),
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
                'request_data' => array_intersect_key($input ?? $request->all(), array_flip(['username', 'firstName', 'lastName', 'email', 'role']))
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
                'request_data' => array_intersect_key($input ?? $request->all(), array_flip(['username', 'firstName', 'lastName', 'email', 'role']))
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server error. Please check your Laravel server logs and try again.'
            ], 500);
        }
    }

    /**
     * Send verification code
     * Supports both registration (user doesn't exist) and resend (user exists)
     */
    public function sendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        // Check if user exists (for resend scenario)
        $user = User::where('email', $request->email)->first();
        
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

        // Send verification code email asynchronously (non-blocking)
        // Return success immediately, email will be sent in background
        try {
            $userName = $user ? ($user->firstName ?? $user->name ?? 'User') : null;
            // Dispatch to queue for faster response (even with sync queue, it's cleaner)
            Mail::to($request->email)->queue(new VerificationCode($verificationCode, $userName));
            \Log::info('Verification code email queued', ['email' => $request->email]);
        } catch (\Exception $e) {
            // Try synchronous send as fallback
            try {
                $userName = $user ? ($user->firstName ?? $user->name ?? 'User') : null;
                Mail::to($request->email)->send(new VerificationCode($verificationCode, $userName));
                \Log::info('Verification code email sent (sync fallback)', ['email' => $request->email]);
            } catch (\Exception $e2) {
                \Log::error('Failed to send verification code email: ' . $e2->getMessage(), [
                    'email' => $request->email,
                    'error' => $e2->getMessage()
                ]);
            }
        }
        
        // Return immediately - code is saved, email is being sent
        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email',
        ]);
    }

    /**
     * Verify email with code
     * Optimized for speed - reduced database queries, eager loading
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string|size:6',
        ]);

        // Optimized: Single query with all conditions
        $verification = EmailVerification::where('email', $request->email)
            ->where('code', $request->code)
            ->where('verified', false)
            ->where('expires_at', '>', now()) // Check expiry in query (faster)
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code',
            ], 400);
        }

        // Mark as verified immediately (fast direct update)
        $verification->verified = true;
        $verification->save();
        
        $email = $request->email;

        // Check if user exists and get with eager loading (optimized - single query)
        $user = User::with('facebookAccount')->where('email', $email)->first();
        
        if ($user) {
            // Update user verification status (queue if possible, but need immediate response)
            $user->email_verified_at = now();
            $user->save();
            
            // Create token (required for immediate response)
            $token = $user->createToken('mobile-app')->plainTextToken;

            // Return immediately - user data already loaded with relationships
            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'token' => $token,
                ],
            ]);
        }

        // User doesn't exist yet (registration flow)
        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. You can now complete registration.',
            'data' => [
                'user' => null,
                'token' => null,
                'email_verified' => true,
            ],
        ]);
    }

    /**
     * Handle logout request
     * Optimized for fastest response - uses direct DB query
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $token = $user->currentAccessToken();
            
            if ($token) {
                // Use direct DB query for fastest deletion (bypasses Eloquent overhead)
                // This is faster than $token->delete() for remote databases
                DB::table('personal_access_tokens')
                    ->where('id', $token->id)
                    ->delete();
            }
        } catch (\Exception $e) {
            // Log but don't fail - token will expire naturally
            \Log::warning('Logout token deletion: ' . $e->getMessage());
        }

        // Return immediately - token deletion is fast with direct query
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8',
                'new_password_confirmation' => 'required|string|same:new_password',
            ], [
                'current_password.required' => 'Current password is required',
                'new_password.required' => 'New password is required',
                'new_password.min' => 'New password must be at least 8 characters',
                'new_password_confirmation.required' => 'Password confirmation is required',
                'new_password_confirmation.same' => 'Passwords do not match',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 400);
            }

            // Check if new password is same as current password
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password',
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Change password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle password reset request (forgot password)
     * Sends verification code to email
     */
    public function requestPasswordReset(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
            ], [
                'email.required' => 'Email is required',
                'email.email' => 'Please enter a valid email address',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user exists
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                // Don't reveal if email exists or not for security
                return response()->json([
                    'success' => true,
                    'message' => 'If the email exists, a verification code has been sent.',
                ]);
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

            // Send verification code email asynchronously (non-blocking)
            try {
                $userName = $user->firstName ?? $user->name ?? 'User';
                Mail::to($request->email)->queue(new VerificationCode($verificationCode, $userName));
                \Log::info('Password reset verification code email queued', ['email' => $request->email]);
            } catch (\Exception $e) {
                // Try synchronous send as fallback
                try {
                    $userName = $user->firstName ?? $user->name ?? 'User';
                    Mail::to($request->email)->send(new VerificationCode($verificationCode, $userName));
                    \Log::info('Password reset verification code email sent (sync fallback)', ['email' => $request->email]);
                } catch (\Exception $e2) {
                    \Log::error('Failed to send password reset verification code email: ' . $e2->getMessage(), [
                        'email' => $request->email,
                        'error' => $e2->getMessage()
                    ]);
                    // Still return success - code is saved, user can request resend
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to your email',
            ]);
        } catch (\Exception $e) {
            \Log::error('Request password reset error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle password reset with verification code
     */
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'code' => 'required|string|size:6',
                'password' => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required|string|min:8',
            ], [
                'email.required' => 'Email is required',
                'email.email' => 'Please enter a valid email address',
                'code.required' => 'Verification code is required',
                'code.size' => 'Verification code must be 6 digits',
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Passwords do not match',
                'password_confirmation.required' => 'Password confirmation is required',
                'password_confirmation.min' => 'Password confirmation must be at least 8 characters',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify code
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

            // Find user
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Mark verification as used
            $verification->update(['verified' => true]);

            // Create new token for the user
            $token = $user->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
                'data' => [
                    'token' => $token,
                    'user' => $this->formatUserData($user),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Reset password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password: ' . $e->getMessage()
            ], 500);
        }
    }
}
