<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\BaseController;
use App\Models\Ride;
use App\Models\RideEvent;
use App\Models\RideLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends BaseController
{
    // ═══════════════════════════════════════════════════════════════
    // BULK LOCATION UPDATE (Rider app sends every 5-10 seconds)
    // POST /api/rider/rides/{id}/locations
    // Body: {
    //   "locations": [
    //     {"lat": 31.5204, "lng": 74.3587, "accuracy": 5, "speed": 25, "heading": 90, "recorded_at": "2026-06-28T12:00:00Z"},
    //     ...
    //   ]
    // }
    // ═══════════════════════════════════════════════════════════════
    public function updateLocations(Request $request, string $rideId): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'locations' => ['required', 'array', 'min:1', 'max:50'], // Max 50 points per request
            'locations.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'locations.*.speed' => ['nullable', 'numeric', 'min:0'],
            'locations.*.heading' => ['nullable', 'numeric', 'between:0,360'],
            'locations.*.recorded_at' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $rider = $user->rider;

        $ride = Ride::where('id', $rideId)
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['accepted', 'arrived', 'started'])
            ->first();

        if (!$ride) {
            return $this->errorResponse('Active ride nahi mili.', 404);
        }

        $locations = $request->input('locations');
        $inserted = 0;

        foreach ($locations as $loc) {
            RideLocation::createLocation($rideId, $loc['lat'], $loc['lng'], [
                'accuracy' => $loc['accuracy'] ?? null,
                'speed' => $loc['speed'] ?? null,
                'heading' => $loc['heading'] ?? null,
                'source' => 'rider_app',
                'recorded_at' => $loc['recorded_at'],
            ]);
            $inserted++;
        }

        return $this->successResponse([
            'inserted' => $inserted,
            'ride_id' => $rideId,
        ], 'Locations saved.');
    }

    // ═══════════════════════════════════════════════════════════════
    // SINGLE LOCATION UPDATE (Quick update)
    // POST /api/rider/rides/{id}/location
    // ═══════════════════════════════════════════════════════════════
    public function updateLocation(Request $request, string $rideId): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $rider = $user->rider;

        $ride = Ride::where('id', $rideId)
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['accepted', 'arrived', 'started'])
            ->first();

        if (!$ride) {
            return $this->errorResponse('Active ride nahi mili.', 404);
        }

        RideLocation::createLocation($rideId, $request->input('lat'), $request->input('lng'), [
            'accuracy' => $request->input('accuracy'),
            'speed' => $request->input('speed'),
            'heading' => $request->input('heading'),
            'source' => 'rider_app',
        ]);

        return $this->successResponse([
            'ride_id' => $rideId,
            'lat' => $request->input('lat'),
            'lng' => $request->input('lng'),
        ], 'Location updated.');
    }
}