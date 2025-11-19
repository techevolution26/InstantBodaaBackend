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

   public function reverse(Request $request)
{
    $lat = $request->query('lat');
    $lon = $request->query('lon');

    if (!isset($lat, $lon) || !is_numeric($lat) || !is_numeric($lon)) {
        return response()->json(['error' => 'Missing or invalid lat/lon'], 400);
    }

    $latKey = number_format((float)$lat, 6, '.', '');
    $lonKey = number_format((float)$lon, 6, '.', '');

    // Cache lookup
    $cache = AddressCache::where('lat', $latKey)->where('lon', $lonKey)->first();
    if ($cache) {
        return response()->json([
            'display_name' => $cache->formatted,
            'components'   => $cache->components,
            'cached'       => true,
            'found'        => true,
        ], 200);
    }

    $apiKey = env('OPENCAGE_API_KEY');
    if (!$apiKey) {
        // dev-friendly: still return coords so UI behaves
        $fallback = "($latKey, $lonKey)";
        return response()->json([
            'display_name' => $fallback,
            'components' => null,
            'cached' => false,
            'found' => false,
            'error' => 'Geocoding key not configured'
        ], 200);
    }

    try {
        $response = Http::timeout(10)
            ->get('https://api.opencagedata.com/geocode/v1/json', [
                'q'              => "$lat,$lon",
                'key'            => $apiKey,
                'language'       => 'en',
                'no_annotations' => 1,
            ]);

        $json = $response->json();

        if (empty($json['results'][0]['formatted'])) {
            // No address — return coords as display_name with found:false but 200 OK
            $fallback = "($latKey, $lonKey)";
            AddressCache::create([
                'lat' => $latKey,
                'lon' => $lonKey,
                'formatted' => $fallback,
                'components' => null,
            ]);
            return response()->json([
                'display_name' => $fallback,
                'components'   => null,
                'cached'       => false,
                'found'        => false,
            ], 200);
        }

        $result = $json['results'][0];
        $formatted = $result['formatted'];
        $components = $result['components'] ?? null;

        AddressCache::create([
            'lat'       => $latKey,
            'lon'       => $lonKey,
            'formatted' => $formatted,
            'components'=> $components,
        ]);

        return response()->json([
            'display_name' => $formatted,
            'components'   => $components,
            'cached'       => false,
            'found'        => true,
        ], 200);
    } catch (\Exception $e) {
        $fallback = "($latKey, $lonKey)";
        // Return 200 with display_name fallback to avoid broken UI
        return response()->json([
            'display_name' => $fallback,
            'components' => null,
            'cached' => false,
            'found' => false,
            'error' => 'Geocoding failed',
            'details' => $e->getMessage(),
        ], 200);
    }
}


}
