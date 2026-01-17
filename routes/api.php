<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/google-signin', [AuthController::class, 'googleSignIn']);
    Route::post('/facebook-signin', [AuthController::class, 'facebookSignIn']);
    Route::post('/send-verification-code', [AuthController::class, 'sendVerificationCode']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/chatbot', [ChatbotController::class, 'handle']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/set-initial-password', [AuthController::class, 'setInitialPassword']);
        Route::get('/user', [ProfileController::class, 'user']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/tickets', [TicketController::class, 'store']);
    });
});

