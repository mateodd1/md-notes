<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountExport extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'archive_path',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
        'archive_path',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
