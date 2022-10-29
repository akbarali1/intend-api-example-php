<?php

namespace App\Services;

/**
 * Created by PhpStorm.
 * Filename: IntendService.php
 * User: Akbarali
 * Date: 17/01/2022
 * Time: 6:54 PM
 * Github: https://github.com/akbarali1
 * Telegram: @kbarali
 * E-mail: akbarali@webschool.uz
 */
/*
 * Loyiha laravelda bo`lsa envga yozingiz kerak
 * INTEND_API_KEY=<INTEND_BERGAN_API_KEY>
 * INTEND_CALCULATE_URL=DOKEMENTATSIYADA BERILGANI
 * INTEND_URL=DOKEMENTATSIYADA BERILGANI
 * INTEND_ORDER_CHECK_URL=DOKEMENTATSIYADA BERILGANI
 */

class IntendService
{
    public function calculateIntend($product)
    {
        /*
         * Array yoki Object kelishi kerak
         $product = [
            [
                'id' => 1,
                'price' => 100,
             ],
            [
                'id' => 2,
                'price' => 200,
            ],
            [
                'id' => 3,
                'price' => 300,
            ],
        ];
         * */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, env('INTEND_CALCULATE_URL'));
        curl_setopt($ch, CURLOPT_POST, 1);                //0 for a get request
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode(
                [
                    [
                        'api_key' => env('INTEND_API_KEY'),
                        'price'   => $product->price,
                        'id'      => $product->id,
                    ],
                ]
            )
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response_json = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response_json, true);

        return $response['data']['items'][0]['prices'][0];
    }

    /**
     * Bunga order_id ni yuborasiz
     */
    public function orderCheck($order_id): bool
    {
        if (!is_numeric($order_id)) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, env('INTEND_ORDER_CHECK_URL'));
        curl_setopt($ch, CURLOPT_POST, 1);                //0 for a get request
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['order_id' => $order_id]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'api-key: '.env('INTEND_API_KEY'),
        ]);
        $response_json = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response_json, true);

        if ($response['success'] === true) {
            return true;
        } else {
            return false;
        }

    }
}