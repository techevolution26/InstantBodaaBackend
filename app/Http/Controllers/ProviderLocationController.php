<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProviderLocation;

class ProviderLocationController extends Controller {
    //

    public function updateLocation( Request $request ) {
        $user = $request->user();
        if ( ! $user->is_provider ) {
            return response()->json( [ 'message'=>'Forbidden' ], 403 );
        }

        $data = $request->validate( [
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'online'      => 'required|boolean',
            'recorded_at' => 'nullable|date_format:Y-m-d H:i:s',
        ] );

        // upsert their location row
        $loc = ProviderLocation::updateOrCreate(
            [ 'provider_id' => $user->id ],
            $data
        );

        return response()->json( $loc );
    }
}
