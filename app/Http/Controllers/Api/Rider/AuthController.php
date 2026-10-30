<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\BaseController;
use App\Models\Rider;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends BaseController
{
    public function __construct(private OtpService $otpService) {}

    // ═══════════════════════════════════════════════════════════════
    // STATUS ORDER (for progression checks)
    // ═══════════════════════════════════════════════════════════════
    private const STATUS_ORDER = [
        'phone_verified' => 1,
        'personal_info_done' => 2,
        'vehicle_info_done' => 3,
        'documents_done' => 4,
        'under_review' => 5,
        'approved' => 6,
        'rejected' => 0,
    ];

    // ═══════════════════════════════════════════════════════════════
    // STEP ALLOWED STATUSES (idempotent - allow current + future steps)
    // ═══════════════════════════════════════════════════════════════
    private const STEP_ALLOWED_STATUSES = [
        'personal_info' => ['phone_verified', 'personal_info_done', 'vehicle_info_done', 'documents_done'],
        'vehicle_info' => ['personal_info_done', 'vehicle_info_done', 'documents_done'],
        'documents' => ['vehicle_info_done', 'documents_done'],
        'review' => ['documents_done'],
        'submit' => ['documents_done'],
    ];

    // ═══════════════════════════════════════════════════════════════
    // 1. SEND OTP
    // POST /api/rider/auth/send-otp
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
    // 2. VERIFY OTP
    // POST /api/rider/auth/verify-otp
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

        // Try registration first, then login
        $result = $this->otpService->verify($phone, $otp, 'registration');
        
        if (!$result['success']) {
            $result = $this->otpService->verify($phone, $otp, 'login');
        }

        if (!$result['success']) {
            return $this->errorResponse($result['message'], 400);
        }

        $existingUser = User::where('phone', $phone)->first();

        // ═══════════════════════════════════════════════════════════
        // NEW USER
        // ═══════════════════════════════════════════════════════════
        if (!$existingUser) {
            $user = $this->createBasicUser($phone);
            $token = $user->createToken('rider-app', ['rider'])->plainTextToken;

            return $this->successResponse([
                'is_new_user' => true,
                'phone_verified' => true,
                'phone' => $phone,
                'token' => $token,
                'token_type' => 'Bearer',
                'registration_status' => 'phone_verified',
                'next_step' => 'personal_info',
            ], 'OTP verify ho gaya. Apni details complete karein.');
        }

        // ═══════════════════════════════════════════════════════════
        // EXISTING USER - Return actual status, not just next_step
        // ═══════════════════════════════════════════════════════════
        $registrationStatus = $existingUser->registration_status;
        $nextStep = $existingUser->getRegistrationStep();
        $token = $existingUser->createToken('rider-app', ['rider'])->plainTextToken;

        // AGAR APPROVED HAI → Dashboard bhejo
        if ($registrationStatus === 'approved') {
            $rider = $existingUser->rider;
            
            return $this->successResponse([
                'is_new_user' => false,
                'phone_verified' => true,
                'phone' => $phone,
                'user' => [
                    'id' => $existingUser->id,
                    'name' => $existingUser->name,
                    'phone' => $existingUser->phone,
                    'registration_status' => 'approved',
                ],
                'rider' => $rider ? [
                    'id' => $rider->id,
                    'vehicle_type' => $rider->vehicle_type,
                    'vehicle_number' => $rider->vehicle_number,
                    'rating' => $rider->rating,
                ] : null,
                'token' => $token,
                'token_type' => 'Bearer',
                'registration_status' => 'approved',
                'next_step' => 'dashboard',
                'message' => 'Welcome back! Aap ab rides accept kar sakte hain.',
            ], 'Welcome back!');
        }
        return $this->successResponse([
            'is_new_user' => false,
            'phone_verified' => true,
            'phone' => $phone,
            'user' => [
                'id' => $existingUser->id,
                'name' => $existingUser->name,
                'phone' => $existingUser->phone,
                'registration_status' => $registrationStatus,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
            'registration_status' => $registrationStatus,
            'next_step' => $nextStep,
            'message' => $this->getStepMessage($nextStep),
        ], 'Welcome back!');
    }

    // ═══════════════════════════════════════════════════════════════
    // 3. RESEND OTP
    // POST /api/rider/auth/resend-otp
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
    // 4. STEP 1: PERSONAL INFO (FIXED - Idempotent)
    // POST /api/rider/register/step1
    // Auth Required
    // ═══════════════════════════════════════════════════════════════
    public function step1PersonalInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'cnic_number' => ['required', 'string', 'max:15'],
            'cnic_front_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'cnic_back_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'cnic_expiry' => ['nullable', 'date', 'after:today'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();

        // FIX: Check if user can access this step (idempotent)
        $allowedStatuses = self::STEP_ALLOWED_STATUSES['personal_info'] ?? [];
        
        if (!in_array($user->registration_status, $allowedStatuses)) {
            // User is already past this step or rejected
            if ($user->registration_status === 'under_review') {
                return $this->errorResponse('Aapki application already submit ho chuki hai. Admin review ka wait karein.', 400);
            }
            if ($user->registration_status === 'approved') {
                return $this->errorResponse('Aapka account already approved hai.', 400);
            }
            if ($user->registration_status === 'rejected') {
                return $this->errorResponse('Aapki application reject ho gayi hai. Dobara apply karein.', 400);
            }
            return $this->errorResponse('Ye step already complete ho gaya hai.', 400);
        }

        // Store files
        $cnicFrontPath = $request->file('cnic_front_photo')->store('riders/cnic', 'public');
        $cnicBackPath = $request->file('cnic_back_photo')->store('riders/cnic', 'public');

        // Update registration data
        $data = $user->registration_data ?? [];
        $data['cnic_number'] = $request->input('cnic_number');
        $data['cnic_front_photo'] = $cnicFrontPath;
        $data['cnic_back_photo'] = $cnicBackPath;
        $data['cnic_expiry'] = $request->input('cnic_expiry');

        $updateData = [
            'name' => $request->input('name'),
            'registration_data' => $data,
        ];

        // Only promote status if currently at phone_verified
        // If already at personal_info_done or beyond, keep current status
        if ($user->registration_status === 'phone_verified') {
            $updateData['registration_status'] = 'personal_info_done';
        }

        $user->update($updateData);

        return $this->successResponse([
            'step_completed' => 'personal_info',
            'next_step' => 'vehicle_info',
            'is_update' => $user->registration_status !== 'phone_verified',
            'registration_status' => $user->fresh()->registration_status,
        ], 'Step 1 complete! Ab vehicle details dalein.');
    }

    // ═══════════════════════════════════════════════════════════════
    // 5. STEP 2: VEHICLE INFO (FIXED - Idempotent)
    // POST /api/rider/register/step2
    // Auth Required
    // ═══════════════════════════════════════════════════════════════
    public function step2VehicleInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_type' => ['required', 'string', Rule::in(['bike', 'rickshaw', 'car', 'car_ac'])],
            'vehicle_company' => ['nullable', 'string', 'max:50'],
            'vehicle_model' => ['nullable', 'string', 'max:50'],
            'vehicle_year' => ['nullable', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'vehicle_number' => ['required', 'string', 'max:20'],
            'vehicle_color' => ['nullable', 'string', 'max:30'],
            'vehicle_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();

        // FIX: Check if user can access this step
        $allowedStatuses = self::STEP_ALLOWED_STATUSES['vehicle_info'] ?? [];
        
        if (!in_array($user->registration_status, $allowedStatuses)) {
            if ($user->registration_status === 'phone_verified') {
                return $this->errorResponse('Pehle Step 1 complete karein.', 403);
            }
            if (in_array($user->registration_status, ['under_review', 'approved', 'rejected'])) {
                return $this->errorResponse('Aapki registration process complete ho chuki hai.', 400);
            }
            return $this->errorResponse('Ye step accessible nahi hai.', 403);
        }

        // Store file
        $vehiclePhotoPath = $request->file('vehicle_photo')->store('riders/vehicle', 'public');

        // Update registration data
        $data = $user->registration_data ?? [];
        $data['vehicle_type'] = $request->input('vehicle_type');
        $data['vehicle_company'] = $request->input('vehicle_company');
        $data['vehicle_model'] = $request->input('vehicle_model');
        $data['vehicle_year'] = $request->input('vehicle_year');
        $data['vehicle_number'] = $request->input('vehicle_number');
        $data['vehicle_color'] = $request->input('vehicle_color');
        $data['vehicle_photo'] = $vehiclePhotoPath;

        $updateData = [
            'registration_data' => $data,
        ];

        // Only promote status if currently at personal_info_done
        if ($user->registration_status === 'personal_info_done') {
            $updateData['registration_status'] = 'vehicle_info_done';
        }

        $user->update($updateData);

        return $this->successResponse([
            'step_completed' => 'vehicle_info',
            'next_step' => 'documents',
            'is_update' => $user->registration_status !== 'personal_info_done',
            'registration_status' => $user->fresh()->registration_status,
        ], 'Step 2 complete! Ab documents upload karein.');
    }

    // ═══════════════════════════════════════════════════════════════
    // 6. STEP 3: DOCUMENTS (FIXED - Idempotent)
    // POST /api/rider/register/step3
    // Auth Required
    // ═══════════════════════════════════════════════════════════════
    public function step3Documents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_number' => ['required', 'string', 'max:50'],
            'license_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'license_expiry' => ['required', 'date', 'after:today'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();

        // FIX: Check if user can access this step
        $allowedStatuses = self::STEP_ALLOWED_STATUSES['documents'] ?? [];
        
        if (!in_array($user->registration_status, $allowedStatuses)) {
            if (in_array($user->registration_status, ['phone_verified', 'personal_info_done'])) {
                return $this->errorResponse('Pehle previous steps complete karein.', 403);
            }
            if (in_array($user->registration_status, ['under_review', 'approved', 'rejected'])) {
                return $this->errorResponse('Aapki registration process complete ho chuki hai.', 400);
            }
            return $this->errorResponse('Ye step accessible nahi hai.', 403);
        }

        // Store file
        $licensePhotoPath = $request->file('license_photo')->store('riders/license', 'public');

        // Update registration data
        $data = $user->registration_data ?? [];
        $data['license_number'] = $request->input('license_number');
        $data['license_photo'] = $licensePhotoPath;
        $data['license_expiry'] = $request->input('license_expiry');

        $updateData = [
            'registration_data' => $data,
        ];

        // Only promote status if currently at vehicle_info_done
        if ($user->registration_status === 'vehicle_info_done') {
            $updateData['registration_status'] = 'documents_done';
        }

        $user->update($updateData);

        return $this->successResponse([
            'step_completed' => 'documents',
            'next_step' => 'review',
            'can_submit' => true,
            'is_update' => $user->registration_status !== 'vehicle_info_done',
            'registration_status' => $user->fresh()->registration_status,
        ], 'Step 3 complete! Review karein aur submit karein.');
    }

    // ═══════════════════════════════════════════════════════════════
    // 7. SUBMIT FOR VERIFICATION
    // POST /api/rider/register/submit
    // Auth Required
    // ═══════════════════════════════════════════════════════════════
    public function submitForVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        // Must be at documents_done
        if ($user->registration_status !== 'documents_done') {
            if ($user->registration_status === 'under_review') {
                return $this->errorResponse('Aapki application already submit ho chuki hai.', 400);
            }
            if ($user->registration_status === 'approved') {
                return $this->errorResponse('Aapka account already approved hai.', 400);
            }
            return $this->errorResponse('Pehle sab steps complete karein.', 403);
        }

        $data = $user->registration_data ?? [];

        // Validate all required data is present
        if (empty($data['cnic_number']) || empty($data['vehicle_number']) || empty($data['license_number'])) {
            return $this->errorResponse('Sab details complete nahi hain.', 403);
        }

        // Create rider record
        DB::transaction(function () use ($user, $data) {
            Rider::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'cnic_number' => $data['cnic_number'],
                'cnic_front_photo' => $data['cnic_front_photo'],
                'cnic_back_photo' => $data['cnic_back_photo'],
                'cnic_expiry' => $data['cnic_expiry'] ?? null,
                'license_number' => $data['license_number'],
                'license_photo' => $data['license_photo'],
                'license_expiry' => $data['license_expiry'],
                'vehicle_type' => $data['vehicle_type'],
                'vehicle_company' => $data['vehicle_company'] ?? null,
                'vehicle_model' => $data['vehicle_model'] ?? null,
                'vehicle_year' => $data['vehicle_year'] ?? null,
                'vehicle_number' => $data['vehicle_number'],
                'vehicle_color' => $data['vehicle_color'] ?? null,
                'vehicle_photo' => $data['vehicle_photo'],
                'verification_status' => 'under_review',
                'rating' => 5.0,
                'total_rides' => 0,
                'total_earnings' => 0.00,
                'total_cancelled' => 0,
                'is_online' => false,
                'is_available' => false,
            ]);

            $user->update([
                'registration_status' => 'under_review',
            ]);
        });

        return $this->successResponse([
            'verification_status' => 'under_review',
            'next_step' => 'under_review',
            'estimated_time' => '24-48 hours',
        ], 'Registration submit ho gayi! Admin verification ka wait karein.');
    }

    // ═══════════════════════════════════════════════════════════════
    // 8. GET STATUS
    // GET /api/rider/register/status
    // Auth Required
    // ═══════════════════════════════════════════════════════════════
    public function getStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $rider = $user->rider;

        // If already submitted to riders table
        if ($rider) {
            return $this->successResponse([
                'is_submitted' => true,
                'verification_status' => $rider->verification_status,
                'next_step' => $rider->verification_status === 'approved' ? 'dashboard' : 'under_review',
                'can_access_dashboard' => $rider->verification_status === 'approved',
                'rider_id' => $rider->id,
            ]);
        }

        // Registration in progress
        $data = $user->registration_data ?? [];
        $nextStep = $user->getRegistrationStep();

        return $this->successResponse([
            'is_submitted' => false,
            'current_step' => $nextStep,
            'registration_status' => $user->registration_status,
            'steps_completed' => [
                'personal_info' => !empty($data['cnic_number']),
                'vehicle_info' => !empty($data['vehicle_number']),
                'documents' => !empty($data['license_number']),
            ],
            'next_step' => $nextStep,
            'can_submit' => $user->canSubmitRegistration(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 9. LOGOUT
    // POST /api/rider/auth/logout
    // Auth Required
    // ═══════════════════════════════════════════════════════════════
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse([], 'Logout ho gaya.');
    }

    // ═══════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function createBasicUser(string $phone): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'phone' => $phone,
            'name' => '',
            'email' => null,
            'password' => null,
            'user_type' => 'rider',
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

    private function getStepMessage(string $step): string
    {
        return match($step) {
            'personal_info' => 'Apni personal details complete karein.',
            'vehicle_info' => 'Apni vehicle details complete karein.',
            'documents' => 'Apne documents upload karein.',
            'review' => 'Review karein aur submit karein.',
            'under_review' => 'Verification ka wait karein.',
            'dashboard' => 'Welcome! Aap ab rides accept kar sakte hain.',
            'rejected' => 'Aapki application reject ho gayi. Dobara apply karein.',
            default => 'Registration complete karein.',
        };
    }
}