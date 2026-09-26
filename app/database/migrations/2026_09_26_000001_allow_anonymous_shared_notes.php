<?php

use App\Models\SharedNote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_notes', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->longText('content')->nullable();
            $table->unsignedInteger('content_bytes')->default(0);
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        // Anonymous rows cannot be represented by the old non-nullable schema.
        // Do not silently delete public notes during a rollback.
        if (SharedNote::query()->whereNull('user_id')->exists()) {
            throw new RuntimeException('Remove anonymous shared notes before rolling back this migration.');
        }

        Schema::table('shared_notes', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'expires_at']);
            $table->dropColumn(['content', 'content_bytes']);
        });

        Schema::table('shared_notes', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
