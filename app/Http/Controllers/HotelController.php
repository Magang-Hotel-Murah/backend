<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\AmadeusService;
use App\Models\HotelSearchLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

/**
 * @group Hotels
 */
class HotelController extends Controller
{
    protected $amadeusService;

    public function __construct(AmadeusService $amadeusService)
    {
        $this->amadeusService = $amadeusService;
    }

    public function getHotelsByCity(Request $request)
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
            return $this->amadeusService->getHotelsByCity($validated);
        });

        return response()->json($results);
    }

    public function getHotelsById(Request $request)
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
            return $this->amadeusService->getHotelsById($params);
        });

        return response()->json($results);
    }

    public function getMultiHotelOffers(Request $request)
    {
        $request->validate([
            'hotelIds' => 'required|string',
            'adults' => 'integer|min:1',
            'checkInDate' => 'date',
            'checkOutDate' => 'date|after_or_equal:checkInDate',
            'countryOfResidence' => 'nullable|string|max:2',
            'roomQuantity' => 'nullable|integer|min:1',
            'priceRange' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'paymentPolicy' => 'nullable|string|in:GUARANTEE, DEPOSIT, NONE|default:NONE',
            'boardType' => 'nullable|string|in:ROOM_ONLY, BREAKFAST, HALF_BOARD, FULL_BOARD, ALL_INCLUSIVE',
            'bestRateOnly' => 'nullable|boolean',

        ]);
        $params = $request->only([
            'hotelIds',
            'adults',
            'checkInDate',
            'checkOutDate',
            'countryOfResidence',
            'roomQuantity',
            'priceRange',
            'currency',
            'paymentPolicy',
            'boardType',
            'bestRateOnly'
        ]);

        $cacheKey = 'hotels_offers_' . md5(json_encode($params));
        $results = Cache::remember($cacheKey, 60 * 5, function () use ($params) {
            return $this->amadeusService->getMultiHotelOffers($params);
        });

        return response()->json($results);
    }

    public function getOfferPricing(Request $request, $offerId)
    {
        $params = ['offerId' => $offerId];

        $cacheKey = 'offer_pricing_' . md5(json_encode($params));
        $results = Cache::remember($cacheKey, 60 * 5, function () use ($params) {
            return $this->amadeusService->getOfferPricing($params);
        });

        return response()->json($results);
    }
}
