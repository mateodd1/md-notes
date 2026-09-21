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
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('auth_version')->default(0);
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('shared_notes', function (Blueprint $table): void {
                $table->string('token', 7)->charset('ascii')->collation('ascii_bin')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('auth_version');
        });

        // Keep binary token comparisons: reverting them can merge existing links.
    }
};
