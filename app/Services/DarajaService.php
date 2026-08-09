<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DarajaService
{
    protected string $baseUrl;

    protected ?string $consumerKey;

    protected ?string $consumerSecret;

    protected string $stkShortcode;

    protected ?string $passkey;

    protected string $b2cShortcode;

    protected string $initiatorName;

    protected ?string $b2cPassword;

    protected ?string $callbackUrl;

    public function __construct()
    {
        $config = config('services.mpesa');

        $this->baseUrl = ($config['env'] ?? 'sandbox') === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $this->consumerKey = $config['consumer_key'] ?? null;
        $this->consumerSecret = $config['consumer_secret'] ?? null;
        $this->stkShortcode = $config['stk_shortcode'] ?? '174379';
        $this->passkey = $config['passkey'] ?? null;
        $this->b2cShortcode = $config['b2c_shortcode'] ?? '600192';
        $this->initiatorName = $config['initiator_name'] ?? 'testapi';
        $this->b2cPassword = $config['b2c_password'] ?? null;
        $this->callbackUrl = $config['callback_url'] ?? null;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->consumerKey) &&
               ! empty($this->consumerSecret) &&
               $this->consumerKey !== 'your_consumer_key';
    }

    public function getAccessToken(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->baseUrl.'/oauth/v1/generate?grant_type=client_credentials');

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('Daraja OAuth failed: '.$response->body());
        } catch (\Exception $e) {
            Log::error('Daraja OAuth exception: '.$e->getMessage());
        }

        return null;
    }

    public function initiateStkPush(string $checkoutReference, float $amount, string $phoneNumber): array
    {
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        if (! $this->isConfigured()) {
            Log::info("Daraja STK Push [SIMULATION] for Ref: {$checkoutReference}, Amt: {$amount}, Phone: {$formattedPhone}");

            return [
                'success' => true,
                'is_simulation' => true,
                'checkout_request_id' => 'ws_CO_'.uniqid(),
                'merchant_request_id' => 'merch_'.uniqid(),
                'response_code' => '0',
                'response_description' => 'Success (Simulated)',
            ];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Failed to generate Daraja access token'];
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->stkShortcode.$this->passkey.$timestamp);

        // Append checkout_reference as a query parameter for direct identification on receipt
        $callback = ($this->callbackUrl ?: url('/')).'/api/daraja/stk-callback/'.$checkoutReference;

        $payload = [
            'BusinessShortCode' => $this->stkShortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round($amount),
            'PartyA' => $formattedPhone,
            'PartyB' => $this->stkShortcode,
            'PhoneNumber' => $formattedPhone,
            'CallBackURL' => $callback,
            'AccountReference' => substr($checkoutReference, 0, 12), // Max 12 characters required by Safaricom
            'TransactionDesc' => 'Loan Repayment',
        ];

        try {
            $response = Http::withToken($token)
                ->post($this->baseUrl.'/mpesa/stkpush/v1/processrequest', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'is_simulation' => false,
                    'checkout_request_id' => $response->json('CheckoutRequestID'),
                    'merchant_request_id' => $response->json('MerchantRequestID'),
                    'response_code' => $response->json('ResponseCode'),
                    'response_description' => $response->json('ResponseDescription'),
                ];
            }

            Log::error('Daraja STK Push failed: '.$response->body());

            return ['success' => false, 'message' => $response->json('errorMessage') ?? 'STK push execution failed'];
        } catch (\Exception $e) {
            Log::error('Daraja STK Push exception: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function initiateB2cDisbursement(string $reference, float $amount, string $phoneNumber): array
    {
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        if (! $this->isConfigured()) {
            Log::info("Daraja B2C [SIMULATION] for Ref: {$reference}, Amt: {$amount}, Phone: {$formattedPhone}");

            return [
                'success' => true,
                'is_simulation' => true,
                'conversation_id' => 'AG_'.uniqid(),
                'originator_conversation_id' => 'ORIG_'.uniqid(),
                'response_code' => '0',
                'response_description' => 'Accept the service request successfully (Simulated)',
            ];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Failed to generate Daraja access token'];
        }

        $callback = ($this->callbackUrl ?: url('/')).'/api/daraja/b2c-callback/'.$reference;

        $payload = [
            'InitiatorName' => $this->initiatorName,
            'SecurityCredential' => $this->b2cPassword, // For sandbox, use B2C plain initiator password or encrypted credential from dev portal
            'CommandID' => 'PromotionPayment',
            'Amount' => (int) round($amount),
            'PartyA' => $this->b2cShortcode,
            'PartyB' => $formattedPhone,
            'Remarks' => 'Loan Payout',
            'QueueTimeOutURL' => $callback,
            'ResultURL' => $callback,
            'Occasion' => 'Disbursement',
        ];

        try {
            $response = Http::withToken($token)
                ->post($this->baseUrl.'/mpesa/b2c/v1/paymentrequest', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'is_simulation' => false,
                    'conversation_id' => $response->json('ConversationID'),
                    'originator_conversation_id' => $response->json('OriginatorConversationID'),
                    'response_code' => $response->json('ResponseCode'),
                    'response_description' => $response->json('ResponseDescription'),
                ];
            }

            Log::error('Daraja B2C failed: '.$response->body());

            return ['success' => false, 'message' => $response->json('errorMessage') ?? 'B2C payout execution failed'];
        } catch (\Exception $e) {
            Log::error('Daraja B2C exception: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (! str_starts_with($phone, '254') && strlen($phone) === 9) {
            $phone = '254'.$phone;
        }

        return $phone;
    }
}
