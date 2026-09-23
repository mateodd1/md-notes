<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $documentationFiles = [
            resource_path('docs/documentation.md'),
            resource_path('docs/documentation.en.md'),
            resource_path('views/documentation.blade.php'),
        ];

        return response()->view('sitemap', [
            'urls' => [
                [
                    'location' => route('home'),
                    'lastModified' => $this->lastModified([
                        resource_path('views/welcome.blade.php'),
                        public_path('assets/md-notes-landing.css'),
                    ]),
                ],
                [
                    'location' => route('documentation'),
                    'lastModified' => $this->lastModified($documentationFiles),
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** @param array<int, string> $files */
    private function lastModified(array $files): string
    {
        $timestamps = array_map(
            static fn (string $file): int => is_file($file) ? (int) filemtime($file) : 0,
            $files,
        );

        return date(DATE_ATOM, max($timestamps));
    }
}
