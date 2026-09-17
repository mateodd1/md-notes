<?php

namespace Tests\Feature;

use App\Models\ProfileVerificationCode;
use App\Models\User;
use App\Services\ProfileVerificationCodes;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileVerificationCodesTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
    }

    public function test_password_verification_code_stops_accepting_attempts_after_five_failures(): void
    {
        $user = User::query()->create([
            'name' => 'Mateo',
            'email' => 'mateo@example.test',
            'password' => 'una-clave-segura',
        ]);
        ProfileVerificationCode::query()->create([
            'user_id' => $user->id,
            'purpose' => ProfileVerificationCodes::PASSWORD,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
        ]);
        $codes = $this->app->make(ProfileVerificationCodes::class);

        foreach (range(1, 5) as $attempt) {
            $this->assertFalse($codes->verify($user, ProfileVerificationCodes::PASSWORD, '000000'));
        }

        $this->assertFalse($codes->verify($user, ProfileVerificationCodes::PASSWORD, '123456'));
        $this->assertDatabaseHas('profile_verification_codes', ['user_id' => $user->id]);
    }
}
