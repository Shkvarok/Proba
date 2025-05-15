<?php
return [
    'public_key' => env('LIQPAY_PUBLIC_KEY', ''),
    'private_key' => env('LIQPAY_PRIVATE_KEY', ''),
    'sandbox' => env('LIQPAY_SANDBOX', true) // true для тестового режиму
];