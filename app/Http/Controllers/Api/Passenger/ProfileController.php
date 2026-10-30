<?php

namespace App\Http\Controllers\Api\Passenger;

use App\Http\Controllers\Api\BaseController;
use App\Models\Passenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileController extends BaseController
{
    // ═══════════════════════════════════════════════════════════════
    // GET PROFILE
    // GET /api/passenger/profile
    // ═══════════════════════════════════════════════════════════════
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->passenger) {
            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                ],
                'passenger' => null,
                'saved_places' => [],
            ], 'Profile data');
        }

        $passenger = $user->passenger;

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
            'passenger' => [
                'id' => $passenger->id,
                'total_rides' => $passenger->total_rides,
                'total_spent' => $passenger->total_spent,
            ],
            'saved_places' => $this->getSavedPlace($passenger),
        ], 'Profile data');
    }

    // ═══════════════════════════════════════════════════════════════
    // CREATE/UPDATE PROFILE
    // POST /api/passenger/profile
    // ═══════════════════════════════════════════════════════════════
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();

        // Update user
        $updateData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
        ];

        if ($request->hasFile('avatar')) {
            $updateData['avatar'] = $request->file('avatar')->store('passengers/avatars', 'public');
        }

        $user->update($updateData);

        // Create passenger record if not exists
        if (!$user->passenger) {
            Passenger::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
            ]);
        }
        // dd($user->passenger->id);
        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
            'passenger' => [
                'passenger_id' => $user->passenger->id,
                'total_rides' => $user->passenger->total_rides,
                'total_spent' => $user->passenger->total_spent,
            ],
        ], 'Profile update ho gayi.');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET SAVED PLACES
    // GET /api/passenger/saved-places
    // ═══════════════════════════════════════════════════════════════
    public function getSavedPlaces(Request $request): JsonResponse
    {
        $user = $request->user();
        $passenger = $user->passenger;

        if (!$passenger) {
            return $this->successResponse([
                'places' => [],
            ], 'No saved places');
        }

        return $this->successResponse([
            'places' => $this->getSavedPlace($passenger),
        ], 'Saved places');
    }

    // ═══════════════════════════════════════════════════════════════
    // SAVE PLACE
    // POST /api/passenger/saved-places
    // ═══════════════════════════════════════════════════════════════
    public function savePlace(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:home,work'],
            'address' => ['required', 'string', 'max:500'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $passenger = $user->passenger;

        if (!$passenger) {
            return $this->errorResponse('Pehle profile complete karein.', 403);
        }

        $type = $request->input('type');
        $updateData = [
            "{$type}_address" => $request->input('address'),
            "{$type}_lat" => $request->input('lat'),
            "{$type}_lng" => $request->input('lng'),
        ];

        $passenger->update($updateData);

        return $this->successResponse([
            'places' => $this->getSavedPlace($passenger),
        ], ucfirst($type) . ' location save ho gayi.');
    }

    // ═══════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function getSavedPlace(Passenger $passenger): array
    {
        $places = [];

        if ($passenger->home_address) {
            $places[] = [
                'type' => 'home',
                'label' => 'Home / گھر',
                'address' => $passenger->home_address,
                'lat' => $passenger->home_lat,
                'lng' => $passenger->home_lng,
            ];
        }

        if ($passenger->work_address) {
            $places[] = [
                'type' => 'work',
                'label' => 'Work / دفتر',
                'address' => $passenger->work_address,
                'lat' => $passenger->work_lat,
                'lng' => $passenger->work_lng,
            ];
        }

        return $places;
    }
}