<?php

return [
    'canonical_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),

    'legacy_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MD_NOTES_LEGACY_HOSTS', 'md.mateo.ovh')),
    ))),
];
