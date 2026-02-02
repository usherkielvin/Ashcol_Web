<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/google-signin', [AuthController::class, 'googleSignIn']);
    Route::post('/google-register', [AuthController::class, 'googleRegister']); // New Google registration endpoint
    Route::post('/facebook-signin', [AuthController::class, 'facebookSignIn']);
    Route::post('/send-verification-code', [AuthController::class, 'sendVerificationCode']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/forgot-password', [AuthController::class, 'requestPasswordReset']); // Alias for Android app compatibility
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/chatbot', [ChatbotController::class, 'handle']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/set-initial-password', [AuthController::class, 'setInitialPassword']);
        Route::get('/user', [ProfileController::class, 'user']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
        Route::put('/profile/photo', [ProfileController::class, 'updatePhoto']);
        Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);
        Route::post('/update-location', [ProfileController::class, 'updateLocation']);
        
        // Ticket routes - using the API TicketController
        Route::get('/test', [TicketController::class, 'test']); // Test endpoint
        Route::post('/tickets', [\App\Http\Controllers\TicketController::class, 'store']); // This uses the web controller for ticket creation
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/{ticketId}', [TicketController::class, 'show']);
        Route::put('/tickets/{ticketId}/status', [TicketController::class, 'updateStatus']);
        Route::post('/tickets/{ticketId}/accept', [TicketController::class, 'accept']);
        Route::post('/tickets/{ticketId}/reject', [TicketController::class, 'reject']);
        
        // Manager-specific routes
        Route::get('/manager/tickets', [TicketController::class, 'getManagerTickets']);
        
        // Employee-specific routes
        Route::get('/employee/tickets', [TicketController::class, 'getEmployeeTickets']);
        Route::get('/employee/schedule', [TicketController::class, 'getEmployeeSchedule']);
        
        Route::get('/employees', [TicketController::class, 'getEmployees']);
        
        // Schedule management routes
        Route::put('/tickets/{ticketId}/schedule', [TicketController::class, 'setSchedule']);
    });
});

