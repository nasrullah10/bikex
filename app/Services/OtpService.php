<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Str;

class OtpService
{
    /**
     * Generate new OTP for phone number
     */
    public function generate(string $phone, string $purpose = 'registration'): array
    {
        // Invalidate all previous unused OTPs
        Otp::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $otp = $this->generateOtpCode();

        $otpRecord = Otp::create([
            'id' => (string) Str::uuid(),
            'phone' => $phone,
            'otp' => $otp,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'is_used' => false,
        ]);

        // TODO: Integrate SMS gateway (Twilio, SMSAlert, etc.)
        // For development: OTP returned in response

        return [
            'otp_id' => $otpRecord->id,
            'otp' => $otp, // REMOVE IN PRODUCTION
            'expires_at' => $otpRecord->expires_at,
            'message' => 'OTP bhej diya gaya hai. 5 minute mein valid hai.',
        ];
    }

    /**
     * Verify OTP code
     */
    public function verify(string $phone, string $otpCode, string $purpose = 'registration'): array
    {
        $otpRecord = Otp::where('phone', $phone)
            ->where('otp', $otpCode)
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return [
                'success' => false,
                'message' => 'Galat OTP. Dobara koshish karein.'
            ];
        }

        if ($otpRecord->isExpired()) {
            return [
                'success' => false,
                'message' => 'OTP expire ho gaya hai. Naya OTP mangwaein.'
            ];
        }

        if ($otpRecord->attempts >= 3) {
            return [
                'success' => false,
                'message' => 'Bahut si koshishein. Naya OTP mangwaein.'
            ];
        }

        // Increment attempts before final check
        $otpRecord->incrementAttempts();

        // Mark as used
        $otpRecord->markAsUsed();

        return [
            'success' => true,
            'message' => 'OTP verify ho gaya.'
        ];
    }

    /**
     * Resend OTP with 30 second cooldown
     */
    public function resend(string $phone, string $purpose = 'registration'): array
    {
        $recentOtp = Otp::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subSeconds(30))
            ->latest()
            ->first();

        if ($recentOtp) {
            $waitSeconds = 30 - now()->diffInSeconds($recentOtp->created_at);
            return [
                'success' => false,
                'message' => "Please wait {$waitSeconds} seconds before requesting new OTP."
            ];
        }

        return $this->generate($phone, $purpose);
    }

    /**
     * Generate random 6-digit OTP
     */
    private function generateOtpCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}