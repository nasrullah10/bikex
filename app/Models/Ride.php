<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'passenger_id',
        'rider_id',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'pickup_geohash',
        'drop_address',
        'drop_lat',
        'drop_lng',
        'vehicle_type',
        'estimated_distance',
        'estimated_duration',
        'actual_distance',
        'actual_duration',
        'base_fare',
        'distance_fare',
        'waiting_fare',
        'surge_multiplier',
        'promo_discount',
        'total_fare',
        'status',
        'requested_at',
        'offered_at',
        'accepted_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'passenger_rating',
        'rider_rating',
        'passenger_comment',
        'rider_comment',
        'route_polyline',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:8',
        'pickup_lng' => 'decimal:8',
        'drop_lat' => 'decimal:8',
        'drop_lng' => 'decimal:8',
        'estimated_distance' => 'decimal:2',
        'actual_distance' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'distance_fare' => 'decimal:2',
        'waiting_fare' => 'decimal:2',
        'surge_multiplier' => 'decimal:2',
        'promo_discount' => 'decimal:2',
        'total_fare' => 'decimal:2',
        'requested_at' => 'datetime',
        'offered_at' => 'datetime',
        'accepted_at' => 'datetime',
        'arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class, 'passenger_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RideOffer::class);
    }

    // Scopes
    public function scopeSearching($query)
    {
        return $query->where('status', 'searching');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['accepted', 'arrived', 'started']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->whereIn('status', ['cancelled_by_passenger', 'cancelled_by_rider']);
    }

    // Helpers
    public function isActive(): bool
    {
        return in_array($this->status, ['accepted', 'arrived', 'started']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled_by_passenger', 'cancelled_by_rider']);
    }

    public function riderEarning(): float
    {
        // Simple calculation: 80% of total fare
        return round($this->total_fare * 0.80, 2);
    }

     public function locations(): HasMany
    {
        return $this->hasMany(RideLocation::class)
            ->orderBy('recorded_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RideEvent::class)
            ->orderBy('event_at');
    }
     // ═══════════════════════════════════════════════════════════════
    // TRACKING HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get route as array of [lat, lng] for map
     */
    public function getRouteArray(): array
    {
        return $this->locations()
            ->select('lat', 'lng')
            ->get()
            ->map(fn($loc) => [(float)$loc->lat, (float)$loc->lng])
            ->toArray();
    }

    /**
     * Get route as Google Maps encoded polyline
     */
    public function getEncodedPolyline(): string
    {
        $points = $this->getRouteArray();
        return $this->encodePolyline($points);
    }

    /**
     * Get pickup location
     */
    public function getPickupLocation(): ?array
    {
        return [
            'lat' => (float)$this->pickup_lat,
            'lng' => (float)$this->pickup_lng,
            'address' => $this->pickup_address,
        ];
    }

    /**
     * Get drop location
     */
    public function getDropLocation(): ?array
    {
        return [
            'lat' => (float)$this->drop_lat,
            'lng' => (float)$this->drop_lng,
            'address' => $this->drop_address,
        ];
    }

    /**
     * Get actual distance traveled (from locations)
     */
    public function getActualDistanceKm(): float
    {
        $locations = $this->locations()->select('lat', 'lng')->get();
        
        if ($locations->count() < 2) {
            return (float)($this->actual_distance ?? 0);
        }

        $totalDistance = 0;
        for ($i = 1; $i < $locations->count(); $i++) {
            $totalDistance += $this->haversineDistance(
                (float)$locations[$i-1]->lat,
                (float)$locations[$i-1]->lng,
                (float)$locations[$i]->lat,
                (float)$locations[$i]->lng
            );
        }

        return round($totalDistance, 2);
    }

    /**
     * Get ride timeline for admin/logs
     */
    public function getTimeline(): array
    {
        $events = $this->events()
            ->with('ride')
            ->orderBy('event_at')
            ->get();

        return $events->map(function ($event) {
            return [
                'time' => $event->event_at->format('Y-m-d H:i:s'),
                'event' => $event->event_type,
                'location' => $event->lat ? [
                    'lat' => (float)$event->lat,
                    'lng' => (float)$event->lng,
                ] : null,
                'details' => $event->metadata,
                'by' => $event->triggered_by,
            ];
        })->toArray();
    }

    // Private helpers
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lngDelta / 2) * sin($lngDelta / 2);

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function encodePolyline(array $points): string
    {
        // Google Maps polyline encoding algorithm
        $encoded = '';
        $lastLat = 0;
        $lastLng = 0;

        foreach ($points as $point) {
            $lat = round($point[0] * 1e5);
            $lng = round($point[1] * 1e5);

            $encoded .= $this->encodeSignedNumber($lat - $lastLat);
            $encoded .= $this->encodeSignedNumber($lng - $lastLng);

            $lastLat = $lat;
            $lastLng = $lng;
        }

        return $encoded;
    }

    private function encodeSignedNumber(int $num): string
    {
        $num = $num << 1;
        if ($num < 0) {
            $num = ~$num;
        }
        return $this->encodeNumber($num);
    }

    private function encodeNumber(int $num): string
    {
        $encoded = '';
        while ($num >= 0x20) {
            $encoded .= chr((0x20 | ($num & 0x1f)) + 63);
            $num >>= 5;
        }
        $encoded .= chr($num + 63);
        return $encoded;
    }
}