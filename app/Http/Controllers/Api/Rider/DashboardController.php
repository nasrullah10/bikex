<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\BaseController;
use App\Models\Ride;
use App\Models\RideOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    // ═══════════════════════════════════════════════════════════════
    // GET DASHBOARD STATS
    // GET /api/rider/dashboard
    // ═══════════════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rider = $user->rider;

        if (!$rider || $rider->verification_status !== 'approved') {
            return $this->errorResponse('Aapka account approved nahi hai.', 403);
        }

        $today = now()->startOfDay();
        
        // Today's completed rides
        $todayRides = Ride::where('rider_id', $rider->id)
            ->where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->get();

        $todayEarnings = $todayRides->sum(function ($ride) {
            return $ride->riderEarning();
        });

        // Weekly stats
        $weekStart = now()->startOfWeek();
        $weeklyRides = Ride::where('rider_id', $rider->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $weekStart)
            ->get();

        $weeklyEarnings = $weeklyRides->sum(function ($ride) {
            return $ride->riderEarning();
        });

        // Pending offers count
        $pendingOffers = RideOffer::where('rider_id', $rider->id)
            ->whereNull('response')
            ->where('expires_at', '>', now())
            ->count();

        // Check for active ride
        $activeRide = Ride::where('rider_id', $rider->id)
            ->whereIn('status', ['accepted', 'arrived', 'started'])
            ->first();

        return $this->successResponse([
            'rider' => [
                'id' => $rider->id,
                'name' => $user->name,
                'rating' => $rider->rating,
                'total_rides' => $rider->total_rides,
                'total_earnings' => $rider->total_earnings,
                'is_online' => $rider->is_online,
                'is_available' => $rider->is_available,
                'current_plan' => $rider->current_plan,
                'vehicle_type' => $rider->vehicle_type,
                'vehicle_number' => $rider->vehicle_number,
            ],
            'today' => [
                'earnings' => $todayEarnings,
                'rides_count' => $todayRides->count(),
                'online_hours' => $this->getOnlineHours($rider->id, $today),
            ],
            'weekly' => [
                'earnings' => $weeklyEarnings,
                'rides_count' => $weeklyRides->count(),
            ],
            'notifications' => [
                'pending_offers' => $pendingOffers,
                'has_active_ride' => !is_null($activeRide),
                'active_ride_id' => $activeRide?->id,
            ],
            'wallet' => [
                'balance' => $user->wallet_balance,
            ],
        ], 'Dashboard data');
    }

    // ═══════════════════════════════════════════════════════════════
    // TOGGLE ONLINE/OFFLINE
    // POST /api/rider/online
    // Body: { "is_online": true, "lat": 31.5204, "lng": 74.3587 }
    // ═══════════════════════════════════════════════════════════════
    public function toggleOnline(Request $request): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'is_online' => ['required', 'boolean'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $rider = $user->rider;

        if (!$rider || $rider->verification_status !== 'approved') {
            return $this->errorResponse('Account approved nahi hai.', 403);
        }

        $updateData = [
            'is_online' => $request->boolean('is_online'),
        ];

        // Only make available if going online AND no active ride
        if ($request->boolean('is_online')) {
            $hasActiveRide = Ride::where('rider_id', $rider->id)
                ->whereIn('status', ['accepted', 'arrived', 'started'])
                ->exists();

            $updateData['is_available'] = !$hasActiveRide;
        } else {
            $updateData['is_available'] = false;
        }

        if ($request->has('lat') && $request->has('lng')) {
            $updateData['current_lat'] = $request->input('lat');
            $updateData['current_lng'] = $request->input('lng');
            $updateData['location_updated_at'] = now();
        }

        $rider->update($updateData);

        $message = $request->boolean('is_online') 
            ? 'Aap Online ho gaye hain. Ab rides accept kar sakte hain.'
            : 'Aap Offline ho gaye hain.';

        return $this->successResponse([
            'is_online' => $rider->is_online,
            'is_available' => $rider->is_available,
        ], $message);
    }

    // ═══════════════════════════════════════════════════════════════
    // UPDATE LOCATION
    // POST /api/rider/location
    // Body: { "lat": 31.5204, "lng": 74.3587, "heading": 90 }
    // ═══════════════════════════════════════════════════════════════
    public function updateLocation(Request $request): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $rider = $user->rider;

        if (!$rider) {
            return $this->errorResponse('Rider not found.', 404);
        }

        $rider->update([
            'current_lat' => $request->input('lat'),
            'current_lng' => $request->input('lng'),
            'current_heading' => $request->input('heading'),
            'location_updated_at' => now(),
        ]);

        return $this->successResponse([
            'location_updated' => true,
            'timestamp' => now()->toIso8601String(),
        ], 'Location updated.');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET EARNINGS
    // GET /api/rider/earnings?period=today|week|month
    // ═══════════════════════════════════════════════════════════════
    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();
        $rider = $user->rider;
        $period = $request->input('period', 'today');

        $query = Ride::where('rider_id', $rider->id)
            ->where('status', 'completed');

        switch ($period) {
            case 'today':
                $query->whereDate('completed_at', now()->startOfDay());
                break;
            case 'week':
                $query->where('completed_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('completed_at', '>=', now()->startOfMonth());
                break;
        }

        $rides = $query->orderBy('completed_at', 'desc')
            ->paginate(20);

        $totalEarning = $rides->sum(function ($ride) {
            return $ride->riderEarning();
        });

        return $this->successResponse([
            'period' => $period,
            'total_earning' => $totalEarning,
            'rides_count' => $rides->count(),
            'rides' => $rides,
        ], 'Earnings data');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET WALLET
    // GET /api/rider/wallet
    // ═══════════════════════════════════════════════════════════════
    public function wallet(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'balance' => $user->wallet_balance,
            'transactions' => $user->transactions()
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
        ], 'Wallet data');
    }

    // ═══════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function getOnlineHours(string $riderId, $date): float
    {
        // TODO: Implement proper online hours tracking with a separate table
        // For now return dummy
        return 0.0;
    }
}