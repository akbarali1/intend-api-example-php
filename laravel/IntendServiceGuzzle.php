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
 * INTEND_CALCULATE_URL=DOKEMENTATSIYADA BERILGANI
 * INTEND_URL=DOKEMENTATSIYADA BERILGANI
 * INTEND_ORDER_CHECK_URL=DOKEMENTATSIYADA BERILGANI
 */

class IntendServiceGuzzle
{
    protected string $member_auth_url;
    protected string $request_url;
    protected Client $client;
    protected string $secret_key;

    public function __construct()
    {
        $this->client          = new Client();
        $this->request_url     = 'https://pay.intend.uz/api/v1/external';
        $this->member_auth_url = 'https://pay.intend.uz/api/v1/external/member/auth';
        $this->secret_key      = env('INTEND_SECRET_KEY');
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

    public function getUserTokenNoPasswords($phone, $api_key, $status = false, $user_id = false)
    {
        if ($status === false && session()->has('intend_token')) {
            return session()->get('intend_token');
        }
        if ($status === true) {
            session()->forget('intend_token');
        }
        try {
            $response = $this->client->post($this->member_auth_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'api-key'      => $api_key,
                ],
                'json'    => [
                    'username' => $phone,
                    'token'    => hash('sha512', $phone.'$'.$this->secret_key),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        $json = json_decode($response->getBody()->getContents(), true);

        if (isset($json['data']['token'])) {
            session()->put('intend_token', $json['data']['token']);

            if ($user_id !== false) {
                return $json['data']['id'];
            }

            return $json['data']['token'];
        }

        return false;

    }

    public function userCheckLimit($token, $api_key)
    {
        if (session()->has('intend_limit')) {
            return session()->get('intend_limit') / 100;
        }

        $response = $this->client->get($this->request_url.'/member/limits', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer '.$token,
                'api-key'       => $api_key,
            ],
        ]);
        $json     = json_decode($response->getBody()->getContents(), true);
        if (isset($json['data']['limit'])) {
            session()->put('intend_limit', $json['data']['limit']);

            return $json['data']['limit'] / 100;
        }

        return false;
    }

    public function createIntendOrder($token, $api_key, $order, $product, $supplier_id, $duration = 12)
    {
        $user           = auth()->user();
        $product_price  = $product->price;
        $product_id     = $product->id;
        $product_name   = AdminService::trans($product->name);
        $product_weight = $product->weight;
        $product_sku    = $product->sku;
        $product_url    = AdminService::trans($product->slug);
        $used_bonus     = request()->input('used_bonus');
        if (!$used_bonus) {
            $used_bonus = 0;
        }
        $data = [
            'duration'     => $duration,
            'bonus_amount' => $used_bonus * 100,
            'order_id'     => $supplier_id,
            'redirect_url' => '',
            'products'     => [
                [
                    'id'       => $product_id,
                    'price'    => $product_price,
                    'quantity' => $order->product_quantity,
                    'name'     => $product_name,
                    'sku'      => $product_sku,
                    'url'      => route('web.product.slug', ['slug' => $product_url]),
                    'weight'   => $product_weight,
                ],
            ],
            'ref_id'       => $order->id,   // order id
            'ttl'          => 600,
        ];

        try {
            $response = $this->client->post($this->request_url.'/order/create', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer '.$token,
                    'api-key'       => $api_key,
                ],
                'json'    => $data,
            ]);
        } catch (\Exception $e) {
            $token_new = (new \App\Services\IntendService())->getUserToken($user->phone, $api_key, true);

            return (new IntendService())->createIntendOrder($token_new, $api_key, $order, $product, $supplier_id);
        }
        $json = (array)json_decode($response->getBody()->getContents(), true);
        if (isset($json['success']) && $json['success'] === true) {
            return $json['data'];
        }

        return false;
    }

    public function checkOrder($api_key, $token, $ref_id, $code)
    {
        $user = auth()->user();
        try {
            $response = $this->client->post($this->request_url.'/cheque/confirm', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'api-key'       => $api_key,
                    'Authorization' => 'Bearer '.$token,
                ],
                'json'    => [
                    'ref_id' => $ref_id,
                    'code'   => $code,
                ],
            ]);
        } catch (\Exception $e) {
            $token_new = (new \App\Services\IntendService())->getUserToken($user->phone, $api_key, true);

            return (new IntendService())->checkOrder($api_key, $token_new, $ref_id, $code);
        }

        $json = json_decode($response->getBody()->getContents(), true);

        if (isset($json['success']) && $json['success'] === true) {
            return [
                'success' => true,
                'data'    => [
                    'message' => 'Buyurtma qabul qilindi tez orada bog`lanamiz',
                ],
            ];
        }
        $ref_id   = $this->reSendSms($api_key, $token, $ref_id);
        $response = array_merge($json, ['ref_id' => $ref_id]);

        return $json;
    }

    public function reSendSms($api_key, $token, $ref_id)
    {
        $user = auth()->user();
        try {
            $response = $this->client->post($this->request_url.'/cheque/resend', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'api-key'       => $api_key,
                    'Authorization' => 'Bearer '.$token,
                ],
                'json'    => [
                    'ref_id' => $ref_id,
                ],
            ]);
        } catch (\Exception $e) {
            $token_new = (new \App\Services\IntendService())->getUserToken($user->phone, $api_key, true);

            return (new IntendService())->reSendSms($api_key, $token_new, $ref_id);
        }

        $json = json_decode($response->getBody()->getContents(), true);

        if (isset($json['success']) && $json['success'] === true) {
            return $json['data']['ref_id'];
        }

        return response()->json($json);
    }

    public function getUserToken($username, $api_key, $status = false, $user_id = false)
    {
        if ($status === false && session()->has('intend_token')) {
            return session()->get('intend_token');
        }
        if ($status === true) {
            session()->forget('intend_token');
        }
        try {
            $response = $this->client->post($this->member_auth_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'api-key'      => $api_key,
                ],
                'json'    => [
                    'username' => $username,
                    'token'    => hash('sha512', $username.'$'.$this->secret_key),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        $json = json_decode($response->getBody()->getContents(), true);

        if (isset($json['data']['token'])) {
            session()->put('intend_token', $json['data']['token']);

            if ($user_id !== false) {
                return $json['data']['id'];
            }

            return $json['data']['token'];
        }

        return false;
    }

}