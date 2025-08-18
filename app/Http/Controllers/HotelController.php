<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\AmadeusService;
use App\Models\HotelSearchLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;


class HotelController extends Controller
{
    protected $amadeusService;

    public function __construct(AmadeusService $amadeusService)
    {
        $this->amadeusService = $amadeusService;
    }

    public function searchHotels(Request $request)
    {
        $allowedParams = [
            'cityCode' => ['required', 'string', 'size:3'],
            'radius' => ['integer', 'min:1'],
            'radiusUnit' => ['string', 'in:KM,MI'],
            'chainCodes' => ['array'],
            'amenities' => ['string'],
            'ratings' => ['integer', 'min:1', 'max:5'],
        ];

        $input = $request->only(array_keys($allowedParams));

        $rules = [];
        foreach ($input as $key => $value) {
            $rules[$key] = $allowedParams[$key];
        }

        $validated = $request->validate($rules);

        HotelSearchLog::create([
            'user_id' => Auth::id(),
            'search_type' => 'city',
            'params' => json_encode($validated),
        ]);

        $cacheKey = 'hotels_city_' . md5(json_encode($validated));
        $results = Cache::remember($cacheKey, 60 * 5, function () use ($validated) {
            return $this->amadeusService->searchHotels($validated);
        });

        return response()->json($results);
    }

    public function getHotelsByIds(Request $request)
    {
        $request->validate([
            'hotelIds' => 'required|string',
        ]);

        $params = [
            'hotelIds' => $request->hotelIds
        ];

        HotelSearchLog::create([
            'user_id' => Auth::id(),
            'search_type' => 'hotelId',
            'params' => json_encode($params),
        ]);
        $cacheKey = 'hotels_ids_' . md5(json_encode($params));
        $results = Cache::remember($cacheKey, 60 * 5, function () use ($params) {
            return $this->amadeusService->getHotelsByIds($params);
        });

        return response()->json($results);
    }
}
