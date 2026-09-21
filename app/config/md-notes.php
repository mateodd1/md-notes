<?php

return [
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MD_NOTES_TRUSTED_PROXIES', '')),
    ), static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false)),

    'canonical_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),

    'workspace_url' => rtrim((string) env('MD_NOTES_WORKSPACE_URL', env('APP_URL', 'http://localhost')), '/'),

    'legacy_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MD_NOTES_LEGACY_HOSTS', 'md.mateo.ovh')),
    ))),
];
