<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rider Controllers
use App\Http\Controllers\Api\Rider\AuthController as RiderAuthController;
use App\Http\Controllers\Api\Rider\DashboardController;
use App\Http\Controllers\Api\Rider\TrackingController;
use App\Http\Controllers\Api\Rider\RideController as RiderRideController;

// Passenger Controllers
use App\Http\Controllers\Api\Passenger\AuthController as PassengerAuthController;
use App\Http\Controllers\Api\Passenger\ProfileController;
use App\Http\Controllers\Api\Passenger\RideController as PassengerRideController;
use App\Http\Controllers\Api\Passenger\WalletController as PassengerWalletController;

// ═══════════════════════════════════════════════════════════════
// PUBLIC ROUTES
// ═══════════════════════════════════════════════════════════════

// ─── Rider Auth ───
Route::prefix('rider/auth')->group(function () {
    Route::post('/send-otp', [RiderAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [RiderAuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [RiderAuthController::class, 'resendOtp']);
});

// ─── Passenger Auth ───
Route::prefix('passenger/auth')->group(function () {
    Route::post('/send-otp', [PassengerAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [PassengerAuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [PassengerAuthController::class, 'resendOtp']);
});

// ═══════════════════════════════════════════════════════════════
// PROTECTED ROUTES
// ═══════════════════════════════════════════════════════════════

// ─── RIDER ROUTES ───
Route::middleware('auth:sanctum')->prefix('rider')->group(function () {
    
    // Registration
    Route::post('/register/step1', [RiderAuthController::class, 'step1PersonalInfo']);
    Route::post('/register/step2', [RiderAuthController::class, 'step2VehicleInfo']);
    Route::post('/register/step3', [RiderAuthController::class, 'step3Documents']);
    Route::post('/register/submit', [RiderAuthController::class, 'submitForVerification']);
    Route::get('/register/status', [RiderAuthController::class, 'getStatus']);
    
    // Auth
    Route::post('/auth/logout', [RiderAuthController::class, 'logout']);
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/online', [DashboardController::class, 'toggleOnline']);
    Route::post('/location', [DashboardController::class, 'updateLocation']);
    
    // Earnings & Wallet
    Route::get('/earnings', [DashboardController::class, 'earnings']);
    Route::get('/wallet', [DashboardController::class, 'wallet']);
    
    // Ride Offers
    Route::get('/rides/offers', [RiderRideController::class, 'offers']);
    Route::post('/rides/offers/{offer_id}/accept', [RiderRideController::class, 'acceptOffer']);
    Route::post('/rides/offers/{offer_id}/decline', [RiderRideController::class, 'declineOffer']);
    
    // Current & History
    Route::get('/rides/current', [RiderRideController::class, 'current']);
    Route::get('/rides/history', [RiderRideController::class, 'history']);
    
    // Ride Actions
    Route::post('/rides/{id}/status', [RiderRideController::class, 'updateStatus']);
    Route::post('/rides/{id}/cancel', [RiderRideController::class, 'cancel']);


      // Tracking
    Route::post('/rides/{ride_id}/location', [TrackingController::class, 'updateLocation']);
    Route::post('/rides/{ride_id}/locations', [TrackingController::class, 'updateLocations']);

});

// ─── PASSENGER ROUTES ───
Route::middleware('auth:sanctum')->prefix('passenger')->group(function () {
    
    // Auth
    Route::post('/auth/logout', [PassengerAuthController::class, 'logout']);
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    
    // Saved Places
    Route::get('/saved-places', [ProfileController::class, 'getSavedPlaces']);
    Route::post('/saved-places', [ProfileController::class, 'savePlace']);
    
    // Rides
    Route::post('/rides/request', [PassengerRideController::class, 'requestRide']);
    Route::get('/rides/{id}', [PassengerRideController::class, 'show']);
    Route::post('/rides/{id}/cancel', [PassengerRideController::class, 'cancel']);
    Route::post('/rides/{id}/rate', [PassengerRideController::class, 'rate']);
    Route::get('/rides', [PassengerRideController::class, 'history']);
    
    // Wallet
    Route::get('/wallet', [PassengerWalletController::class, 'index']);
});

// Default user route
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');