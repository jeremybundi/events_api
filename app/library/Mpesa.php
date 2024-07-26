<?php
namespace App\Library;

class Mpesa
{
    private $consumerKey;
    private $consumerSecret;
    private $shortcode;
    private $passkey;

    public function __construct()
    {
        $this->consumerKey = 'YOUR_CONSUMER_KEY';
        $this->consumerSecret = 'YOUR_CONSUMER_SECRET';
        $this->shortcode = 'YOUR_SHORTCODE';
        $this->passkey = 'YOUR_PASSKEY';
    }

    public function stkPush($phoneNumber, $amount)
    {
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $accessToken = $this->getAccessToken();

        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $curl_post_data = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => 'YOUR_CALLBACK_URL',
            'AccountReference' => 'Payment',
            'TransactionDesc' => 'Payment'
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $accessToken));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
        $curl_response = curl_exec($curl);

        return json_decode($curl_response, true);
    }

    private function getAccessToken()
    {
        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $curl_response = curl_exec($curl);

        $result = json_decode($curl_response, true);
        return $result['access_token'];
    }
}
