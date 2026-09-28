<?php

use App\Models\SharedNote;
use App\Services\NoteVersionHistory;
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

Artisan::command('versions:prune', function (NoteVersionHistory $history): void {
    $history->pruneExpired();

    $this->info('Expired note versions and unreferenced attachments pruned.');
})->purpose('Remove note versions older than 7 days and their unused attachments');

Schedule::command('versions:prune')->hourly()->withoutOverlapping();
