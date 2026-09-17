<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NoteSpace;
use App\Mail\WelcomeToMdNotes;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
    }

    public function test_first_registered_account_gets_the_legacy_import(): void
    {
        Mail::fake();
        $spaces = Mockery::mock(NoteSpace::class);
        $spaces->shouldReceive('root')->once()->andReturn('/tmp/notes');
        $spaces->shouldReceive('importLegacySpace')->once();
        $spaces->shouldReceive('tree')->once()->andReturn([['path' => 'Importada.md']]);
        $this->app->instance(NoteSpace::class, $spaces);

        $response = $this->withSession(['_token' => 'test-token'])->post(route('register.store'), [
            '_token' => 'test-token',
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
            'password_confirmation' => 'una-clave-segura',
        ]);

        $response->assertRedirect(route('notes.index'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'mateo@example.test']);
        Mail::assertSent(WelcomeToMdNotes::class, fn (WelcomeToMdNotes $mail): bool => $mail->user->email === 'mateo@example.test');
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
        $this->get('/login')->assertOk();
        $this->get('/singup')->assertOk();
        $this->get('/acceder')->assertNotFound();
        $this->get('/registro')->assertNotFound();
    }
}
