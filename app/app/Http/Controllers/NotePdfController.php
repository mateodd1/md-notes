<?php

namespace App\Http\Controllers;

use App\Services\NotePdf;
use App\Services\NoteSpace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotePdfController extends Controller
{
    public function __invoke(Request $request, string $path, NoteSpace $spaces, NotePdf $pdf): StreamedResponse
    {
        $content = $spaces->read($request->user(), $path);
        $document = $pdf->render($request->user(), $path, $content);
        $filename = Str::beforeLast(basename($path), '.').'.pdf';

        return response()->streamDownload(static function () use ($document): void {
            echo $document;
        }, $filename, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }
}
