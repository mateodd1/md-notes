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
        $user = $demo->reset();

        $this->info('Cuenta demo restablecida: '.$user->email);

        return self::SUCCESS;
    }
}
