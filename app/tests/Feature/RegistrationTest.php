<?php

namespace Tests\Feature;

use App\Mail\WelcomeToMdNotes;
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
        $response->assertRedirect(route('notes.show', ['path' => 'Welcome.md']))
            ->assertSessionHas('status', __('ui.account_created'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['Welcome.md'], array_column($spaces->tree($user), 'path'));
        $content = $spaces->read($user, 'Welcome.md');
        $this->assertStringContainsString('# Welcome to md-notes', $content);
        $this->assertDatabaseHas('note_versions', ['user_id' => $user->id, 'path' => 'Welcome.md', 'content' => $content]);
        Mail::assertSent(WelcomeToMdNotes::class, fn (WelcomeToMdNotes $mail): bool => $mail->user->is($user));
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
        $response->assertRedirect(route('notes.show', ['path' => 'Bienvenida.md']));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['Bienvenida.md'], array_column($spaces->tree($user), 'path'));
        $this->assertStringContainsString('# Bienvenido a md-notes', $spaces->read($user, 'Bienvenida.md'));
        $this->assertSame('# Private notes', $spaces->read($owner, 'Private.md'));
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

    public function test_registration_requires_a_valid_email_a_three_character_name_and_an_eight_character_password(): void
    {
        $this->withSession(['_token' => 'test-token'])->post(route('register.store'), [
            '_token' => 'test-token',
            'name' => 'Al',
            'email' => 'not-an-email',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ])->assertSessionHasErrors(['name', 'email', 'password']);
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
