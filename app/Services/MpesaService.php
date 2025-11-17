<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MpesaService
{
    protected function getAccessToken()
    {
        $res = Http::withBasicAuth(config('mpesa.consumer_key'), config('mpesa.consumer_secret'))
            ->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
        return $res->json()['access_token'];
    }

    public function stkPush($phone, $amount, $accountRef)
    {
        $token = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $passkey   = config('mpesa.passkey');
        $shortcode = config('mpesa.shortcode');

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password'          => base64_encode($shortcode . $passkey . $timestamp),
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => config('mpesa.callback_url'),
            'AccountReference'  => $accountRef,
            'TransactionDesc'   => 'Wallet funding',
        ];

        return Http::withToken($token)
            ->post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', $payload)
            ->throw()
            ->json();
    }
}
