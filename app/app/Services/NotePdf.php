<?php

namespace App\Services;

use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Masterminds\HTML5;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotePdf
{
    public function __construct(
        private readonly NoteMedia $media,
        private readonly MarkdownMediaUrls $mediaUrls,
    ) {}

    public function render(User $user, string $path, string $content): string
    {
        $temporaryDirectory = storage_path('app/private/pdf-tmp/'.Str::uuid());
        File::ensureDirectoryExists($temporaryDirectory, 0700, true);

        try {
            $options = new Options;
            $options->setIsRemoteEnabled(false);
            $options->setIsPhpEnabled(false);
            $options->setIsJavascriptEnabled(false);
            $options->setAllowedProtocols(['data://']);
            $options->setChroot([$temporaryDirectory]);
            $options->setTempDir($temporaryDirectory);
            $options->setFontCache($temporaryDirectory);
            $options->setDefaultFont('DejaVu Sans');
            $options->setIsFontSubsettingEnabled(true);

            $title = Str::beforeLast(basename($path), '.');
            $rendered = $this->renderMarkdown($user, $content);
            $pdf = new Dompdf($options);
            $pdf->setPaper('A4');
            $pdf->addInfo('Title', $title);
            $pdf->loadHtml(view('notes.pdf', compact('title', 'path', 'rendered'))->render(), 'UTF-8');
            $pdf->render();

            return $pdf->output();
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    private function renderMarkdown(User $user, string $content): string
    {
        $html = Str::markdown($this->mediaUrls->forAuthenticatedUser($content), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
        $parser = new HTML5;
        $document = $parser->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>');
        $mediaBase = Str::beforeLast(route('media.show', ['filename' => 'placeholder']), 'placeholder');

        foreach (iterator_to_array($document->getElementsByTagName('img')) as $image) {
            $source = $image->getAttribute('src');
            $filename = str_starts_with($source, $mediaBase)
                ? parse_url(substr($source, strlen($mediaBase)), PHP_URL_PATH)
                : null;
            $data = null;
            if (is_string($filename) && preg_match('/^[a-z0-9]{24}\.[a-z0-9]{1,10}$/', $filename)) {
                try {
                    $imagePath = $this->media->path($user, $filename);
                    if ($this->media->isImagePath($imagePath)) {
                        $data = 'data:'.mime_content_type($imagePath).';base64,'.base64_encode(File::get($imagePath));
                    }
                } catch (NotFoundHttpException) {
                    // A missing attachment must not break the export.
                }
            }

            if ($data !== null) {
                $image->setAttribute('src', $data);
            } else {
                $label = $document->createElement('span');
                $label->setAttribute('class', 'missing-image');
                $label->appendChild($document->createTextNode(__('ui.pdf_image_unavailable').($image->getAttribute('alt') !== '' ? ': '.$image->getAttribute('alt') : '')));
                $image->parentNode->replaceChild($label, $image);
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $rendered = '';
        foreach ($body->childNodes as $node) {
            $rendered .= $parser->saveHTML($node);
        }

        return $rendered;
    }
}
