<?php

namespace App\Http\Controllers;

use App\Models\ServiceProvider;
use App\Models\ProviderLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProviderController extends Controller {

    // GET /api/providers/

    public function show( $id ) {
        $provider = ServiceProvider::with( 'user' )->where( 'user_id', $id )->firstOrFail();

        return response()->json( [
            'bike_model'            => $provider->bike_model,
            'plate_number'          => $provider->plate_number,
            'license_url'           => $provider->license_url,
            'insurance_url'         => $provider->insurance_url,
            'additional_image_urls' => $provider->additional_image_urls,
            //user‐level fields needed:
            'avatar_url'            => $provider->user->avatar_url,
            'dob'                   => $provider->user->dob,
            'blood_group'           => $provider->user->blood_group,
            'address'               => $provider->user->address,
        ] );
    }

    // GET /api/providers/nearby?lat = ..&lng = ..&radius = ..

    public function nearby( Request $request ) {
        $request->validate( [
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'nullable|numeric', // in meters
        ] );

        $lat    = $request->lat;
        $lng    = $request->lng;
        $radius = $request->radius ?? 1000;

        // MySQL ST_Distance_Sphere geo-query
        $nearby = ProviderLocation::selectRaw( "
                provider_id,
                ST_Distance_Sphere(
                  POINT(longitude, latitude),
                  POINT(?, ?)
                ) AS distance_m
            ", [ $lng, $lat ] )
        ->whereRaw( "ST_Distance_Sphere(
                  POINT(longitude, latitude),
                  POINT(?, ?)
                ) <= ?", [ $lng, $lat, $radius ] )
        ->orderBy( 'distance_m' )
        ->limit( 10 )
        ->get()
        ->pluck( 'provider_id' );

        $providers = ServiceProvider::with( 'user' )
        ->whereIn( 'user_id', $nearby )
        ->where( 'status', 'online' )
        ->get();

        return response()->json( $providers );
    }

    // POST/api/providers/

    public function uploadDocs( Request $request, $id ) {
        $provider = ServiceProvider::where( 'user_id', $id )->firstOrFail();

        $provider = ServiceProvider::firstOrCreate(
            [ 'user_id' => $id ],
            [ 'bike_model' => null, 'plate_number' => null ]
        );

        $request->validate( [
            'license'   => 'nullable|file|mimes:jpg,png,pdf|max:5120',
            'insurance' => 'nullable|file|mimes:jpg,png,pdf|max:5120',
            'images.*'  => 'nullable|image|max:5120',
        ] );

        // License
        if ( $request->hasFile( 'license' ) ) {
            if ( $provider->license_url ) {
                Storage::disk( 'public' )->delete( str_replace( '/storage/', '', $provider->license_url ) );
            }
            $path = $request->file( 'license' )->store( 'providers/licenses', 'public' );
            $provider->license_url = '/storage/' . $path;
        }

        // Insurance
        if ( $request->hasFile( 'insurance' ) ) {
            if ( $provider->insurance_url ) {
                Storage::disk( 'public' )->delete( str_replace( '/storage/', '', $provider->insurance_url ) );
            }
            $path = $request->file( 'insurance' )->store( 'providers/insurance', 'public' );
            $provider->insurance_url = '/storage/' . $path;
        }

        if ( $request->hasFile( 'images' ) ) {
            // delete all old extras
            if ( is_array( $provider->additional_image_urls ) ) {
                foreach ( $provider->additional_image_urls as $old ) {
                    Storage::disk( 'public' )->delete( str_replace( '/storage/', '', $old ) );
                }
            }
            // store new ones
            $urls = [];
            foreach ( $request->file( 'images' ) as $img ) {
                $path = $img->store( 'providers/extra', 'public' );
                $urls[] = '/storage/' . $path;
            }
            $provider->additional_image_urls = $urls;
        }

        $provider->save();

        return response()->json( $provider );

    }

    public function update( Request $request, $id ) {
        $provider = ServiceProvider::where( 'user_id', $id )->firstOrFail();

        $data = $request->validate( [
            'bike_model'   => 'sometimes|string|max:255',
            'plate_number' => 'sometimes|string|max:50',
        ] );

        $provider->update( $data );

        return response()->json( $provider );
    }
}
