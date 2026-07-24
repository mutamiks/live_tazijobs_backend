<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsService
{
    public function send(string $phone, string $message): string
    {
        $message = trim($message);
        if (mb_strlen($message) >= 160) {
            throw new RuntimeException('SMS messages must contain fewer than 160 characters.');
        }
        if (app()->environment('testing')) {
            return 'TEST';
        }
        $this->ensureConfigured();

        $normalizedPhone = $this->normalizePhone($phone);
        $response = Http::timeout(20)->get($this->gatewayUrl(), [
            'number' => $normalizedPhone,
            'message' => $message,
            'username' => config('sms.username'),
            'password' => config('sms.password'),
            'sender' => config('sms.sender'),
        ])->throw();

        $result = trim($response->body());
        Log::info('EgoSMS submission response', [
            'phone_suffix' => substr($normalizedPhone, -4),
            'response' => $result,
        ]);

        $rejected = $result === ''
            || str_starts_with(strtoupper($result), 'ERR')
            || str_contains(strtoupper($result), 'NOT ENOUGH')
            || str_contains(strtoupper($result), 'INSUFFICIENT')
            || str_contains(strtoupper($result), 'FAILED');
        if ($rejected) {
            throw new RuntimeException($result ?: 'The SMS gateway returned an empty response.');
        }

        return $result;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($phone, '0')) {
            return '256'.substr($phone, 1);
        }
        if (str_starts_with($phone, '7')) {
            return '256'.$phone;
        }
        return $phone;
    }

    public function ensureConfigured(bool $payments = false): void
    {
        $keys = $payments
            ? ['sms.payment_url', 'sms.payment_username', 'sms.payment_password']
            : ['sms.gateway_url', 'sms.username', 'sms.password'];

        if (! config('sms.enabled') || collect($keys)->contains(fn ($key) => blank(config($key)))) {
            throw new RuntimeException('SMS service is not configured. Add the SMS provider settings to backend/.env.');
        }
    }

    public function balance(): string
    {
        $this->ensureConfigured();
        $url = $this->gatewayUrl();
        $response = Http::timeout(20)->get($url, [
            'method' => 'Balance',
            'username' => config('sms.username'),
            'password' => config('sms.password'),
        ])->throw();

        return trim($response->body());
    }

    public function requestPayment(float $amount, string $phone, string $purpose = 'SMS Payment'): array
    {
        $this->ensureConfigured(true);
        $reference = 'TAZI-'.str()->upper(str()->slug($purpose, '-')).'-'.str()->upper(str()->random(12));
        $xml = '<?xml version="1.0" encoding="UTF-8"?><AutoCreate><Request>'
            .'<APIUsername>'.$this->xml(config('sms.payment_username')).'</APIUsername>'
            .'<APIPassword>'.$this->xml(config('sms.payment_password')).'</APIPassword>'
            .'<Method>'.$this->xml(config('sms.payment_method')).'</Method><NonBlocking>TRUE</NonBlocking>'
            .'<Amount>'.$amount.'</Amount><Account>'.$this->xml($phone).'</Account>'
            .'<Narrative>'.$reference.'</Narrative><ExternalReference>TaziJobs</ExternalReference>'
            .'<ProviderReferenceText>'.$this->xml($purpose).'</ProviderReferenceText></Request></AutoCreate>';

        return $this->paymentRequest($xml);
    }

    public function providerMessage(array $provider, string $fallback = 'Payment request failed.'): string
    {
        return $provider['StatusMessage']
            ?? $provider['Message']
            ?? $provider['ErrorMessage']
            ?? $provider['Error']
            ?? $provider['TransactionStatus']
            ?? $provider['Status']
            ?? $fallback;
    }

    public function paymentStatus(string $reference): array
    {
        $this->ensureConfigured(true);
        $method = config('sms.payment_status_method');
        if (config('sms.payment_method') === 'acdepositfunds' && $method === 'mmstatus') {
            $method = 'actransactioncheckstatus';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><AutoCreate><Request>'
            .'<APIUsername>'.$this->xml(config('sms.payment_username')).'</APIUsername>'
            .'<APIPassword>'.$this->xml(config('sms.payment_password')).'</APIPassword>'
            .'<Method>'.$this->xml($method).'</Method>'
            .'<TransactionReference>'.$this->xml($reference).'</TransactionReference>'
            .'</Request></AutoCreate>';

        return $this->paymentRequest($xml);
    }

    public function giveCredits(int $credits): array
    {
        $this->ensureConfigured();
        if (blank(config('sms.topup_url')) || blank(config('sms.reseller_username')) || blank(config('sms.reseller_password'))) {
            throw new RuntimeException('SMS reseller top-up settings are not configured.');
        }

        return Http::timeout(30)->asJson()->post(config('sms.topup_url'), [
            'method' => 'GiveCredit',
            'userdata' => [
                'username' => config('sms.reseller_username'),
                'password' => config('sms.reseller_password'),
            ],
            'accountdata' => [
                'giveusername' => config('sms.username'),
                'rate' => config('sms.rate'),
                'credits' => $credits,
            ],
        ])->throw()->json();
    }

    private function paymentRequest(string $xml): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'text/xml;charset=utf-8'])
            ->withBody($xml, 'text/xml')
            ->post(config('sms.payment_url'))
            ->throw();
        $parsed = simplexml_load_string($response->body());
        if (! $parsed) {
            throw new RuntimeException('The payment provider returned an invalid response.');
        }

        $payload = json_decode(json_encode($parsed), true);
        $provider = $payload['Response'] ?? $payload;

        Log::info('Yo payment provider response', [
            'status' => $provider['Status'] ?? null,
            'transaction_status' => $provider['TransactionStatus'] ?? null,
            'message' => $this->providerMessage($provider, ''),
            'transaction_reference' => $provider['TransactionReference'] ?? null,
        ]);

        return $provider;
    }

    private function gatewayUrl(): string
    {
        $url = config('sms.gateway_url');
        return str_starts_with($url, 'http') ? $url : 'https://'.$url;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
