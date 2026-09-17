<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ApiTokens
{
    /** @return array{ApiToken, string} */
    public function create(User $user, string $name): array
    {
        $token = 'mdn_'.bin2hex(random_bytes(32));

        $record = $user->apiTokens()->create([
            'name' => trim($name),
            'token_hash' => hash('sha256', $token),
        ]);

        return [$record, $token];
    }

    /** @return Collection<int, ApiToken> */
    public function forUser(User $user): Collection
    {
        return $user->apiTokens()->latest()->get();
    }
}
