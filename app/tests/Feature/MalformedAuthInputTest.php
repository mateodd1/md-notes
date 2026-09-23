<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MalformedAuthInputTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
        Mail::fake();
    }

    public static function malformedForms(): array
    {
        $password = array_fill(0, 8, 'part');

        return [
            'registration name' => ['register', 'register.store', ['name' => ['Mateo']], 'name'],
            'registration email' => ['register', 'register.store', ['email' => ['bad@example.test']], 'email'],
            'registration password' => ['register', 'register.store', ['password' => $password, 'password_confirmation' => $password], 'password'],
            'login email' => ['login', 'login.store', ['email' => ['bad@example.test']], 'email'],
            'forgotten password email' => ['password.request', 'password.email', ['email' => ['bad@example.test']], 'email'],
            'reset email' => ['password.reset', 'password.update', ['email' => ['bad@example.test']], 'email'],
            'reset password' => ['password.reset', 'password.update', ['password' => $password, 'password_confirmation' => $password], 'password'],
        ];
    }

    #[DataProvider('malformedForms')]
    public function test_malformed_fields_return_to_a_working_form(string $formRoute, string $submitRoute, array $overrides, string $field): void
    {
        $formUrl = route($formRoute, $formRoute === 'password.reset' ? ['token' => 'test-reset-token'] : []);
        $data = array_replace([
            '_token' => 'test-token',
            'token' => 'test-reset-token',
            'name' => 'Mateo',
            'email' => 'malformed@example.test',
            'password' => 'a-valid-test-password',
            'password_confirmation' => 'a-valid-test-password',
        ], $overrides);

        $this->followingRedirects()->withHeader('referer', $formUrl)->withSession(['_token' => 'test-token'])
            ->post(route($submitRoute), $data)
            ->assertOk()
            ->assertViewHas('errors', fn ($errors) => $errors->has($field));

        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingSent();
    }

    public function test_profile_rejects_array_names_without_breaking_the_form(): void
    {
        $user = User::factory()->create(['name' => 'Original']);

        $this->followingRedirects()->actingAs($user)->withHeader('referer', route('profile.edit'))
            ->withSession(['_token' => 'test-token'])
            ->patch(route('profile.name'), ['_token' => 'test-token', 'name' => ['invalid']])
            ->assertOk()
            ->assertViewHas('errors', fn ($errors) => $errors->has('name'))
            ->assertSee('value="Original"', false);

        $this->assertSame('Original', $user->fresh()->name);
    }

    public function test_reset_form_handles_array_email_in_the_url(): void
    {
        $this->get(route('password.reset', ['token' => 'test-reset-token', 'email' => ['invalid']]))
            ->assertOk();
    }
}
