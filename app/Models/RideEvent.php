<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideEvent extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ride_id',
        'event_type',
        'lat',
        'lng',
        'metadata',
        'notes',
        'triggered_by',
        'event_at',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'metadata' => 'array',
        'event_at' => 'datetime',
    ];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * Log a ride event
     */
    public static function log(string $rideId, string $eventType, array $data = []): self
    {
        return self::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'ride_id' => $rideId,
            'event_type' => $eventType,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'notes' => $data['notes'] ?? null,
            'triggered_by' => $data['triggered_by'] ?? 'system',
            'event_at' => $data['event_at'] ?? now(),
        ]);
    }
}