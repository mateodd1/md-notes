<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordSecurity
{
    public function change(User $user, string $password): void
    {
        $hash = Hash::make($password);
        DB::transaction(function () use ($user, $hash): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $locked->forceFill([
                'password' => $hash,
                'remember_token' => Str::random(60),
                'auth_version' => $locked->auth_version + 1,
            ])->save();
            $user->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
