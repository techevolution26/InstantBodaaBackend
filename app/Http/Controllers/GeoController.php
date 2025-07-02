<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AddressCache;

class GeoController extends Controller {

    /**
    * Reverse geocode a latitude/longitude pair to an address.
    *
    * @param Request $request
    * @return \Illuminate\Http\JsonResponse
    */

    public function reverse( Request $request ) {
        $lat = $request->query( 'lat' );
        $lon = $request->query( 'lon' );

        if ( ! $lat || ! $lon ) {
            return response()->json( [ 'error' => 'Missing lat or lon' ], 400 );
        }

        // Normalize to fixed precision strings
        $latKey = number_format( ( float )$lat, 6, '.', '' );
        $lonKey = number_format( ( float )$lon, 6, '.', '' );

        //Try to fetch from our cache table
        $cache = AddressCache::where( 'lat', $latKey )
        ->where( 'lon', $lonKey )
        ->first();

        if ( $cache ) {
            return response()->json( [
                'display_name' => $cache->formatted,
                'components'   => $cache->components,
                'cached'       => true,
            ] );
        }

        //If not cached, hit OpenCage
        try {
            $response = Http::timeout( 10 )
            ->get( 'https://api.opencagedata.com/geocode/v1/json', [
                'q'              => "$lat,$lon",
                'key'            => env( 'OPENCAGE_API_KEY' ),
                'language'       => 'en',
                'no_annotations' => 1,
            ] );

            $json = $response->json();

            if ( empty( $json[ 'results' ][ 0 ][ 'formatted' ] ) ) {
                return response()->json( [ 'error' => 'No address found' ], 404 );
            }

            $result      = $json[ 'results' ][ 0 ];
            $formatted   = $result[ 'formatted' ];
            $components  = $result[ 'components' ] ?? null;

            // 3 ) Store in our cache table
            AddressCache::create( [
                'lat'       => $latKey,
                'lon'       => $lonKey,
                'formatted' => $formatted,
                'components'=> $components,
            ] );

            // 4 ) Return to client
            return response()->json( [
                'display_name' => $formatted,
                'components'   => $components,
                'cached'       => false,
            ] );
        } catch ( \Exception $e ) {
            return response()->json( [
                'error'   => 'Geocoding failed',
                'details' => $e->getMessage(),
            ], 500 );
        }
    }

}
