<?php

declare(strict_types=1);

use App\Services\PaymobService;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/env.php';

/**
 * Minimal self-test (no phpunit in this repo):
 * - Validates Paymob HMAC verification against a known sample payload.
 *
 * Run inside docker:
 *   docker exec -w /var/www/html/backend irb_api php scripts/paymob-selftest.php
 */

$sample = [
    'type' => 'TRANSACTION',
    'obj' => [
        'id' => 192036465,
        'pending' => false,
        'amount_cents' => 100000,
        'success' => true,
        'is_auth' => false,
        'is_capture' => false,
        'is_standalone_payment' => true,
        'is_voided' => false,
        'is_refunded' => false,
        'is_3d_secure' => true,
        'integration_id' => 4097558,
        'has_parent_transaction' => false,
        'order' => [
            'id' => 217503754,
        ],
        'created_at' => '2024-06-13T11:33:44.592345',
        'currency' => 'EGP',
        'source_data' => [
            'pan' => '2346',
            'type' => 'card',
            'sub_type' => 'MasterCard',
        ],
        'error_occured' => false,
        'owner' => 302852,
    ],
];

$hmacSecret = (string) env('PAYMOB_HMAC_SECRET', '');
if ($hmacSecret === '') {
    fwrite(STDERR, "PAYMOB_HMAC_SECRET is missing. Set it in backend/.env before running.\n");
    exit(2);
}

// Build expected HMAC string per Paymob ordering, then hash with sha512.
$concat =
    '100000' .
    '2024-06-13T11:33:44.592345' .
    'EGP' .
    'false' .
    'false' .
    '192036465' .
    '4097558' .
    'true' .
    'false' .
    'false' .
    'false' .
    'true' .
    'false' .
    '217503754' .
    '302852' .
    'false' .
    '2346' .
    'MasterCard' .
    'card' .
    'true';

$hmac = hash_hmac('sha512', $concat, $hmacSecret);
$ok = PaymobService::verifyTransactionHmac($sample, $hmac);

if (!$ok) {
    fwrite(STDERR, "Paymob HMAC self-test failed.\n");
    exit(1);
}

fwrite(STDOUT, "Paymob HMAC self-test passed.\n");
exit(0);

