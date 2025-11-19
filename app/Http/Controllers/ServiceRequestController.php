<?php

namespace App\Http\Controllers;

use App\Models\ProviderLocation;
use App\Models\ServiceRequest;
use App\Notifications\JobRequested;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ServiceRequestController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request, ServiceRequest $ride)
    {
        $user = $request->user();

        // rider may only view their own request
        if (! $user->is_provider && $ride->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        // provider may only view requests assigned to them
        if ($user->is_provider && $ride->provider_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        // eager load relations
        return response()->json(
            $ride->load(['user', 'provider'])
        );
    }

    // GET /api/rides
    public function index(Request $request)
    {
        $user = $request->user();
        $inputStatus = $request->query('status');

        // Maping UI filters to DB status
        $statusMap = [
            'incoming' => 'pending',
            'assigned' => 'assigned',
            'completed' => 'completed',
        ];
        $status = $statusMap[$inputStatus] ?? null;

        $query = ServiceRequest::with(['user', 'provider'])
            ->when($user->is_provider, function ($q) use ($user, $status) {
                $q->where('provider_id', $user->id);
                // Only apply status if present
                if ($status) {
                    $q->where('status', $status);
                }
            })
            ->when(! $user->is_provider, function ($q) use ($user, $status) {
                $q->where('user_id', $user->id);
                if ($status) {
                    $q->where('status', $status);
                }
            })
            ->latest();

        $paginated = $query->paginate(20);

        return response()->json([
            'data' => $paginated->items(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    // POST /api/rides

    public function requestRide(Request $request)
    {
        $data = $request->validate([
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'dropoff_lat' => 'required|numeric',
            'dropoff_lng' => 'required|numeric',
            'type' => 'nullable|string',
        ]);

        // compute distance/eta/fare
        $calc = $this->calculateFare([
            'pickup_lat' => $data['pickup_lat'],
            'pickup_lng' => $data['pickup_lng'],
            'dropoff_lat' => $data['dropoff_lat'],
            'dropoff_lng' => $data['dropoff_lng'],
        ]);

        // Find nearest online provider (you may want to filter by online flag)
        $nearest = ProviderLocation::selectRaw('
                provider_id,
                ST_Distance_Sphere(
                  POINT(longitude, latitude),
                  POINT(?, ?)
                ) AS distance_m
            ', [$data['pickup_lng'], $data['pickup_lat']])
            ->orderBy('distance_m')
            ->first();

        if (! $nearest) {
            return response()->json(['message' => 'No providers nearby'], 404);
        }

        // Create request and persist distance/eta/fare
        $ride = ServiceRequest::create([
            'user_id' => $request->user()->id,
            'provider_id' => $nearest->provider_id,
            'pickup_lat' => $data['pickup_lat'],
            'pickup_lng' => $data['pickup_lng'],
            'dropoff_lat' => $data['dropoff_lat'],
            'dropoff_lng' => $data['dropoff_lng'],
            'distance_km' => $calc['distance_km'],
            'eta_minutes' => $calc['eta_minutes'],
            'fare_estimate' => $calc['fare'],
            'type' => $data['type'] ?? 'ride',
        ]);

        // Save user's last-known location (optional, privacy considerations)
        $user = $request->user();
        if ($user) {
            $user->last_lat = $data['pickup_lat'];
            $user->last_lng = $data['pickup_lng'];
            $user->last_location_at = now();
            $user->save();
        }

        // Notify provider (assuming provider relation returns a User / Notifiable)
        if ($ride->provider) {
            Notification::send($ride->provider, new JobRequested($ride));
        }

        return response()->json($ride, 201);
    }

    // PATCH /api/rides/
    public function updateStatus(Request $request, ServiceRequest $ride)
    {

        $data = $request->validate([
            'status' => 'required|in:assigned,in_progress,completed,cancelled',
        ]);

        $user = $request->user();

        // If accepting, claim the ride
        if (
            // rider updating their own ride after assignment
            ($user->id === $ride->user_id) ||
            // provider accepting a new ride
            ($user->is_provider && $ride->status === 'pending') ||
            // provider updating an already assigned ride
            ($user->is_provider && $ride->provider_id === $user->id)
        ) {
            // permitted
        } else {
            abort(403, 'This action is unauthorized.');
        }

        // Set status & timestamps
        $ride->status = $data['status'];

        if ($ride->status === 'in_progress') {
            $ride->started_at = now();
        }

        if ($ride->status === 'completed') {
            $ride->completed_at = now();
            $ride->fare_actual = $ride->fare_estimate;
        }

        $ride->save();

        // Notify the other participant
        $other = $user->id === $ride->user_id
        ? $ride->provider
        : $ride->user;

        $other->notify(new \App\Notifications\RideStatusUpdated($ride));

        return response()->json($ride);
    }

    protected function calculateFare(array $coords): array
    {
        // config values (move into config/rates.php or env)
        $baseFare = (float) config('rates.base_fare', 40.0);       // KES base
        $perKm = (float) config('rates.per_km', 30.0);            // KES / km
        $perMin = (float) config('rates.per_min', 5.0);           // KES / minute
        $avgSpeedKph = (float) config('rates.avg_speed_kph', 30.0); // city average
        $fuelPrice = (float) config('rates.fuel_price', 200.0);   // KES / litre
        $fuelEfficiencyKmPerL = (float) config('rates.fuel_eff_km_per_l', 10.0); // km per litre

        // haversine distance (km)
        $lat1 = (float) $coords['pickup_lat'];
        $lon1 = (float) $coords['pickup_lng'];
        $lat2 = (float) $coords['dropoff_lat'];
        $lon2 = (float) $coords['dropoff_lng'];

        $distance_km = $this->haversineKm($lat1, $lon1, $lat2, $lon2);

        // ETA estimate (minutes)
        $eta_hours = max(0.05, $distance_km / max(1, $avgSpeedKph)); // avoid divide by zero
        $eta_minutes = (int) max(1, round($eta_hours * 60));

        // fuel cost estimate (KES)
        $fuel_cost = ($distance_km / max(0.1, $fuelEfficiencyKmPerL)) * $fuelPrice;

        // total fare: base + per_km + per_min + fuel share (you may choose to add a margin)
        $fare = $baseFare + ($perKm * $distance_km) + ($perMin * $eta_minutes) + ($fuel_cost * 0.2);
        // we multiply fuel_cost by 0.2 to add only a portion (driver pays fuel but app may include portion)

        $fare = round(max(1, $fare), 2);

        return [
            'distance_km' => round($distance_km, 3),
            'eta_minutes' => $eta_minutes,
            'fare' => $fare,
        ];
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }
}
