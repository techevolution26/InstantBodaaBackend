<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\ProviderLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Notifications\JobRequested;
use App\Notifications\RideStatusUpdated;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ServiceRequestController extends Controller {
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
        $ride->load(['user','provider'])
    );
}

    // GET /api/rides
public function index(Request $request)
{
    $user   = $request->user();
    $inputStatus = $request->query('status');

    // Maping UI filters to DB status
    $statusMap = [
        'incoming'  => 'pending',
        'assigned'  => 'assigned',
        'completed' => 'completed',
    ];
    $status = $statusMap[$inputStatus] ?? null;

    $query = ServiceRequest::with(['user','provider'])
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
        'data'          => $paginated->items(),
        'total'         => $paginated->total(),
        'per_page'      => $paginated->perPage(),
        'current_page'  => $paginated->currentPage(),
        'last_page'     => $paginated->lastPage(),
    ]);
}

    // POST /api/rides

    public function requestRide( Request $request ) {
        $data = $request->validate( [
            'pickup_lat'   => 'required|numeric',
            'pickup_lng'   => 'required|numeric',
            'dropoff_lat'  => 'required|numeric',
            'dropoff_lng'  => 'required|numeric',
        ] );

        /** Find nearest online provider **/
        $nearest = ProviderLocation::selectRaw( "
                provider_id,
                ST_Distance_Sphere(
                  POINT(longitude, latitude),
                  POINT(?, ?)
                ) AS distance_m
            ", [ $data[ 'pickup_lng' ], $data[ 'pickup_lat' ] ] )
        ->orderBy( 'distance_m' )
        ->first();

        if ( ! $nearest ) {
            return response()->json( [ 'message' => 'No providers nearby' ], 404 );
        }

        /** Create request **/
        $ride = ServiceRequest::create( [
            'user_id'      => $request->user()->id,
            'provider_id'  => $nearest->provider_id,
            'pickup_lat'   => $data[ 'pickup_lat' ],
            'pickup_lng'   => $data[ 'pickup_lng' ],
            'dropoff_lat'  => $data[ 'dropoff_lat' ],
            'dropoff_lng'  => $data[ 'dropoff_lng' ],
            'fare_estimate'=> $this->calculateFare( $data ),
        ] );

        /** Notify provider **/
        $providerUser = $ride->provider;
        Notification::send( $providerUser, new JobRequested( $ride ) );

        return response()->json( $ride, 201 );
    }

    // PATCH /api/rides/
    public function updateStatus( Request $request, ServiceRequest $ride ) {

        $data = $request->validate( [
            'status' => 'required|in:assigned,in_progress,completed,cancelled',
        ] );

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
        $ride->status = $data[ 'status' ];

        if ( $ride->status === 'in_progress' ) {
            $ride->started_at = now();
        }

        if ( $ride->status === 'completed' ) {
            $ride->completed_at = now();
            $ride->fare_actual  = $ride->fare_estimate;
        }

        $ride->save();

        // Notify the other participant
        $other = $user->id === $ride->user_id
        ? $ride->provider
        : $ride->user;

        $other->notify( new \App\Notifications\RideStatusUpdated( $ride ) );

        return response()->json( $ride );
    }

    protected function calculateFare( array $coords ): float {
        // simple distance-based stub
        $dx = $coords[ 'dropoff_lat' ]  - $coords[ 'pickup_lat' ];
        $dy = $coords[ 'dropoff_lng' ]  - $coords[ 'pickup_lng' ];
        $dist = sqrt( $dx*$dx + $dy*$dy ) * 111;
        // rough km
        return round( $dist * 50, 2 );
        // e.g. KES 50/km
    }
}
