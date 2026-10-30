<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class RideLocation extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ride_id',
        'lat',
        'lng',
        'accuracy',
        'speed',
        'heading',
        'source',
        'recorded_at',
        'received_at',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'recorded_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * Create location with spatial point using raw SQL
     */
    public static function createLocation(string $rideId, float $lat, float $lng, array $metadata = []): self
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        
        DB::insert(
            'INSERT INTO ride_locations 
             (id, ride_id, lat, lng, location, accuracy, speed, heading, source, recorded_at, received_at) 
             VALUES (?, ?, ?, ?, POINT(?, ?), ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $rideId,
                $lat,
                $lng,
                $lng, // POINT(x=lng, y=lat)
                $lat,
                $metadata['accuracy'] ?? null,
                $metadata['speed'] ?? null,
                $metadata['heading'] ?? null,
                $metadata['source'] ?? 'rider_app',
                $metadata['recorded_at'] ?? now(),
                now(),
            ]
        );

        return self::find($id);
    }
}