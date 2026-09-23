<?php

namespace Tests\Feature;

use App\Mail\AccountDeletedMail;
use App\Mail\WelcomeToMdNotes;
use App\Mail\ProfileVerificationCodeMail;
use App\Models\User;
use App\Services\NoteSpace;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
    }

    public function test_first_registered_account_starts_with_only_its_welcome_note(): void
    {
        Mail::fake();
        $spaces = app(NoteSpace::class);

        $response = $this->withHeader('Accept-Language', 'en')->withSession(['_token' => 'test-token'])->post(route('register.store'), [
            '_token' => 'test-token',
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'password_confirmation' => 'una-clave-segura',
        ]);

        $user = User::query()->where('email', 'mateo@example.test')->firstOrFail();
        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->get(route('notes.index'))->assertRedirect(route('verification.notice'));
        $this->assertSame(['Welcome.md'], array_column($spaces->tree($user), 'path'));
        $content = $spaces->read($user, 'Welcome.md');
        $this->assertStringContainsString('# Welcome to md-notes', $content);
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'path' => 'Welcome.md', 'content' => $content]);
        Mail::assertNotSent(WelcomeToMdNotes::class);
        $code = null;
        Mail::assertSent(ProfileVerificationCodeMail::class, function (ProfileVerificationCodeMail $mail) use ($user, &$code): bool {
            $code = $mail->code;

            return $mail->user->is($user) && $mail->purpose === 'account';
        });
        $this->withSession(['_token' => 'verify-token'])->post(route('verification.verify'), ['_token' => 'verify-token', 'code' => $code])->assertRedirect(route('notes.show', ['path' => 'Welcome.md']));
        $this->assertNotNull($user->fresh()->email_verified_at);
        Mail::assertSent(WelcomeToMdNotes::class);
    }

    public function test_a_new_account_gets_a_localized_welcome_note_without_other_users_files(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $spaces = app(NoteSpace::class);
        $spaces->createNote($owner, '', 'Private');
        $spaces->write($owner, 'Private.md', '# Private notes');

        $response = $this->withHeader('Accept-Language', 'es')->withSession(['_token' => 'test-token'])->post(route('register.store'), [
            '_token' => 'test-token',
            'name' => 'Nuevo usuario',
            'email' => 'new@example.test',
            'password' => 'una-clave-segura',
            'password_confirmation' => 'una-clave-segura',
        ]);

        $user = User::query()->where('email', 'new@example.test')->firstOrFail();
        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['Bienvenida.md'], array_column($spaces->tree($user), 'path'));
        $this->assertStringContainsString('# Bienvenido a md-notes', $spaces->read($user, 'Bienvenida.md'));
        $this->assertSame('# Private notes', $spaces->read($owner, 'Private.md'));
        $code = null;
        Mail::assertSent(ProfileVerificationCodeMail::class, function (ProfileVerificationCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->withSession(['_token' => 'verify-token'])->post(route('verification.verify'), ['_token' => 'verify-token', 'code' => $code])->assertRedirect(route('notes.show', ['path' => 'Bienvenida.md']));
        $this->get(route('notes.show', ['path' => 'Private.md']))->assertNotFound();
        Mail::assertSent(WelcomeToMdNotes::class, fn (WelcomeToMdNotes $mail): bool => $mail->user->is($user));
    }

    public function test_demo_credentials_can_sign_in_without_registering(): void
    {
        User::query()->create([
            'name' => 'Cuenta demo',
            'email' => 'demo@demo',
            'password' => 'demo',
            'is_admin' => false,
        ]);

        $this->withSession(['_token' => 'test-token'])->post(route('login.store'), [
            '_token' => 'test-token',
            'email' => 'demo@demo',
            'password' => 'demo',
        ])->assertRedirect(route('notes.index'));

        $this->assertAuthenticated();
    }

    public function test_unverified_account_can_request_a_new_code_but_cannot_access_notes(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->withSession(['_token' => 'test-token'])->actingAs($user)
            ->get(route('notes.index'))->assertRedirect(route('verification.notice'));
        $this->withSession(['_token' => 'test-token'])->post(route('verification.verify'), [
            '_token' => 'test-token',
            'code' => '123456',
        ])->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->withSession(['_token' => 'test-token'])->post(route('verification.send'), [
            '_token' => 'test-token',
        ])->assertSessionHas('status', __('ui.verification_code_sent'));
        Mail::assertSent(ProfileVerificationCodeMail::class);
    }

    public function test_unverified_login_goes_to_the_code_form(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'a-long-test-password']);

        $this->withSession(['_token' => 'test-token'])->post(route('login.store'), [
            '_token' => 'test-token',
            'email' => $user->email,
            'password' => 'a-long-test-password',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_account_deletion_sends_confirmation_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => 'a-long-test-password']);

        $this->withSession(['_token' => 'test-token'])->actingAs($user)->delete(route('profile.destroy'), [
            '_token' => 'test-token',
            'current_password' => 'a-long-test-password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Mail::assertSent(AccountDeletedMail::class, fn (AccountDeletedMail $mail): bool => $mail->recipientName === $user->name);
    }

    public function test_registration_requires_a_valid_email_a_three_character_name_and_an_eight_character_password(): void
    {
        $this->followingRedirects()->withHeader('referer', route('register'))->withSession(['_token' => 'test-token'])->post(route('register.store'), [
            '_token' => 'test-token',
            'name' => 'Al',
            'email' => 'not-an-email',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ])
            ->assertOk()
            ->assertSee('class="auth-intro"', false)
            ->assertSee('id="registration-toast"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('md-notes-register.js')
            ->assertSee('novalidate', false)
            ->assertViewHas('errors', fn ($errors) => $errors->has(['name', 'email', 'password']))
            ->assertDontSee('class="errors"', false);
    }

    public function test_account_names_allow_accented_letters_but_reject_special_characters(): void
    {
        Mail::fake();

        $this->withSession(['_token' => 'test-token'])->post(route('register.store'), [
            '_token' => 'test-token',
            'name' => 'Mateo_!',
            'email' => 'invalid-name@example.test',
            'password' => 'una-clave-segura',
            'password_confirmation' => 'una-clave-segura',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-name@example.test']);

        $this->withSession(['_token' => 'test-token'])->post(route('register.store'), [
            '_token' => 'test-token',
            'name' => ['Mateo'],
            'email' => 'invalid-name@example.test',
            'password' => 'una-clave-segura',
            'password_confirmation' => 'una-clave-segura',
        ])->assertSessionHasErrors('name');

        $this->withSession(['_token' => 'test-token'])->post(route('register.store'), [
            '_token' => 'test-token',
            'name' => 'María Núñez 2',
            'email' => 'valid-name@example.test',
            'password' => 'una-clave-segura',
            'password_confirmation' => 'una-clave-segura',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', ['email' => 'valid-name@example.test', 'name' => 'María Núñez 2']);
    }

    public function test_profile_name_uses_the_same_character_rule(): void
    {
        $user = User::factory()->create(['name' => 'Nombre Inicial']);

        $this->withSession(['_token' => 'test-token'])->actingAs($user)->patch(route('profile.name'), [
            '_token' => 'test-token',
            'name' => 'Mateo_!',
        ])->assertSessionHasErrors('name');

        $this->assertSame('Nombre Inicial', $user->fresh()->name);

        $this->withSession(['_token' => 'test-token'])->patch(route('profile.name'), [
            '_token' => 'test-token',
            'name' => ['Mateo'],
        ])->assertSessionHasErrors('name');

        $this->withSession(['_token' => 'test-token'])->patch(route('profile.name'), [
            '_token' => 'test-token',
            'name' => 'María Núñez 2',
        ])->assertSessionHasNoErrors();

        $this->assertSame('María Núñez 2', $user->fresh()->name);
    }

    public function test_demo_profile_settings_are_disabled_server_side(): void
    {
        $demo = User::query()->create([
            'name' => 'Cuenta demo',
            'email' => 'demo@demo',
            'password' => 'demo',
            'is_admin' => false,
        ]);

        $this->actingAs($demo)->get(route('profile.edit'))
            ->assertRedirect(route('notes.index'))
            ->assertSessionHas('status', __('ui.demo_settings_disabled'));
    }

    public function test_authentication_routes_use_the_new_english_paths(): void
    {
        $this->get('/login')->assertOk()->assertSee('assets/md-notes-google.css', false);
        $this->get('/signup')->assertOk()->assertSee(__('ui.confirm_password'));
        $this->get('/singup')->assertNotFound();
        $this->get('/acceder')->assertNotFound();
        $this->get('/registro')->assertNotFound();
    }
}
