<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeToMdNotes;
use App\Models\User;
use App\Services\NoteSpace;
use App\Services\NoteVersionHistory;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
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

        return redirect()->intended(route('notes.index'));
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, NoteSpace $spaces, NoteVersionHistory $history): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => trim((string) $request->input('email')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8', 'max:128'],
        ]);

        $firstAccount = User::query()->doesntExist();
        $user = DB::transaction(fn (): User => User::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]));

        $spaces->root($user);
        $legacyImported = false;
        if ($firstAccount) {
            try {
                $spaces->importLegacySpace($user);
                $legacyImported = true;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $welcomePath = null;
        if ($spaces->tree($user) === []) {
            $welcomePath = $spaces->createNote($user, '', app()->getLocale() === 'es' ? 'Bienvenida' : 'Welcome');
            $welcomeContent = $this->welcomeNoteContent();
            $spaces->write($user, $welcomePath, $welcomeContent);
            $history->record($user, $welcomePath, $welcomeContent);
        }

        Auth::login($user);
        $request->session()->regenerate();

        try {
            Mail::to($user->email)->send(new WelcomeToMdNotes($user, app()->getLocale()));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $welcomePath
            ? redirect()->route('notes.show', ['path' => $welcomePath])->with('status', __('ui.account_created'))
            : redirect()->route('notes.index')->with('status', $legacyImported
                ? __('ui.account_created_legacy_imported')
                : __('ui.account_created'));
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

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8', 'max:128'],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

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
