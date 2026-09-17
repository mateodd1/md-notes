<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Services\ApiTokens;
use App\Services\AccountExports;
use App\Services\NoteSpace;
use App\Services\ProfileVerificationCodes;
use App\Services\StorageQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountExportReadyMail;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly ProfileVerificationCodes $verificationCodes,
        private readonly ApiTokens $apiTokens,
        private readonly StorageQuota $quota,
        private readonly AccountExports $exports,
    )
    {
    }

    public function edit(Request $request): View|RedirectResponse
    {
        if ($response = $this->demoResponse($request)) {
            return $response;
        }

        return view('profile.edit', [
            'user' => $request->user(),
            'apiTokens' => $this->apiTokens->forUser($request->user()),
            'quota' => $this->quota->summary($request->user()),
        ]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        if ($response = $this->demoResponse($request)) {
            return $response;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $request->user()->forceFill(['name' => trim($data['name'])])->save();

        return back()->with('status', __('ui.name_updated'));
    }

    public function storeApiToken(Request $request): RedirectResponse
    {
        if ($response = $this->demoResponse($request)) {
            return $response;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        [, $token] = $this->apiTokens->create($request->user(), $data['name']);

        return back()->with([
            'api_token_created' => $token,
            'status' => __('ui.api_token_created'),
        ]);
    }

    public function destroyApiToken(Request $request, ApiToken $token): RedirectResponse
    {
        if ($response = $this->demoResponse($request)) {
            return $response;
        }

        abort_unless($token->user_id === $request->user()->getKey(), 404);
        $token->delete();

        return back()->with('status', __('ui.api_token_revoked'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        if ($response = $this->demoResponse($request)) {
            return $response;
        }

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'new_password' => ['required', 'string', 'confirmed', 'min:8', 'max:128'],
        ]);

        if (! $this->verificationCodes->verify($request->user(), ProfileVerificationCodes::PASSWORD, $data['code'])) {
            return back()->withErrors([
                'code' => __('ui.invalid_or_expired_code'),
            ]);
        }

        $request->user()->forceFill(['password' => Hash::make($data['new_password'])])->save();

        $request->session()->forget('password_code_requested');

        return back()->with('status', __('ui.password_updated'));
    }

    public function sendPasswordCode(Request $request): RedirectResponse
    {
        if ($response = $this->demoResponse($request)) {
            return $response;
        }

        try {
            $this->verificationCodes->send($request->user(), ProfileVerificationCodes::PASSWORD);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['password_code' => __('ui.code_delivery_failed')]);
        }

        $request->session()->put('password_code_requested', true);

        return back()->with('status', __('ui.password_code_sent'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($response = $this->demoResponse($request)) {
            return $response;
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
        ]);
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['delete_password' => __('ui.password_not_correct')]);
        }

        try {
            $this->exports->purgeForUser($user);
            $this->spaces->deleteSpace($user);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['delete_account' => $exception->getMessage()]);
        }

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('ui.account_deleted'));
    }

    public function requestAccountExport(Request $request): RedirectResponse
    {
        if ($response = $this->demoResponse($request)) {
            return $response;
        }

        $result = $this->exports->create($request->user());
        if ($result === null) {
            return back()->with('status', __('ui.account_export_daily_limit'));
        }

        [$export, $token] = $result;

        try {
            Mail::to($request->user()->email)->send(new AccountExportReadyMail(
                $request->user(),
                route('account-exports.download', ['token' => $token]),
                app()->getLocale(),
            ));
        } catch (Throwable $exception) {
            $this->exports->delete($export);
            report($exception);

            return back()->withErrors(['account_export' => __('ui.account_export_delivery_failed')]);
        }

        return back()->with('status', __('ui.account_export_email_sent'));
    }

    public function downloadAccountExport(string $token)
    {
        return $this->exports->download($token);
    }

    private function demoResponse(Request $request): ?RedirectResponse
    {
        if (! $request->user()->isDemo()) {
            return null;
        }

        return redirect()->route('notes.index')->with('status', __('ui.demo_settings_disabled'));
    }
}
