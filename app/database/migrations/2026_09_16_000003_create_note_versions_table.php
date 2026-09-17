<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->longText('content');
            $table->timestamps();

            $table->index(['user_id', 'path', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_versions');
    }
};
