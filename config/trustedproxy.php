<?php

return [
    // Set only the IPs/CIDRs of reverse proxies that can reach the origin.
    // Read through config so this also works after `artisan config:cache`.
    'proxies' => array_values(array_filter(array_map(
        'trim', explode(',', env('TRUSTED_PROXIES', ''))
    ))),
];
