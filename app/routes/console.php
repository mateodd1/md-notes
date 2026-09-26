<?php

use App\Models\SharedNote;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shares:prune-anonymous', function (): void {
    $count = SharedNote::query()->whereNull('user_id')
        ->where('expires_at', '<=', now())->delete();

    $this->info("Deleted {$count} expired anonymous shared notes.");
})->purpose('Remove anonymous notes after their 30-day expiry');

Schedule::command('shares:prune-anonymous')->hourly();
