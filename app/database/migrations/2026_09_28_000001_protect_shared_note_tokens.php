<?php

use App\Services\ShareTokens;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_notes', function (Blueprint $table): void {
            $table->char('token_hash', 64)->nullable()->unique();
            $table->text('token_encrypted')->nullable();
        });

        $tokens = app(ShareTokens::class);
        DB::table('shared_notes')->whereNotNull('token')->orderBy('id')->chunkById(100, function ($shares) use ($tokens): void {
            foreach ($shares as $share) {
                DB::table('shared_notes')->where('id', $share->id)->update([
                    'token' => null,
                    'token_hash' => $tokens->digest($share->token),
                    'token_encrypted' => Crypt::encryptString($share->token),
                ]);
            }
        });

        Schema::table('shared_notes', function (Blueprint $table): void {
            $table->string('token', 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('shared_notes')->whereNotNull('token_encrypted')->orderBy('id')->chunkById(100, function ($shares): void {
            foreach ($shares as $share) {
                DB::table('shared_notes')->where('id', $share->id)->update([
                    'token' => Crypt::decryptString($share->token_encrypted),
                ]);
            }
        });

        Schema::table('shared_notes', function (Blueprint $table): void {
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['token_hash', 'token_encrypted']);
            $table->string('token', 7)->nullable(false)->change();
        });
    }
};
