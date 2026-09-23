<?php

return [
    'home' => 'Go to homepage',
    'pages' => [
        401 => ['title' => 'Sign in to continue', 'description' => 'You need to sign in to view this page.'],
        403 => ['title' => 'Access denied', 'description' => 'This page is not available to your account.'],
        404 => ['title' => 'Page not found', 'description' => 'The address may be incorrect or the content is no longer available.'],
        405 => ['title' => 'Action unavailable', 'description' => 'This action cannot be performed from here.'],
        419 => ['title' => 'Your session has expired', 'description' => 'Open the page again and try once more.'],
        429 => ['title' => 'Too many requests', 'description' => 'Please wait a moment before trying again.'],
        500 => ['title' => 'Something went wrong', 'description' => 'We could not load this page. Please try again in a few minutes.'],
        503 => ['title' => 'Service unavailable', 'description' => 'We are performing maintenance or the service is temporarily unavailable.'],
    ],
];
