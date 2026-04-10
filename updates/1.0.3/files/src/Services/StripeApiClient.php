<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class StripeApiClient
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly string $secretKey
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $params): array
    {
        return $this->request('POST', '/checkout/sessions', $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createBillingPortalSession(array $params): array
    {
        return $this->request('POST', '/billing_portal/sessions', $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createProduct(array $params): array
    {
        return $this->request('POST', '/products', $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createPrice(array $params): array
    {
        return $this->request('POST', '/prices', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->request('GET', '/checkout/sessions/' . rawurlencode($sessionId), [
            'expand' => ['subscription'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->request('GET', '/subscriptions/' . rawurlencode($subscriptionId), []);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveEvent(string $eventId): array
    {
        return $this->request('GET', '/events/' . rawurlencode($eventId), []);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $params): array
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('Stripe secret key is missing.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for the Stripe integration.');
        }

        $url = rtrim(self::API_BASE, '/') . $path;
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $curl = curl_init();

        if (!$curl) {
            throw new RuntimeException('Could not initialize the Stripe request.');
        }

        if ($method === 'GET' && $params !== []) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method !== 'GET') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        }

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || $response === '') {
            throw new RuntimeException($curlError !== '' ? $curlError : 'Empty response from Stripe.');
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid response from Stripe.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = (string) ($decoded['error']['message'] ?? 'Stripe request failed.');
            throw new RuntimeException($errorMessage);
        }

        return $decoded;
    }
}
