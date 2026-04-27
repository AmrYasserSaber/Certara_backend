<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

final class KashierService
{
    /**
     * @param array{
     *   amount: float,
     *   reference_id: string,
     *   email: string,
     *   full_name: string,
     *   description: string
     * } $data
     * @return string|null The Kashier Checkout URL
     */
    public static function generatePaymentLink(array $data): ?string
    {
        $merchantId = env('KASHIER_MERCHANT_ID');
        $secretKey = env('KASHIER_API_KEY'); // Your Kashier Secret Key
        $isLive = (bool) env('KASHIER_IS_LIVE', false);

        if (!$merchantId || !$secretKey) {
            Logger::error('Kashier credentials are not set');
            return null;
        }

        $orderId = $data['reference_id'];
        $amount = number_format($data['amount'], 2, '.', '');
        $currency = "EGP";
        
        // 1. Generate the Hash for the request
        // Path: /mid={mid}&orderId={orderId}&amount={amount}&currency={currency}
        $hashString = "/mid={$merchantId}&orderId={$orderId}&amount={$amount}&currency={$currency}";
        $hash = hash_hmac('sha256', $hashString, $secretKey);

        // 2. Build the Redirect URL
        $baseUrl = "https://checkout.kashier.com";
        
        $params = [
            'merchantId' => $merchantId,
            'orderId'    => $orderId,
            'amount'     => $amount,
            'currency'   => $currency,
            'hash'       => $hash,
            'mode'       => $isLive ? 'live' : 'test',
            'metaData'   => json_encode([
                'description' => $data['description'],
                'customerName' => $data['full_name']
            ]),
            // Optional: You can pass these to pre-fill the form
            'customerEmail' => $data['email'],
            'lang'          => 'ar',
            'serverWebhook' => env('APP_URL') . '/api/payments/callback/kashier', // Your webhook
            'display'       => 'en'
        ];

        return $baseUrl . '/?' . http_build_query($params);
    }

    /**
     * Verifies the signature of the callback from Kashier.
     * Kashier sends response parameters in the Query String.
     */
    public static function verifyCallback(array $data): bool
    {
        $secretKey = env('KASHIER_API_KEY');
        
        // The 'signature' (sometimes called 'token' in Kashier docs) is what we check against
        $signature = $data['signature'] ?? ($data['queryString']['signature'] ?? null);
        
        if (!$signature) {
            return false;
        }

        /*
         * Kashier Data String Order for Callback:
         * queryString = "cardPAN=" + cardPAN + "&currency=" + currency + "&amount=" + amount + "&mid=" + mid + "&orderId=" + orderId + "&transactionNo=" + transactionNo + "&orderStatus=" + orderStatus;
         */
        
        $cardPAN = $data['cardPAN'] ?? '';
        $currency = $data['currency'] ?? '';
        $amount = $data['amount'] ?? '';
        $mid = env('KASHIER_MERCHANT_ID');
        $orderId = $data['orderId'] ?? '';
        $transactionNo = $data['transactionNo'] ?? '';
        $orderStatus = $data['paymentStatus'] ?? $data['orderStatus'] ?? '';

        $queryString = "cardPAN={$cardPAN}&currency={$currency}&amount={$amount}&mid={$mid}&orderId={$orderId}&transactionNo={$transactionNo}&orderStatus={$orderStatus}";
        
        $expectedSignature = hash_hmac('sha256', $queryString, $secretKey);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Logger::warning('Kashier Callback Signature Mismatch', [
                'expected' => $expectedSignature,
                'received' => $signature,
                'string_built' => $queryString
            ]);
        }

        return $isValid;
    }
}