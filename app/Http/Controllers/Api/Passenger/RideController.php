<?php

namespace App\Http\Controllers\Api\Passenger;

use App\Http\Controllers\Api\BaseController;
use App\Models\Ride;
use App\Models\RideOffer;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RideController extends BaseController
{
    // ═══════════════════════════════════════════════════════════════
    // REQUEST NEW RIDE
    // POST /api/passenger/rides/request
    // ═══════════════════════════════════════════════════════════════
    public function requestRide(Request $request): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'pickup_address' => ['required', 'string', 'max:500'],
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'drop_address' => ['required', 'string', 'max:500'],
            'drop_lat' => ['required', 'numeric', 'between:-90,90'],
            'drop_lng' => ['required', 'numeric', 'between:-180,180'],
            'vehicle_type' => ['required', 'string', 'in:bike,rickshaw,car,car_ac'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $passenger = $user->passenger;

        if (!$passenger) {
            return $this->errorResponse('Pehle profile complete karein.', 403);
        }

        // Check for active ride
        $activeRide = Ride::where('passenger_id', $passenger->id)
            ->whereIn('status', ['searching', 'offered', 'accepted', 'arrived', 'started'])
            ->first();

        if ($activeRide) {
            return $this->errorResponse('Aapke paas already active ride hai.', 400);
        }

        // Calculate fare
        $distance = $this->calculateDistance(
            $request->input('pickup_lat'),
            $request->input('pickup_lng'),
            $request->input('drop_lat'),
            $request->input('drop_lng')
        );

        $baseFare = $this->getBaseFare($request->input('vehicle_type'));
        $distanceFare = $distance * $this->getPerKmRate($request->input('vehicle_type'));
        $totalFare = $baseFare + $distanceFare;

        DB::beginTransaction();

        try {
            $ride = Ride::create([
                'id' => (string) Str::uuid(),
                'passenger_id' => $passenger->id,
                'pickup_address' => $request->input('pickup_address'),
                'pickup_lat' => $request->input('pickup_lat'),
                'pickup_lng' => $request->input('pickup_lng'),
                'pickup_geohash' => $this->encodeGeohash(
                    $request->input('pickup_lat'),
                    $request->input('pickup_lng')
                ),
                'drop_address' => $request->input('drop_address'),
                'drop_lat' => $request->input('drop_lat'),
                'drop_lng' => $request->input('drop_lng'),
                'vehicle_type' => $request->input('vehicle_type'),
                'estimated_distance' => round($distance, 2),
                'estimated_duration' => ceil($distance * 3 * 60),
                'base_fare' => $baseFare,
                'distance_fare' => round($distanceFare, 2),
                'waiting_fare' => 0.00,
                'surge_multiplier' => 1.00,
                'promo_discount' => 0.00,
                'total_fare' => round($totalFare, 2),
                'status' => 'searching',
                'requested_at' => now(),
            ]);

            // Find nearby online riders
            $nearbyRiders = $this->findNearbyRiders(
                $request->input('pickup_lat'),
                $request->input('pickup_lng'),
                $request->input('vehicle_type')
            );

            // Create offers for nearby riders
            foreach ($nearbyRiders as $rider) {
                RideOffer::create([
                    'id' => (string) Str::uuid(),
                    'ride_id' => $ride->id,
                    'rider_id' => $rider->id,
                    'offered_at' => now(),
                    'expires_at' => now()->addSeconds(30),
                ]);
            }

            DB::commit();

            return $this->successResponse([
                'ride' => $ride->fresh(),
                'fare_estimate' => [
                    'base_fare' => $baseFare,
                    'distance_fare' => round($distanceFare, 2),
                    'total_fare' => round($totalFare, 2),
                    'distance_km' => round($distance, 2),
                    'estimated_minutes' => ceil($distance * 3),
                ],
                'offers_sent' => count($nearbyRiders),
                'search_timeout' => 60,
            ], 'Ride request bhej di gayi. Rider ka intezar karein.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Ride request fail ho gayi: ' . $e->getMessage(), 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // GET RIDE STATUS
    // GET /api/passenger/rides/{id}
    // ═══════════════════════════════════════════════════════════════
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $passenger = $user->passenger;

        if (!$passenger) {
            return $this->errorResponse('Passenger profile nahi hai.', 403);
        }

        $ride = Ride::where('id', $id)
            ->where('passenger_id', $passenger->id)
            ->with(['rider.user', 'offers.rider.user'])
            ->first();

        if (!$ride) {
            return $this->errorResponse('Ride nahi mili.', 404);
        }

        return $this->successResponse([
            'ride' => $ride,
        ], 'Ride details');
    }

    // ═══════════════════════════════════════════════════════════════
    // CANCEL RIDE
    // POST /api/passenger/rides/{id}/cancel
    // ═══════════════════════════════════════════════════════════════
    public function cancel(Request $request, string $id): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $passenger = $user->passenger;

        if (!$passenger) {
            return $this->errorResponse('Passenger profile nahi hai.', 403);
        }

        $ride = Ride::where('id', $id)
            ->where('passenger_id', $passenger->id)
            ->whereIn('status', ['searching', 'offered', 'accepted', 'arrived'])
            ->first();

        if (!$ride) {
            return $this->errorResponse('Ride cancel nahi ho sakti.', 400);
        }

        DB::transaction(function () use ($ride, $request) {
            $ride->update([
                'status' => 'cancelled_by_passenger',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->input('reason'),
            ]);

            // Expire all pending offers
            RideOffer::where('ride_id', $ride->id)
                ->whereNull('response')
                ->update([
                    'response' => 'expired',
                    'responded_at' => now(),
                ]);

            // If rider assigned, make them available again
            if ($ride->rider_id) {
                $ride->rider->update(['is_available' => true]);
            }
        });

        return $this->successResponse([
            'ride' => $ride->fresh(),
        ], 'Ride cancel ho gayi.');
    }

    // ═══════════════════════════════════════════════════════════════
    // RATE RIDE
    // POST /api/passenger/rides/{id}/rate
    // ═══════════════════════════════════════════════════════════════
    public function rate(Request $request, string $id): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $passenger = $user->passenger;

        if (!$passenger) {
            return $this->errorResponse('Passenger profile nahi hai.', 403);
        }

        $ride = Ride::where('id', $id)
            ->where('passenger_id', $passenger->id)
            ->where('status', 'completed')
            ->first();

        if (!$ride) {
            return $this->errorResponse('Ride rate nahi ho sakti.', 400);
        }

        if (!is_null($ride->passenger_rating)) {
            return $this->errorResponse('Aap already rate kar chuke hain.', 400);
        }

        $ride->update([
            'passenger_rating' => $request->input('rating'),
            'passenger_comment' => $request->input('comment'),
        ]);

        // Update rider's average rating
        if ($ride->rider) {
            $avgRating = Ride::where('rider_id', $ride->rider_id)
                ->whereNotNull('passenger_rating')
                ->avg('passenger_rating');

            $ride->rider->update(['rating' => round($avgRating, 1)]);
        }

        return $this->successResponse([
            'ride' => $ride->fresh(),
        ], 'Rating submit ho gayi. Shukriya!');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET RIDE HISTORY
    // GET /api/passenger/rides
    // ═══════════════════════════════════════════════════════════════
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $passenger = $user->passenger;

        if (!$passenger) {
            return $this->errorResponse('Passenger profile nahi hai.', 403);
        }

        $rides = Ride::where('passenger_id', $passenger->id)
            ->whereIn('status', ['completed', 'cancelled_by_passenger', 'cancelled_by_rider', 'no_rider_found'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->successResponse([
            'rides' => $rides,
        ], 'Ride history');
    }

    // ═══════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function getBaseFare(string $vehicleType): float
    {
        return match($vehicleType) {
            'bike' => 30,
            'rickshaw' => 50,
            'car' => 100,
            'car_ac' => 150,
            default => 50,
        };
    }

    private function getPerKmRate(string $vehicleType): float
    {
        return match($vehicleType) {
            'bike' => 10,
            'rickshaw' => 15,
            'car' => 25,
            'car_ac' => 35,
            default => 15,
        };
    }

    private function encodeGeohash(float $lat, float $lng, int $precision = 12): string
    {
        $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
        $geohash = '';

        $minLat = -90; $maxLat = 90;
        $minLng = -180; $maxLng = 180;
        $bit = 0; $ch = 0; $even = true;

        while (strlen($geohash) < $precision) {
            if ($even) {
                $mid = ($minLng + $maxLng) / 2;
                if ($lng >= $mid) {
                    $ch |= (1 << (4 - $bit));
                    $minLng = $mid;
                } else {
                    $maxLng = $mid;
                }
            } else {
                $mid = ($minLat + $maxLat) / 2;
                if ($lat >= $mid) {
                    $ch |= (1 << (4 - $bit));
                    $minLat = $mid;
                } else {
                    $maxLat = $mid;
                }
            }

            $even = !$even;
            if ($bit < 4) {
                $bit++;
            } else {
                $geohash .= $base32[$ch];
                $bit = 0;
                $ch = 0;
            }
        }

        return $geohash;
    }

    private function findNearbyRiders(float $lat, float $lng, string $vehicleType): array
    {
        $riders = Rider::where('is_online', true)
            ->where('is_available', true)
            ->where('verification_status', 'approved')
            ->where('vehicle_type', $vehicleType)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->where('location_updated_at', '>', now()->subMinutes(5))
            ->get();

        $nearby = [];
        foreach ($riders as $rider) {
            $distance = $this->calculateDistance(
                $lat, $lng,
                (float)$rider->current_lat,
                (float)$rider->current_lng
            );

            if ($distance <= 5) {
                $nearby[] = [
                    'rider' => $rider,
                    'distance' => $distance,
                ];
            }
        }

        usort($nearby, fn($a, $b) => $a['distance'] <=> $b['distance']);

        return array_slice(array_column($nearby, 'rider'), 0, 5);
    }
}