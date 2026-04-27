<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

final class FawryService
{
    public static function generatePaymentLink(array $data): ?string
    {
        $merchantCode = env('FAWRY_MERCH_CODE', '1tSa6uxz2nTwlaAmt38enA==');
        $secureKey = env('FAWRY_SECURE_KEY', '259af31fc2f74453b3a55739b21ae9ef');
        $isProduction = (bool) env('FAWRY_IS_PROD', false);
        
        $baseUrl = $isProduction 
            ? 'https://atfawry.com/fawrypay-api/api/payments/init'
            : 'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init';

        // 1. Critical: Price must be exactly 2 decimal places in BOTH signature and JSON body
        $amountStr = number_format($data['amount'], 2, '.', '');
        $quantity = 1;

        // 2. Signature Calculation
        $signatureSource = $merchantCode 
            . $data['merchantRefNum'] 
            . $data['customerProfileId'] 
            . $data['returnUrl'] 
            . $data['itemId'] 
            . $quantity 
            . $amountStr 
            . $secureKey;
            
        $signature = hash('sha256', $signatureSource);

        $payload = [
            'merchantCode'      => $merchantCode,
            'merchantRefNum'    => $data['merchantRefNum'],
            'customerMobile'    => $data['customerMobile'],
            'customerEmail'     => $data['customerEmail'],
            'customerName'      => $data['customerName'],
            'customerProfileId' => $data['customerProfileId'],
            'language'          => 'ar-eg',
            'chargeItems'       => [
                [
                    'itemId'      => $data['itemId'],
                    'description' => $data['description'],
                    // 3. Fix: Send price as a string or ensure it retains decimals. 
                    // Fawry sometimes rejects integers when it expects decimals.
                    'price'       => $amountStr, 
                    'quantity'    => $quantity,
                ]
            ],
            'returnUrl'         => $data['returnUrl'],
            'signature'         => $signature,
        ];

        $ch = curl_init($baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 4. Fix: Simplify headers. Some Fawry versions dislike the charset in Content-Type
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            Logger::error('Fawry CURL Error', ['error' => curl_error($ch)]);
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $result = json_decode($response, true);
        
        // 406 logic usually means Fawry didn't like the data format
        if ($httpCode !== 200) {
            Logger::error('Fawry API Error Response', [
                'http_code' => $httpCode, 
                'response'  => $result, 
                'payload'   => $payload
            ]);
            return null;
        }

        return $result['redirectUrl'] ?? null;
    }

    public static function verifyCallback(array $data): bool
    {
        $secureKey = env('FAWRY_SECURE_KEY', '259af31fc2f74453b3a55739b21ae9ef');
        
        // Ensure amount is formatted to 2 decimals for verification
        $amount = number_format((float)$data['paymentAmount'], 2, '.', '');

        $signatureSource = ($data['fawryRefNumber'] ?? '')
            . ($data['merchantRefNum'] ?? '')
            . $amount
            . ($data['orderStatus'] ?? '')
            . $secureKey;
            
        $expectedSignature = hash('sha256', $signatureSource);
        
        return hash_equals($expectedSignature, (string) ($data['signature'] ?? ''));
    }
}