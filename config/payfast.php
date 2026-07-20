<?php

$sandbox = (bool) env('PAYFAST_SANDBOX', true);

return [
    'enabled' => (bool) env('PAYFAST_ENABLED', true),
    'sandbox' => $sandbox,
    'merchant_id' => env('PAYFAST_MERCHANT_ID', $sandbox ? '10000100' : null),
    'merchant_key' => env('PAYFAST_MERCHANT_KEY', $sandbox ? '46f0cd694581a' : null),
    'passphrase' => env('PAYFAST_PASSPHRASE'),
    'process_url' => $sandbox ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process',
    'validate_url' => $sandbox ? 'https://sandbox.payfast.co.za/eng/query/validate' : 'https://www.payfast.co.za/eng/query/validate',
    'validate_server' => (bool) env('PAYFAST_VALIDATE_SERVER', true),
    'validate_ip' => (bool) env('PAYFAST_VALIDATE_IP', true),
    'valid_hosts' => ['www.payfast.co.za', 'sandbox.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'],
];
