<?php
return [
    'enabled' => (bool) env('LIVE_TEST_MODE', false),
    'expires_at' => env('LIVE_TEST_EXPIRES_AT'),
];
