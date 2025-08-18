<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;

class AmadeusService
{
    private $clientId;
    private $clientSecret;
    private $baseUrl;

    public function __construct()
    {
        $this->clientId = env('AMADEUS_CLIENT_ID');
        $this->clientSecret = env('AMADEUS_CLIENT_SECRET');
        $this->baseUrl = env('AMADEUS_BASE_URL', 'https://test.api.amadeus.com');
    }

    private function getAccessToken()
    {
        $response = Http::asForm()->post("{$this->baseUrl}/v1/security/oauth2/token", [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to get Amadeus access token: ' . $response->body());
        }

        return $response->json()['access_token'] ?? null;
    }

    public function searchHotels(array $params = [])
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/v1/reference-data/locations/hotels/by-city", $params);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch hotels: ' . $response->body());
        }

        return $response->json();
    }

    public function getHotelsByIds(array $params = [])
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/v1/reference-data/locations/hotels/by-hotels", $params);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch hotel details: ' . $response->body());
        }

        return $response->json();
    }
}
