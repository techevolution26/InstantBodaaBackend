<?php

namespace App\Http\Controllers;

use App\Models\AddressCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OverpassController extends Controller
{
    /**
     * GET /api/nearby?lat=...&lon=...&radius=1500&types=hotel,hospital,supermarket
     *
     * This proxies a small Overpass query and caches discovered POIs into address_caches:
     * - keyed by normalized lat/lon (6 decimals)
     * - formatted => name (poi name)
     * - components => json with category, osm_type/id, source=overpass
     */
    public function nearby(Request $request)
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');
        $radius = (int) ($request->query('radius', 1500));
        $types = $request->query('types', 'hotel,hospital,supermarket');
        $typesArr = array_filter(array_map('trim', explode(',', $types)));

        if (! is_numeric($lat) || ! is_numeric($lon)) {
            return response()->json(['message' => 'Missing lat/lon'], 400);
        }

        // map friendly types -> Overpass tags
        $tagMap = [
            'hotel' => 'tourism=hotel',
            'hospital' => 'amenity=hospital',
            'marketplace' => 'amenity=marketplace',
            'supermarket' => 'shop=supermarket',
            'restaurant' => 'amenity=restaurant',
            'pharmacy' => 'amenity=pharmacy',
        ];

        $filters = [];
        foreach ($typesArr as $t) {
            if (isset($tagMap[$t])) {
                $filters[] = $tagMap[$t];
            }
        }
        if (empty($filters)) {
            return response()->json(['items' => []], 200);
        }

        // Build Overpass QL: search nodes and ways (centroid) within radius
        $qlParts = [];
        foreach ($filters as $f) {
            $qlParts[] = "node[$f](around:$radius,$lat,$lon);";
            $qlParts[] = "way[$f](around:$radius,$lat,$lon);";
            $qlParts[] = "relation[$f](around:$radius,$lat,$lon);";
        }
        $ql = '[out:json][timeout:20];('.implode('', $qlParts).');out center 50;';

        try {
            $resp = Http::timeout(15)
                ->withHeaders(['Accept' => 'application/json'])
                ->post('https://overpass-api.de/api/interpreter', ['data' => $ql]);

            if (! $resp->ok()) {
                Log::warning('overpass-failed', ['status' => $resp->status()]);

                return response()->json(['items' => []], 200);
            }

            $json = $resp->json();

            $items = [];
            if (! empty($json['elements'])) {
                foreach ($json['elements'] as $el) {
                    // coordinates: nodes have lat/lon; ways/relations have center
                    $latc = $el['lat'] ?? ($el['center']['lat'] ?? null);
                    $lonc = $el['lon'] ?? ($el['center']['lon'] ?? null);
                    $name = $el['tags']['name'] ?? null;
                    $category = null;
                    foreach ($el['tags'] ?? [] as $k => $v) {
                        if (strpos($k, 'amenity') === 0 || strpos($k, 'shop') === 0 || strpos($k, 'tourism') === 0) {
                            $category = "$k=$v";
                            break;
                        }
                    }
                    if ($latc && $lonc && $name) {
                        $d = $this->haversineMeters((float) $lat, (float) $lon, (float) $latc, (float) $lonc);
                        $item = [
                            'id' => "{$el['type']}_{$el['id']}",
                            'name' => $name,
                            'category' => $category ?? 'poi',
                            'lat' => $latc,
                            'lon' => $lonc,
                            'dist_m' => (int) $d,
                        ];

                        // Cache into address_caches table (only store real names, avoid overriding existing good formatted entries)
                        try {
                            $latKey = number_format((float) $latc, 6, '.', '');
                            $lonKey = number_format((float) $lonc, 6, '.', '');
                            $exists = AddressCache::where('lat', $latKey)->where('lon', $lonKey)->first();
                            if (! $exists) {
                                AddressCache::create([
                                    'lat' => $latKey,
                                    'lon' => $lonKey,
                                    'formatted' => $name,
                                    'components' => json_encode([
                                        'category' => $category ?? null,
                                        'osm_type' => $el['type'] ?? null,
                                        'osm_id' => $el['id'] ?? null,
                                        'source' => 'overpass',
                                    ]),
                                ]);
                            }
                        } catch (\Exception $e) {
                            // log and continue — caching failure shouldn't block result
                            Log::warning('addresscache-insert-failed', ['err' => $e->getMessage()]);
                        }

                        $items[] = $item;
                    }
                }
            }

            // sort by distance & return top 20
            usort($items, fn ($a, $b) => $a['dist_m'] <=> $b['dist_m']);

            return response()->json(['items' => array_slice($items, 0, 20)]);
        } catch (\Exception $e) {
            Log::error('overpass-exception', ['msg' => $e->getMessage()]);

            return response()->json(['items' => []], 200);
        }
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }
}
