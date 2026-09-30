<?php

namespace App\Console\Commands;

use App\Services\DemoAccount;
use Illuminate\Console\Command;

class ResetDemoAccount extends Command
{
    protected $signature = 'md-notes:reset-demo';

    protected $description = 'Restablece la cuenta y las notas de demostración';

    public function handle(DemoAccount $demo): int
    {
        if (! config('md-notes.demo_enabled')) {
            $this->info('Demo disabled; no changes made.');

            return self::SUCCESS;
        }

        $user = $demo->reset();

        $this->info('Cuenta demo restablecida: '.$user->email);

        return self::SUCCESS;
    }
}
