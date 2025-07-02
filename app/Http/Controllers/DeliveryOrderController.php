<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Http\Request;

class DeliveryOrderController extends Controller {
    // GET /api/deliveries

    public function index( Request $request ) {
        $user = $request->user();

        $query = Delivery::with( [ 'user', 'provider.user' ] )
        ->when( $user->is_provider, fn( $q ) => $q->where( 'provider_id', $user->id ) )
        ->when( ! $user->is_provider, fn( $q ) => $q->where( 'user_id', $user->id ) );

        return response()->json( $query->latest()->paginate( 20 ) );
    }

    // POST /api/deliveries

    public function create( Request $request ) {
        $data = $request->validate( [
            'pickup_lat'   => 'required|numeric',
            'pickup_lng'   => 'required|numeric',
            'dropoff_lat'  => 'required|numeric',
            'dropoff_lng'  => 'required|numeric',
            'package_desc' => 'nullable|string',
        ] );

        $delivery = Delivery::create( [
            'user_id'     => $request->user()->id,
            'pickup_lat'  => $data[ 'pickup_lat' ],
            'pickup_lng'  => $data[ 'pickup_lng' ],
            'dropoff_lat' => $data[ 'dropoff_lat' ],
            'dropoff_lng' => $data[ 'dropoff_lng' ],
            'fee_estimate'=> $this->calculateFee( $data ),
        ] );

        // similar notify nearest provider…

        return response()->json( $delivery, 201 );
    }

    // PATCH /api/deliveries/{id}

    public function updateStatus( Request $request, Delivery $delivery ) {
        $data = $request->validate( [
            'status' => 'required|in:assigned,in_transit,delivered,cancelled',
        ] );

        $delivery->status = $data[ 'status' ];
        if ( $data[ 'status' ] === 'delivered' ) {
            $delivery->delivered_at = now();
            $delivery->fee_actual   = $delivery->fee_estimate;
        }
        $delivery->save();

        // notify other party…
        return response()->json( $delivery );
    }

    protected function calculateFee( array $coords ): float {
        // Example fee calculation based on distance ( replace with my logic latter )
        $distance = sqrt(
            pow( $coords[ 'pickup_lat' ] - $coords[ 'dropoff_lat' ], 2 ) +
            pow( $coords[ 'pickup_lng' ] - $coords[ 'dropoff_lng' ], 2 )
        );
        $baseFee = 500;
        // base fee in my currency
        $perKmRate = 200;
        // per km rate

        return $baseFee + ( $perKmRate * $distance );
    }
}
