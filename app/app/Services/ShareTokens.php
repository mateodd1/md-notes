<?php

namespace App\Services;

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

    public function publicUrl(string $token): string
    {
        return rtrim((string) config('md-notes.canonical_url'), '/').'/share/'.rawurlencode($token);
    }
}
