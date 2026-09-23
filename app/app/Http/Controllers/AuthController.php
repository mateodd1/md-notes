<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeToMdNotes;
use App\Models\User;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use App\Services\PasswordSecurity;
use App\Services\ProfileVerificationCodes;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function sessionStatus(Request $request): JsonResponse
    {
        $response = response()->json([
            'authenticated' => $request->user() !== null,
        ]);

        $allowedOrigin = rtrim((string) config('md-notes.canonical_url'), '/');
        $requestOrigin = rtrim((string) $request->headers->get('Origin'), '/');

        if ($requestOrigin !== '' && hash_equals($allowedOrigin, $requestOrigin)) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Vary', 'Origin');
        }

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('ui.invalid_credentials')])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('notes.index'));
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, NoteSpace $spaces, NoteVersionHistory $history, ProfileVerificationCodes $codes): RedirectResponse
    {
        $name = $request->input('name');
        $email = $request->input('email');
        $request->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'email' => is_string($email) ? trim($email) : $email,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:80', 'regex:/\A[\p{L}0-9 ]+\z/u'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8', 'max:128'],
        ], ['name.regex' => __('ui.account_name_characters')]);

        $user = DB::transaction(fn (): User => User::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]));

        try {
            $codes->send($user, ProfileVerificationCodes::ACCOUNT);
        } catch (Throwable $exception) {
            report($exception);
            $user->delete();

            return back()->withErrors(['email' => __('ui.code_delivery_failed')])->onlyInput('name', 'email');
        }

        $spaces->root($user);
        $welcomePath = null;
        if ($spaces->tree($user) === []) {
            $welcomePath = $spaces->createNote($user, '', app()->getLocale() === 'es' ? 'Bienvenida' : 'Welcome');
            $welcomeContent = $this->welcomeNoteContent();
            $spaces->write($user, $welcomePath, $welcomeContent);
            $history->record($user, $welcomePath, $welcomeContent);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($welcomePath) {
            $request->session()->put('post_verification_path', $welcomePath);
        }

        return redirect()->route('verification.notice');
    }

    public function verificationNotice(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('notes.index');
        }

        return view('auth.verify-email');
    }

    public function verifyEmail(Request $request, ProfileVerificationCodes $codes): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('notes.index');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        if (! $codes->verify($user, ProfileVerificationCodes::ACCOUNT, $data['code'])) {
            return back()->withErrors(['code' => __('ui.invalid_or_expired_code')]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        try {
            Mail::to($user->email)->send(new WelcomeToMdNotes($user, app()->getLocale()));
        } catch (Throwable $exception) {
            report($exception);
        }

        $welcomePath = $request->session()->pull('post_verification_path');

        return $welcomePath
            ? redirect()->route('notes.show', ['path' => $welcomePath])->with('status', __('ui.account_created'))
            : redirect()->route('notes.index')->with('status', __('ui.account_created'));
    }

    public function resendVerificationCode(Request $request, ProfileVerificationCodes $codes): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('notes.index');
        }

        try {
            $codes->send($request->user(), ProfileVerificationCodes::ACCOUNT);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['code' => __('ui.code_delivery_failed')]);
        }

        return back()->with('status', __('ui.verification_code_sent'));
    }

    public function forgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'email' => __('ui.reset_link_send_failed'),
            ])->onlyInput('email');
        }

        return back()->with('status', __('ui.reset_link_sent'));
    }

    public function resetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'email' => $request->query('email'),
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $request, PasswordSecurity $passwordSecurity): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', 'min:8', 'max:128'],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) use ($passwordSecurity): void {
                $passwordSecurity->change($user, $password);

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('ui.password_reset_done'));
        }

        return back()->withErrors([
            'email' => __('ui.reset_link_invalid'),
        ])->onlyInput('email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function welcomeNoteContent(): string
    {
        if (app()->getLocale() === 'es') {
            return <<<'MARKDOWN'
# Bienvenido a md-notes

Tu espacio privado para apuntes en Markdown ya está listo.

## Empieza aquí

- Crea carpetas para organizar asignaturas, proyectos o temas.
- Añade notas `.md` y edítalas con vista previa de Markdown.
- Arrastra notas y carpetas para reorganizarlas.
- Pega imágenes desde el portapapeles mientras editas una nota.

## Tus notas, bajo control

- Cada nota guarda un historial de versiones durante hasta 7 días y 50 cambios.
- Descarga cualquier nota como archivo Markdown.
- Comparte una nota con un enlace temporal o sin caducidad.
- Tus notas y enlaces son privados para tu cuenta.

¡Que disfrutes tomando apuntes!
MARKDOWN;
        }

        return <<<'MARKDOWN'
# Welcome to md-notes

Your private Markdown notes space is ready.

## Get started

- Create folders to organise classes, projects, or topics.
- Add `.md` notes and edit them with a Markdown preview.
- Drag notes and folders to reorganise them.
- Paste images from your clipboard while editing a note.

## Your notes, under control

- Every note keeps a version history for up to 7 days and 50 changes.
- Download any note as a Markdown file.
- Share a note with a temporary or permanent link.
- Your notes and links are private to your account.

Happy note-taking!
MARKDOWN;
    }
}
