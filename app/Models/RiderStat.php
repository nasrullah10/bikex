<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderStat extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'rider_id', 'period_type', 'period_start', 'rides_completed',
        'rides_cancelled', 'rides_missed', 'earnings', 'commission_saved',
        'online_hours', 'peak_hours', 'rating_avg', 'rating_count'
    ];

    protected $casts = [
        'period_start' => 'date',
        'earnings' => 'decimal:2',
        'commission_saved' => 'decimal:2',
        'online_hours' => 'decimal:2',
        'peak_hours' => 'decimal:2',
        'rating_avg' => 'decimal:1',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}