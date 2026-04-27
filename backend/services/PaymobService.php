<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use Paymob\Library\Paymob;

/**
 * Paymob integration boundary for creating checkout links and verifying callbacks.
 *
 * This service intentionally keeps Paymob-specific details encapsulated here to
 * keep controllers thin and readable.
 */
final class PaymobService
{
    private const MERCHANT_REFERENCE_PREFIX = 'payment:';

    /**
     * @param array{
     *   amount: float,
     *   currency?: string,
     *   merchant_reference: string,
     *   description: string,
     *   customer: array{
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     phone_number: string,
     *     city?: string,
     *     country?: string,
     *     state?: string,
     *     street?: string,
     *     postal_code?: string,
     *     apartment?: string,
     *     floor?: string,
     *     building?: string
     *   }
     * } $data
     */
    public static function createQuickLink(array $data): string
    {
        $secretKey = (string) env('PAYMOB_API_SECRET_KEY', '');
        $publicKey = (string) env('PAYMOB_PUBLIC_KEY', '');
        $apiKey = (string) env('PAYMOB_API_KEY', '');
        $enabledMethods = (string) env('PAYMOB_ENABLED_METHODS', 'card,wallet,kiosk');

        if ($secretKey === '' || $publicKey === '' || $apiKey === '') {
            Logger::error('Paymob credentials missing');
            throw new \RuntimeException('Paymob credentials are not set.');
        }

        $amountCents = self::toAmountCents((float) $data['amount']);
        $currency = (string) ($data['currency'] ?? 'EGP');
        $integrationIds = self::getIntegrationIdsForEnabledMethods([
            'apiKey' => $apiKey,
            'pubKey' => $publicKey,
            'secKey' => $secretKey,
        ], $enabledMethods);

        if ($integrationIds === []) {
            Logger::error('No Paymob integration IDs resolved', ['details' => ['enabled_methods' => $enabledMethods]]);
            throw new \RuntimeException('No Paymob integration IDs available for configured methods.');
        }

        $customer = $data['customer'];
        $billing = self::buildBillingData($customer);

        $payload = [
            'amount' => $amountCents,
            'currency' => $currency,
            'payment_methods' => array_values($integrationIds),
            'billing_data' => $billing,
            'delivery_needed' => false,
            'extras' => [
                'merchant_reference' => (string) $data['merchant_reference'],
                'description' => (string) $data['description'],
            ],
            'special_reference' => (string) $data['merchant_reference'],
        ];

        $client = new Paymob();
        $intention = $client->createIntention($secretKey, $payload, (string) $data['merchant_reference']);

        if (!is_array($intention) || !($intention['success'] ?? false) || !isset($intention['cs'])) {
            Logger::error('Paymob intention creation failed', [
                'details' => [
                    'merchant_reference' => $data['merchant_reference'],
                    'message' => $intention['message'] ?? null,
                ],
            ]);
            throw new \RuntimeException('Paymob intention creation failed.');
        }

        $countryCode = $client->getCountryCode($secretKey);
        $apiUrl = $client->getApiUrl($countryCode);
        $clientSecret = (string) $intention['cs'];

        return $apiUrl . 'unifiedcheckout/?publicKey=' . urlencode($publicKey) . '&clientSecret=' . urlencode($clientSecret);
    }

    /**
     * Verify Paymob Transaction Processed callback HMAC.
     *
     * Paymob provides `hmac` as a query parameter; the payload is POST JSON.
     *
     * @param array<string,mixed> $jsonBody
     */
    public static function verifyTransactionHmac(array $jsonBody, string $hmac): bool
    {
        $hmacSecret = (string) env('PAYMOB_HMAC_SECRET', '');
        if ($hmacSecret === '' || $hmac === '') {
            return false;
        }

        try {
            return Paymob::verifyAcceptHmac($hmacSecret, $jsonBody, $hmac);
        } catch (\Throwable $err) {
            Logger::warning('Paymob HMAC verification error', ['error_message' => $err->getMessage()]);
            return false;
        }
    }

    /**
     * @param array<string,mixed> $jsonBody
     * @return array{
     *   success: bool,
     *   amount_cents: int,
     *   transaction_id: int,
     *   order_id: int,
     *   integration_id: int,
     *   method: string|null
     * }
     */
    public static function parseTransactionCallback(array $jsonBody): array
    {
        $obj = $jsonBody['obj'] ?? null;
        if (!is_array($obj)) {
            return [
                'success' => false,
                'amount_cents' => 0,
                'transaction_id' => 0,
                'order_id' => 0,
                'integration_id' => 0,
                'method' => null,
            ];
        }

        $order = $obj['order'] ?? null;
        $orderId = is_array($order) ? (int) ($order['id'] ?? 0) : 0;
        $source = $obj['source_data'] ?? null;
        $method = is_array($source) ? (string) ($source['type'] ?? '') : '';
        $method = $method !== '' ? $method : null;

        return [
            'success' => (bool) ($obj['success'] ?? false),
            'amount_cents' => (int) ($obj['amount_cents'] ?? 0),
            'transaction_id' => (int) ($obj['id'] ?? 0),
            'order_id' => $orderId,
            'integration_id' => (int) ($obj['integration_id'] ?? 0),
            'method' => $method,
        ];
    }

    public static function buildMerchantReference(int $paymentId): string
    {
        return self::MERCHANT_REFERENCE_PREFIX . $paymentId;
    }

    public static function parsePaymentIdFromReference(string $reference): int
    {
        if (!str_starts_with($reference, self::MERCHANT_REFERENCE_PREFIX)) {
            return 0;
        }

        $id = (int) substr($reference, strlen(self::MERCHANT_REFERENCE_PREFIX));
        return $id > 0 ? $id : 0;
    }

    private static function toAmountCents(float $amount): int
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        return (int) round($amount * 100);
    }

    /**
     * @param array{
     *   email: string,
     *   first_name: string,
     *   last_name: string,
     *   phone_number: string,
     *   city?: string,
     *   country?: string,
     *   state?: string,
     *   street?: string,
     *   postal_code?: string,
     *   apartment?: string,
     *   floor?: string,
     *   building?: string
     * } $customer
     * @return array<string,string>
     */
    private static function buildBillingData(array $customer): array
    {
        $defaults = [
            'apartment' => 'NA',
            'email' => $customer['email'],
            'floor' => 'NA',
            'first_name' => $customer['first_name'],
            'street' => $customer['street'] ?? 'NA',
            'building' => $customer['building'] ?? 'NA',
            'phone_number' => $customer['phone_number'],
            'shipping_method' => 'NA',
            'postal_code' => $customer['postal_code'] ?? 'NA',
            'city' => $customer['city'] ?? 'NA',
            'country' => $customer['country'] ?? 'EG',
            'last_name' => $customer['last_name'],
            'state' => $customer['state'] ?? 'NA',
        ];

        if (isset($customer['apartment'])) {
            $defaults['apartment'] = (string) $customer['apartment'];
        }
        if (isset($customer['floor'])) {
            $defaults['floor'] = (string) $customer['floor'];
        }

        return array_map(static fn($v): string => (string) $v, $defaults);
    }

    /**
     * Resolve Paymob integration IDs matching enabled methods.
     *
     * This uses Paymob's provided helper (`authToken`) to fetch available integration IDs.
     *
     * @param array{apiKey:string,pubKey:string,secKey:string} $conf
     * @return list<int>
     */
    private static function getIntegrationIdsForEnabledMethods(array $conf, string $enabledMethods): array
    {
        $wanted = array_filter(array_map('trim', explode(',', strtolower($enabledMethods))));
        if ($wanted === []) {
            $wanted = ['card', 'wallet', 'kiosk'];
        }

        $client = new Paymob();
        $auth = $client->authToken($conf);
        $integrationIds = $auth['integrationIDs'] ?? [];

        if (!is_array($integrationIds)) {
            return [];
        }

        $result = [];
        foreach ($integrationIds as $id => $info) {
            if (!is_array($info)) {
                continue;
            }

            $gatewayType = strtolower((string) ($info['gateway_type'] ?? ''));
            $typeLabel = strtolower((string) ($info['type'] ?? ''));

            $method = match (true) {
                $gatewayType === 'vpc' || $typeLabel === 'card' => 'card',
                $gatewayType === 'uig' || $typeLabel === 'wallet' => 'wallet',
                $typeLabel === 'aman' => 'kiosk',
                default => '',
            };

            if ($method === '' || !in_array($method, $wanted, true)) {
                continue;
            }

            $result[] = (int) $id;
        }

        return array_values(array_unique($result));
    }
}

