<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardEntry extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'week_start', 'week_end', 'rider_id', 'rank',
        'rides_completed', 'earnings', 'rating_avg',
        'online_hours', 'reward_amount'
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'earnings' => 'decimal:2',
        'rating_avg' => 'decimal:1',
        'online_hours' => 'decimal:2',
        'reward_amount' => 'decimal:2',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}