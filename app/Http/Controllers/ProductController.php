<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProductController extends Controller
{
    /**
     * Uzum do'konidagi mahsulotlar va ularning qoldiqlarini olish
     */
    public function getUzumProducts(Request $request, $shopId)
    {
        $apiKey = auth()->user()->uzum_token;
        $baseUrl = config('services.uzum.url_v1') . "/v1/product/shop/{$shopId}";

        $queryParams = [
            'size'        => $request->query('size', 10),
            'page'        => $request->query('page', 0),
            'searchQuery' => $request->query('searchQuery'),
            'sortBy'      => $request->query('sortBy', 'DEFAULT'),
            'order'       => $request->query('order', 'ASC'),
            'productRank' => $request->query('productRank'),
            'filter'      => $request->query('filter', 'ALL'),
        ];

        $queryParams = array_filter($queryParams, fn($value) => !is_null($value));

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Accept'        => 'application/json',
            ])->get($baseUrl, $queryParams);

            if ($response->successful()) {



                return response()->json([
                    'status' => 'success',
                    'data'   => $response->json(),
                    'limits' => [
                        'remaining_day' => $response->header('x-ratelimit-remaining-per-day'),
                        'limit_day'     => $response->header('x-ratelimit-limit-per-day'),
                    ]
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Uzum API xatosi',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Yandex Market do'kon kataloqidagi mahsulotlarni olish
     */
    public function getYandexProducts(Request $request, $businessId)
    {
        $token = auth()->user()->yandex_token;
        
        // Yangi endpoint URL
        $baseUrl = config('services.yandex.url_v2') . "/businesses/{$businessId}/offer-mappings.json";

        // 1. Query parametrlar (Faqat limit, page_token va language)
        $queryParams = [
            'limit'      => $request->query('limit', 100),
            'page_token' => $request->query('page_token'),
            'language'   => $request->query('language', 'UZ'), // O'zbek tili uchun
        ];
        $queryParams = array_filter($queryParams, fn($value) => !is_null($value));

        // 2. Request Body (Filtrlar JSON formatida yuboriladi)
        // Hujjat bo'yicha offerIds berilsa, boshqa filtrlarni yuborib bo'lmaydi
        $body = [
            'offerIds'     => $request->input('offerIds'),     // Array bo'lishi kerak
            'cardStatuses' => $request->input('cardStatuses'), // Array: HAS_CARD_CAN_UPDATE va h.k.
            'categoryIds'  => $request->input('categoryIds'),  // Array
            'vendorNames'  => $request->input('vendorNames'),  // Array
            'tags'         => $request->input('tags'),         // Array
            'archived'     => $request->boolean('archived', false),
        ];
        // Null qiymatlarni tozalash
        $body = array_filter($body, fn($value) => !is_null($value));

        try {
            // 3. POST so'rovi yuborish
            $response = Http::withHeaders([
                'Api-Key'       => $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])->withQueryParameters($queryParams) // URL qismiga limit va page_token qo'shadi
            ->post($baseUrl, $body);             // Body qismiga filtrlarni qo'shadi

            if ($response->successful()) {
                $result = $response->json()['result'] ?? [];
                
                return response()->json([
                    'status' => 'success',
                    'source' => 'Yandex Business API',
                    'data'   => $result['offerMappings'] ?? [], // Yangi metodda "offerMappings" qaytadi
                    'pagination' => [
                        'nextPageToken' => $result['paging']['nextPageToken'] ?? null,
                        'prevPageToken' => $result['paging']['prevPageToken'] ?? null,
                    ]
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Yandex API xatosi',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function storeMapProducts(Request $request)
    {
        $request->validate([
            'uzum_sku_id' => 'required',
            'yandex_offer_id' => 'required',
        ]);

        $mapping = \App\Models\ProductMapping::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'uzum_sku_id' => $request->uzum_sku_id,
            ],
            [
                'yandex_offer_id' => $request->yandex_offer_id
            ]
        );

        return response()->json(['status' => 'success', 'mapping' => $mapping]);
    }

    public function getMappedProducts()
    {
        $mappings = \App\Models\ProductMapping::where('user_id', auth()->id())->get();

        return response()->json(['status' => 'success', 'mappings' => $mappings]);
    }

    public function getUnifiedProducts(Request $request, $uzumShopId, $yandexBusinessId)
    {
        $mappings = \App\Models\ProductMapping::where('user_id', auth()->id())
            ->get()
            ->keyBy('uzum_sku_id'); 
        
        $yandexLookups = $mappings->pluck('uzum_sku_id', 'yandex_offer_id');

        $uzumData = json_decode($this->getUzumProducts($request, $uzumShopId)->getContent(), true);
        $yandexData = json_decode($this->getYandexProducts($request, $yandexBusinessId)->getContent(), true);

        $finalProducts = [];

        if (isset($uzumData['data']['productList'])) {
            foreach ($uzumData['data']['productList'] as $product) {
                foreach ($product['skuList'] as $sku) {
                    $skuId = (string)$sku['skuId'];
                    
                    $isMapped = $mappings->has($skuId);
                    $linkedYandexId = $isMapped ? $mappings[$skuId]->yandex_offer_id : null;

                    $finalProducts['uzum'][$skuId] = [
                        'id' => $skuId,
                        'name' => $sku['productTitle'] . " (" . $sku['skuTitle'] . ")",
                        'barcode' => $sku['barcode'],
                        'price' => $sku['price'],
                        'image' => $sku['previewImage'],
                        'is_mapped' => $isMapped,
                        'mapped_to' => $linkedYandexId,
                        'platform' => 'uzum'
                    ];
                }
            }
        }

        if (isset($yandexData['data'])) {
            foreach ($yandexData['data'] as $item) {
                $offer = $item['offer'];
                $offerId = (string)$offer['offerId'];

                $linkedUzumId = $yandexLookups->get($offerId);

                $finalProducts['yandex'][$offerId] = [
                    'id' => $offerId,
                    'name' => $offer['name'],
                    'barcode' => $offer['barcodes'][0] ?? null,
                    'price' => $offer['basicPrice']['value'] ?? 0,
                    'image' => $offer['pictures'][0] ?? null,
                    'is_mapped' => !is_null($linkedUzumId),
                    'mapped_to' => $linkedUzumId,
                    'platform' => 'yandex'
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $finalProducts,
            'meta' => [
                'total_uzum' => count($finalProducts['uzum'] ?? []),
                'total_yandex' => count($finalProducts['yandex'] ?? []),
                'total_mapped' => $mappings->count()
            ]
        ]);
    }
}
