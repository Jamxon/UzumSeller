<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;

class ShopsController extends Controller
{
    
    public function getUzumShops()
    {
        $apiKey = auth()->user()->uzum_token;
        $baseUrl = config('services.uzum.url_v1');
        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Accept' => '*/*',
            ])->get($baseUrl . '/v1/shops');

            if ($response->successful()) {
                $shops = $response->json();
                
                return response()->json([
                    'status' => 'success',
                    'data' => $shops
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API xatosi: ' . $response->status(),
                    'error_details' => $response->body()
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tizim xatosi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getYandexShops()
    {
        $apiKey = auth()->user()->yandex_token;
        $baseUrl = config('services.yandex.url_v2');
        
        $response = Http::withHeaders([
            'Api-Key' => $apiKey,
            'Accept' => 'application/json',
        ])->get($baseUrl . '/campaigns');

        if ($response->failed()) {
            return response()->json([
                'debug_info' => 'Kalitni tekshiring: ' . substr($apiKey, 0, 5) . '***',
                'yandex_response' => $response->json()
            ], $response->status());
        }

        return response()->json([
            'status' => 'success',
            'data' => $response->json()['campaigns'] ?? []
        ]);
    }
}
