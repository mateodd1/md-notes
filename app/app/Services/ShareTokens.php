<?php

namespace App\Services;

use App\Models\SharedNote;
use Illuminate\Support\Facades\Crypt;

class ShareTokens
{
    public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    public const ROUTE_PATTERN = '(?:[23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz]{6}|[0123456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5}|[0123456789ABCDEFGHJKLMNPQRSTUVWXYZ]{7})';

    public function generate(): string
    {
        $token = '';
        for ($index = 0; $index < 6; $index++) {
            $token .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $token;
    }

    /** @return array{token: null, token_hash: string, token_encrypted: string} */
    public function storedAttributes(string $token): array
    {
        return [
            'token' => null,
            'token_hash' => $this->digest($token),
            'token_encrypted' => Crypt::encryptString($token),
        ];
    }

    public function digest(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    /** Resolves protected tokens and the plaintext links created before token storage was hardened. */
    public function find(string $token): ?SharedNote
    {
        return SharedNote::query()->with('user')
            ->where('token_hash', $this->digest($token))
            ->first()
            ?? SharedNote::query()->with('user')->where('token', $token)->first();
    }

    public function exists(string $token): bool
    {
        return SharedNote::query()->where('token_hash', $this->digest($token))->exists()
            || SharedNote::query()->where('token', $token)->exists();
    }

    public function publicUrl(string $token): string
    {
        return rtrim((string) config('md-notes.canonical_url'), '/').'/share/'.rawurlencode($token);
    }
}
