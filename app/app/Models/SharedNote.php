<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['user_id', 'path', 'token', 'token_hash', 'token_encrypted', 'expires_at', 'content', 'content_bytes'])]
#[Hidden(['token_hash', 'token_encrypted'])]
class SharedNote extends Model
{
    public function getTokenAttribute(?string $value): ?string
    {
        $encrypted = $this->attributes['token_encrypted'] ?? null;

        return $encrypted === null ? $value : Crypt::decryptString($encrypted);
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'content_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
