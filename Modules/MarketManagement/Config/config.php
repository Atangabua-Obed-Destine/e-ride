<?php

return [
    'name' => 'MarketManagement',
    // The short domain for shop and item links ("https://eride.link"). Empty: the app URL.
    'base_url' => env('MARKET_BASE_URL', ''),
    // The rider app's package, for "open in the app" links.
    'android_package' => env('MARKET_ANDROID_PACKAGE', 'com.i_nnova.eride'),
    // SHA-256 fingerprints of the app signing keys (Play Console > App integrity), comma separated.
    'android_sha256' => env('MARKET_ANDROID_SHA256', ''),
];
