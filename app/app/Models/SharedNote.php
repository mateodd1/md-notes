<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedNote extends Model
{
    protected $fillable = [
        'user_id',
        'path',
        'token',
        'expires_at',
        'content',
        'content_bytes',
    ];

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
