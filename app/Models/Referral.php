<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'referrer_id', 'referred_id', 'level', 'reward_amount',
        'reward_status', 'milestone_triggered', 'rides_required',
        'rides_completed', 'released_at', 'released_transaction_id'
    ];

    protected $casts = [
        'level' => 'integer',
        'reward_amount' => 'decimal:2',
        'rides_required' => 'integer',
        'rides_completed' => 'integer',
        'released_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function releasedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'released_transaction_id');
    }
}