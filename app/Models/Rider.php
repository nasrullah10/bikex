<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rider extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'cnic_number',
        'cnic_front_photo',
        'cnic_back_photo',
        'cnic_expiry',
        'license_number',
        'license_photo',
        'license_expiry',
        'vehicle_type',
        'vehicle_company',
        'vehicle_model',
        'vehicle_year',
        'vehicle_number',
        'vehicle_color',
        'vehicle_photo',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'is_online',
        'is_available',
        'current_lat',
        'current_lng',
        'current_heading',
        'location_updated_at',
        'rating',
        'total_rides',
        'total_earnings',
        'total_cancelled',
        'current_plan',
        'subscription_expires_at',
        'emergency_contact_1',
        'emergency_contact_2'
    ];

    protected $casts = [
        'cnic_expiry' => 'date',
        'license_expiry' => 'date',
        'verified_at' => 'datetime',
        'is_online' => 'boolean',
        'is_available' => 'boolean',
        'current_lat' => 'decimal:8',
        'current_lng' => 'decimal:8',
        'current_heading' => 'decimal:2',
        'location_updated_at' => 'datetime',
        'rating' => 'decimal:1',
        'total_earnings' => 'decimal:2',
        'subscription_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }
}