<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\BaseController;
use App\Models\Ride;
use App\Models\RideOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RideController extends BaseController
{
    // ═══════════════════════════════════════════════════════════════
    // GET PENDING RIDE OFFERS (For Rider)
    // GET /api/rider/rides/offers
    // ═══════════════════════════════════════════════════════════════
    public function offers(Request $request): JsonResponse
    {
        $user = $request->user();
        $rider = $user->rider;
    dd($user,$rider);
        if (!$rider || !$rider->is_online) {
            return $this->errorResponse('Pehle Online ho jayein.', 403);
        }

        if (!$rider->is_available) {
            return $this->errorResponse('Aapke paas already active ride hai.', 400);
        }

        // Get pending offers for this rider
        $offers = RideOffer::where('rider_id', $rider->id)
            ->whereNull('response')
            ->where('expires_at', '>', now())
            ->with('ride')
            ->get();

        return $this->successResponse([
            'offers' => $offers,
            'count' => $offers->count(),
        ], 'Ride offers');
    }

    // ═══════════════════════════════════════════════════════════════
    // ACCEPT RIDE OFFER
    // POST /api/rider/rides/offers/{offer_id}/accept
    // ═══════════════════════════════════════════════════════════════
    public function acceptOffer(Request $request, string $offerId): JsonResponse
    {
        $user = $request->user();
        $rider = $user->rider;

        $offer = RideOffer::where('id', $offerId)
            ->where('rider_id', $rider->id)
            ->whereNull('response')
            ->where('expires_at', '>', now())
            ->first();

        if (!$offer) {
            return $this->errorResponse('Offer expired ya invalid hai.', 404);
        }

        $ride = $offer->ride;

        if ($ride->status !== 'offered') {
            return $this->errorResponse('Ride already taken.', 400);
        }

        // Check if rider already has active ride
        $activeRide = Ride::where('rider_id', $rider->id)
            ->whereIn('status', ['accepted', 'arrived', 'started'])
            ->first();

        if ($activeRide) {
            return $this->errorResponse('Aapke paas already active ride hai.', 400);
        }

        DB::transaction(function () use ($ride, $rider, $offer) {
            // Update ride
            $ride->update([
                'rider_id' => $rider->id,
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            // Update offer
            $offer->update([
                'response' => 'accepted',
                'responded_at' => now(),
            ]);

            // Reject other offers for this ride
            RideOffer::where('ride_id', $ride->id)
                ->where('id', '!=', $offer->id)
                ->whereNull('response')
                ->update([
                    'response' => 'expired',
                    'responded_at' => now(),
                ]);

            // Update rider
            $rider->update([
                'is_available' => false,
            ]);
        });

        return $this->successResponse([
            'ride' => $ride->fresh()->load('passenger'),
        ], 'Ride accept ho gayi! Customer ke paas jayein.');
    }

    // ═══════════════════════════════════════════════════════════════
    // DECLINE RIDE OFFER
    // POST /api/rider/rides/offers/{offer_id}/decline
    // ═══════════════════════════════════════════════════════════════
    public function declineOffer(Request $request, string $offerId): JsonResponse
    {
        $user = $request->user();
        $rider = $user->rider;

        $offer = RideOffer::where('id', $offerId)
            ->where('rider_id', $rider->id)
            ->whereNull('response')
            ->first();

        if (!$offer) {
            return $this->errorResponse('Offer nahi mili.', 404);
        }

        $offer->update([
            'response' => 'declined',
            'responded_at' => now(),
        ]);

        return $this->successResponse([], 'Offer decline ho gayi.');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET CURRENT ACTIVE RIDE
    // GET /api/rider/rides/current
    // ═══════════════════════════════════════════════════════════════
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $rider = $user->rider;

        $ride = Ride::where('rider_id', $rider->id)
            ->whereIn('status', ['accepted', 'arrived', 'started'])
            ->with('passenger.user')
            ->first();

        if (!$ride) {
            return $this->successResponse([
                'has_active_ride' => false,
            ], 'Koi active ride nahi hai.');
        }

        return $this->successResponse([
            'has_active_ride' => true,
            'ride' => $ride,
        ], 'Active ride details');
    }

    // ═══════════════════════════════════════════════════════════════
    // UPDATE RIDE STATUS
    // POST /api/rider/rides/{id}/status
    // Body: { "status": "arrived|started|completed", "lat": 31.52, "lng": 74.35 }
    // ═══════════════════════════════════════════════════════════════
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:arrived,started,completed'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $rider = $user->rider;

        $ride = Ride::where('id', $id)
            ->where('rider_id', $rider->id)
            ->first();

        if (!$ride) {
            return $this->errorResponse('Ride nahi mili.', 404);
        }

        $newStatus = $request->input('status');
        $updateData = ['status' => $newStatus];

        switch ($newStatus) {
            case 'arrived':
                $updateData['arrived_at'] = now();
                $message = 'Aap pickup location pe pahunch gaye hain.';
                break;

            case 'started':
                $updateData['started_at'] = now();
                $message = 'Ride start ho gayi.';
                break;

            case 'completed':
                $updateData['completed_at'] = now();
                $message = 'Ride complete! Shukriya.';
                
                // Calculate rider earning
                $riderEarning = $ride->riderEarning();
                
                // Update rider stats
                $rider->increment('total_rides');
                $rider->increment('total_earnings', $riderEarning);
                $rider->update(['is_available' => true]);
                break;
        }

        // Update location if provided
        if ($request->has('lat') && $request->has('lng')) {
            $rider->update([
                'current_lat' => $request->input('lat'),
                'current_lng' => $request->input('lng'),
                'location_updated_at' => now(),
            ]);
        }

        $ride->update($updateData);

        return $this->successResponse([
            'ride' => $ride->fresh(),
            'rider_earning' => $newStatus === 'completed' ? $ride->riderEarning() : null,
        ], $message);
    }

    // ═══════════════════════════════════════════════════════════════
    // CANCEL RIDE (by Rider)
    // POST /api/rider/rides/{id}/cancel
    // Body: { "reason": "..." }
    // ═══════════════════════════════════════════════════════════════
    public function cancel(Request $request, string $id): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $rider = $user->rider;

        $ride = Ride::where('id', $id)
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['accepted', 'arrived'])
            ->first();

        if (!$ride) {
            return $this->errorResponse('Ride cancel nahi ho sakti.', 400);
        }

        $ride->update([
            'status' => 'cancelled_by_rider',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
        ]);

        $rider->increment('total_cancelled');
        $rider->update(['is_available' => true]);

        return $this->successResponse([
            'ride' => $ride->fresh(),
        ], 'Ride cancel ho gayi.');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET RIDE HISTORY
    // GET /api/rider/rides/history
    // ═══════════════════════════════════════════════════════════════
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $rider = $user->rider;

        $rides = Ride::where('rider_id', $rider->id)
            ->whereIn('status', ['completed', 'cancelled_by_rider', 'cancelled_by_passenger'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->successResponse([
            'rides' => $rides,
        ], 'Ride history');
    }
}