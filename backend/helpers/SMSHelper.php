<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * SMS_DRIVER=http POSTs to SMS_API_URL with a bearer SMS_API_KEY.
 * Any other value logs to storage/logs/sms.log.
 */
final class SMSHelper
{
    public static function send(string $phone, string $message): bool
    {
        $driver = strtolower((string) env('SMS_DRIVER', 'log'));

        if ($driver !== 'http') {
            return self::logToFile(['phone' => $phone, 'message' => $message]);
        }

        $apiUrl = (string) env('SMS_API_URL', '');
        $apiKey = (string) env('SMS_API_KEY', '');

        if ($apiUrl === '' || $apiKey === '') {
            return self::logToFile([
                'phone'   => $phone,
                'message' => $message,
                'note'    => 'SMS_API_URL/SMS_API_KEY missing; wrote to log.',
            ]);
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'to'     => $phone,
                'text'   => $message,
                'sender' => (string) env('SMS_SENDER_ID', 'IRB'),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $status >= 200 && $status < 300 && $response !== false;
    }

    private static function logToFile(array $payload): bool
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . date('c') . '] sms ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return (bool) @file_put_contents($dir . '/sms.log', $line, FILE_APPEND);
    }
}
