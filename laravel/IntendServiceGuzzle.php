<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Created by PhpStorm.
 * Filename: IntendServiceGuzzle.php
 * Project Name: intend-api-example-php
 * Author: Акбарали
 * Date: 27/06/2022
 * Time: 11:26
 * Github: https://github.com/akbarali1
 * Telegram: @akbar_aka
 * E-mail: me@akbarali.uz
 */
/*
 * Loyiha laravelda bo`lsa envga yozingiz kerak
 * INTEND_API_KEY=<INTEND_BERGAN_API_KEY>
 * INTEND_CALCULATE_URL=https://pay.intend.uz/api/v1/front/calculate-all
 * INTEND_URL=https://pay.intend.uz
 * INTEND_ORDER_CHECK_URL=https://pay.intend.uz/api/v1/external/order/check
 */

class IntendServiceGuzzle
{
    public function __construct()
    {
        $this->client      = new Client();
        $this->request_url = 'https://pay.intend.uz/api/v1/external';
        $this->secret_key  = env('INTEND_SECRET_KEY');
    }

    public function getPriceIntend($product_id, $product_price, $supplier_api_key, $per_month = false, $price_intend = false): array|int
    {
        $post_data[] = [
            'id'    => $product_id,
            'price' => $product_price * 100,
        ];
        try {
            $response = $this->client->post($this->request_url.'/calculate', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'api-key'       => $supplier_api_key,
                    "Cache-Control" => "no-cache",
                ],
                'json'    => $post_data,
            ]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        $json = collect(json_decode($response->getBody()->getContents(), true)['data']['items'] ?? []);

        //Agar faqat oylik narxini olmoqchi bo`lsangiz $per_month = true; yozing
        if ($per_month) {
            $per_month = $json->where('id', $product_id)->first()['prices']['0']['per_month'] ?? 0;

            return $per_month / 100;
        }

        // Faqat Intenddagi narxini o`zini olish kerak bo`lsa
        if ($price_intend) {
            $price_intend = $json->where('id', $product_id)->first()['prices']['0']['price'] ?? 0;

            return $price_intend / 100;
        }

        return $json->where('id', $product_id)->first();

    }




}