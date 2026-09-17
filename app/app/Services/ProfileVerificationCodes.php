<?php

namespace App\Services;

use App\Mail\ProfileVerificationCodeMail;
use App\Models\ProfileVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProfileVerificationCodes
{
    public const PASSWORD = 'password';

    public const EMAIL = 'email';

    public function send(User $user, string $purpose, ?string $target = null): void
    {
        $code = (string) random_int(100000, 999999);

        $record = ProfileVerificationCode::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'purpose' => $purpose],
            [
                'target' => $target,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(15),
            ],
        );

        try {
            Mail::to($target ?: $user->email)->send(new ProfileVerificationCodeMail(
                $user,
                $purpose,
                $code,
                app()->getLocale(),
            ));
        } catch (Throwable $exception) {
            $record->delete();

            throw $exception;
        }
    }

    public function verify(User $user, string $purpose, string $code, ?string $target = null): bool
    {
        $query = ProfileVerificationCode::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose);

        $target === null ? $query->whereNull('target') : $query->where('target', $target);
        $record = $query->first();

        if (! $record || $record->expires_at->isPast() || ! Hash::check($code, $record->code_hash)) {
            if ($record?->expires_at->isPast()) {
                $record->delete();
            }

            return false;
        }

        $record->delete();

        return true;
    }
}
