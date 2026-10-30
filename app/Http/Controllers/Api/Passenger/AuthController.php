<?php

namespace App\Http\Controllers\Api\Passenger;

use App\Http\Controllers\Api\BaseController;
use App\Models\Passenger;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    public function __construct(private OtpService $otpService) {}

    // ═══════════════════════════════════════════════════════════════
    // SEND OTP
    // POST /api/passenger/auth/send-otp
    // ═══════════════════════════════════════════════════════════════
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+92[0-9]{10}$/', 'max:15'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $phone = $request->input('phone');
        $purpose = User::where('phone', $phone)->exists() ? 'login' : 'registration';

        $result = $this->otpService->generate($phone, $purpose);

        return $this->successResponse([
            'otp_id' => $result['otp_id'],
            'expires_at' => $result['expires_at'],
            'expires_in_seconds' => 300,
            'purpose' => $purpose,
            'otp' => $result['otp'], // REMOVE IN PRODUCTION
        ], $result['message']);
    }

    // ═══════════════════════════════════════════════════════════════
    // VERIFY OTP
    // POST /api/passenger/auth/verify-otp
    // ═══════════════════════════════════════════════════════════════
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+92[0-9]{10}$/'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $phone = $request->input('phone');
        $otp = $request->input('otp');

        $result = $this->otpService->verify($phone, $otp, 'registration');
        
        if (!$result['success']) {
            $result = $this->otpService->verify($phone, $otp, 'login');
        }

        if (!$result['success']) {
            return $this->errorResponse($result['message'], 400);
        }

        $existingUser = User::where('phone', $phone)->first();

        // ═══════════════════════════════════════════════════════════
        // NEW PASSENGER
        // ═══════════════════════════════════════════════════════════
        if (!$existingUser) {
            $user = $this->createPassengerUser($phone);
            $token = $user->createToken('passenger-app', ['passenger'])->plainTextToken;

            return $this->successResponse([
                'is_new_user' => true,
                'phone_verified' => true,
                'phone' => $phone,
                'token' => $token,
                'token_type' => 'Bearer',
                'has_profile' => false,
                'message' => 'OTP verify ho gaya. Apni profile complete karein.',
            ], 'Welcome to BikeX!');
        }

        // ═══════════════════════════════════════════════════════════
        // EXISTING USER - Check if passenger
        // ═══════════════════════════════════════════════════════════
        if ($existingUser->user_type !== 'passenger') {
            return $this->errorResponse('Ye number rider/admin ke tor pe register hai.', 403);
        }

        $hasProfile = !is_null($existingUser->passenger);
        $token = $existingUser->createToken('passenger-app', ['passenger'])->plainTextToken;

        return $this->successResponse([
            'is_new_user' => false,
            'phone_verified' => true,
            'phone' => $phone,
            'user' => [
                'id' => $existingUser->id,
                'name' => $existingUser->name,
                'phone' => $existingUser->phone,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
            'has_profile' => $hasProfile,
            'message' => 'Welcome back!',
        ], 'Welcome back!');
    }

    // ═══════════════════════════════════════════════════════════════
    // RESEND OTP
    // POST /api/passenger/auth/resend-otp
    // ═══════════════════════════════════════════════════════════════
    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+92[0-9]{10}$/'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $phone = $request->input('phone');
        $purpose = User::where('phone', $phone)->exists() ? 'login' : 'registration';

        $result = $this->otpService->resend($phone, $purpose);

        if (!$result['success']) {
            return $this->errorResponse($result['message'], 429);
        }

        return $this->successResponse([
            'expires_at' => $result['expires_at'],
            'expires_in_seconds' => 300,
        ], $result['message']);
    }

    // ═══════════════════════════════════════════════════════════════
    // LOGOUT
    // POST /api/passenger/auth/logout
    // ═══════════════════════════════════════════════════════════════
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse([], 'Logout ho gaya.');
    }

    // ═══════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function createPassengerUser(string $phone): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'phone' => $phone,
            'name' => '',
            'email' => null,
            'password' => null,
            'user_type' => 'passenger',
            'registration_status' => 'phone_verified',
            'registration_data' => null,
            'referral_code' => $this->generateReferralCode(),
            'phone_verified_at' => now(),
            'is_active' => true,
            'is_banned' => false,
        ]);
    }

    private function generateReferralCode(): string
    {
        do {
            $code = 'BIKEX' . strtoupper(substr(md5(uniqid()), 0, 6));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}