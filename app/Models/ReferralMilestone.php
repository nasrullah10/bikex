<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralMilestone extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'milestone_type', 'target_count', 'achieved_count',
        'reward_amount', 'status', 'achieved_at', 'claimed_at', 'claimed_transaction_id'
    ];

    protected $casts = [
        'target_count' => 'integer',
        'achieved_count' => 'integer',
        'reward_amount' => 'decimal:2',
        'achieved_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claimedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'claimed_transaction_id');
    }
}