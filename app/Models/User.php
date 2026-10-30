<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, HasRoles;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'phone',
        'password',
        'name',
        'email',
        'avatar',
        'user_type',
        'registration_status',
        'registration_data',
        'referral_code',
        'referred_by',
        'wallet_balance',
        'fcm_token',
        'device_info',
        'is_active',
        'is_banned',
        'ban_reason',
        'last_login_at',
        'last_login_ip',
        'phone_verified_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'remember_token',
        'password'
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'wallet_balance' => 'decimal:2',
        'device_info' => 'array',
        'registration_data' => 'array',
        'is_active' => 'boolean',
        'is_banned' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredUsers(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function rider(): HasOne
    {
        return $this->hasOne(Rider::class);
    }

    public function passenger(): HasOne
    {
        return $this->hasOne(Passenger::class);
    }

    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // ═══════════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════════

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_banned', false);
    }

    public function scopeRiders($query)
    {
        return $query->where('user_type', 'rider');
    }

    public function scopePassengers($query)
    {
        return $query->where('user_type', 'passenger');
    }

    public function scopeAdmins($query)
    {
        return $query->where('user_type', 'admin');
    }

    // ═══════════════════════════════════════════════════════════════
    // TYPE CHECKS
    // ═══════════════════════════════════════════════════════════════

    public function isRider(): bool
    {
        return $this->user_type === 'rider';
    }

    public function isPassenger(): bool
    {
        return $this->user_type === 'passenger';
    }

    public function isAdminUser(): bool
    {
        return $this->user_type === 'admin' && $this->admin !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isAdminUser() && $this->admin->isSuperAdmin();
    }

    public function adminCan(string $permission): bool
    {
        if (!$this->isAdminUser()) {
            return false;
        }
        return $this->admin->hasPermission($permission);
    }

    // ═══════════════════════════════════════════════════════════════
    // REGISTRATION HELPERS (FIXED - IDEMPOTENT)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get current registration step for frontend navigation
     */
    public function getRegistrationStep(): string
    {
        $data = $this->registration_data ?? [];

        // Already submitted or approved
        if ($this->registration_status === 'under_review') return 'under_review';
        if ($this->registration_status === 'approved') return 'dashboard';
        if ($this->registration_status === 'rejected') return 'rejected';

        // Check what data is missing
        if (empty($data['cnic_number'])) return 'personal_info';
        if (empty($data['vehicle_number'])) return 'vehicle_info';
        if (empty($data['license_number'])) return 'documents';

        // All data present but not submitted
        if ($this->registration_status === 'documents_done') return 'review';

        // Fallback based on status
        return match($this->registration_status) {
            'phone_verified' => 'personal_info',
            'personal_info_done' => 'vehicle_info',
            'vehicle_info_done' => 'documents',
            'documents_done' => 'review',
            default => 'personal_info',
        };
    }

    /**
     * Check if registration is fully complete and approved
     */
    public function isRegistrationComplete(): bool
    {
        return $this->registration_status === 'approved';
    }

    /**
     * Check if user can submit for verification
     */
    public function canSubmitRegistration(): bool
    {
        $data = $this->registration_data ?? [];
        return !empty($data['cnic_number'])
            && !empty($data['vehicle_number'])
            && !empty($data['license_number']);
    }

    /**
     * Check if user can access a specific step (idempotent)
     */
    public function canAccessStep(string $step): bool
    {
        $statusOrder = [
            'phone_verified' => 1,
            'personal_info_done' => 2,
            'vehicle_info_done' => 3,
            'documents_done' => 4,
            'under_review' => 5,
            'approved' => 6,
            'rejected' => 0,
        ];

        $stepRequirements = [
            'personal_info' => ['phone_verified', 'personal_info_done', 'vehicle_info_done', 'documents_done'],
            'vehicle_info' => ['personal_info_done', 'vehicle_info_done', 'documents_done'],
            'documents' => ['vehicle_info_done', 'documents_done'],
            'review' => ['documents_done'],
            'submit' => ['documents_done'],
        ];

        $allowedStatuses = $stepRequirements[$step] ?? [];
        return in_array($this->registration_status, $allowedStatuses);
    }

    // ═══════════════════════════════════════════════════════════════
    // REFERRAL
    // ═══════════════════════════════════════════════════════════════

    public function generateReferralCode(): string
    {
        do {
            $code = 'BIKEX' . strtoupper(substr(md5(uniqid()), 0, 6));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    // ═══════════════════════════════════════════════════════════════
    // WALLET
    // ═══════════════════════════════════════════════════════════════

    public function creditWallet(float $amount, string $type, string $description, ?array $metadata = null): Transaction
    {
        $this->wallet_balance += $amount;
        $this->save();

        return $this->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $this->wallet_balance,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public function debitWallet(float $amount, string $type, string $description, ?array $metadata = null): ?Transaction
    {
        if ($this->wallet_balance < $amount) {
            return null;
        }

        $this->wallet_balance -= $amount;
        $this->save();

        return $this->transactions()->create([
            'type' => $type,
            'amount' => -$amount,
            'balance_after' => $this->wallet_balance,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}