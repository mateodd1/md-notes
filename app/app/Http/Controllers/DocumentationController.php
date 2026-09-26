<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function raw(): Response
    {
        $locale = app()->getLocale() === 'es' ? 'es' : 'en';
        $document = resource_path($locale === 'es' ? 'docs/documentation.md' : 'docs/documentation.en.md');
        abort_unless(is_file($document), 404);

        return response((string) file_get_contents($document), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(): View
    {
        $locale = app()->getLocale() === 'es' ? 'es' : 'en';
        $document = resource_path($locale === 'es' ? 'docs/documentation.md' : 'docs/documentation.en.md');
        abort_unless(is_file($document), 404);

        return view('documentation', [
            'title' => $locale === 'es' ? 'md-notes · Documentación' : 'md-notes · Documentation',
            'modifiedAt' => date(DATE_ATOM, (int) filemtime($document)),
            'rendered' => Str::markdown((string) file_get_contents($document), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }
}
