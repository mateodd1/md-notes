<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function show(): View
    {
        $locale = app()->getLocale() === 'es' ? 'es' : 'en';
        $document = resource_path($locale === 'es' ? 'docs/documentation.md' : 'docs/documentation.en.md');
        abort_unless(is_file($document), 404);

        return view('documentation', [
            'title' => $locale === 'es' ? 'md-notes · Documentación' : 'md-notes · Documentation',
            'rendered' => Str::markdown((string) file_get_contents($document), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }
}
