<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('shared_notes', function (Blueprint $table): void {
            // Share identifiers are case-sensitive: aB2cD3 and Ab2Cd3 are different links.
            $table->string('token', 7)
                ->nullable()
                ->charset('ascii')
                ->collation('ascii_bin')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep binary token comparisons when rolling back later application migrations.
    }
};
