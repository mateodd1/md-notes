<?php

namespace App\Http\Controllers;

use App\Services\OfflineNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OfflineController extends Controller
{
    public function shell(): View
    {
        return view('offline');
    }

    public function worker(): Response
    {
        $assets = [
            '/offline', '/assets/md-notes-offline.css', '/assets/md-notes-offline-db.js',
            '/assets/md-notes-offline.js', '/assets/md-notes-offline-page.js',
            '/assets/md-notes-base.css', '/assets/md-notes-google.css',
            '/assets/md-notes-viewport.js',
            '/assets/vendor/marked.js', '/assets/vendor/purify.js',
        ];
        $version = hash('sha256', implode('|', array_map(static fn (string $path): string => (string) filemtime($path === '/offline' ? resource_path('views/offline.blade.php') : public_path($path)), $assets)).filemtime(public_path('assets/md-notes-offline-worker.js')));
        $configuration = json_encode(['version' => $version, 'assets' => $assets, 'base' => parse_url(route('notes.index'), PHP_URL_PATH) ?: '/'], JSON_THROW_ON_ERROR);

        return response('const OFFLINE_CONFIG = '.$configuration.";\n".file_get_contents(public_path('assets/md-notes-offline-worker.js')))
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('Cache-Control', 'no-cache')
            ->header('Service-Worker-Allowed', '/');
    }

    public function session(Request $request): JsonResponse
    {
        return response()->json([
            'account' => OfflineNotes::accountKey($request->user()),
            'csrf' => csrf_token(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function sync(Request $request, OfflineNotes $offline): JsonResponse
    {
        $data = $request->validate([
            'account' => ['required', 'string', 'size:64'],
            'path' => ['bail', 'required', 'string', 'max:500', 'regex:/\.md\z/i', function (string $attribute, mixed $value, \Closure $fail): void {
                foreach (explode('/', $value) as $segment) {
                    if (! preg_match('/^[\pL\pN][\pL\pN _().,!&-]{0,79}(?:\.md)?$/u', $segment)) {
                        $fail(__('ui.invalid_item_name'));

                        return;
                    }
                }
            }],
            'content' => ['present', 'string', 'max:5242880'],
            'revision' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'change_id' => ['required', 'uuid'],
            'snapshot' => ['required', 'boolean'],
        ]);
        if (! hash_equals(OfflineNotes::accountKey($request->user()), $data['account'])) {
            return response()->json(['message' => __('offline.account_changed'), 'code' => 'account_changed'], 409);
        }
        if (strlen($data['content']) > 5 * 1024 * 1024) {
            return response()->json(['message' => __('offline.too_large')], 422);
        }
        try {
            return response()->json($offline->save($request->user(), $data['path'], $data['content'], $data['revision'], $data['change_id'], $data['snapshot']))
                ->header('Cache-Control', 'private, no-store');
        } catch (HttpException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage() ?: __('ui.could_not_save')], 422);
        }
    }
}
