<?php

namespace App\Console\Commands;

use App\Application\Services\BootstrapAdminService;
use Illuminate\Console\Command;

final class BootstrapAdminCommand extends Command
{
    protected $signature = 'app:bootstrap-admin';

    protected $description = 'Create the initial admin from ADMIN_USERNAME and ADMIN_PASSWORD if it does not exist';

    public function handle(BootstrapAdminService $service): int
    {
        $username = (string) config('library.admin_username');
        $password = (string) config('library.admin_password');

        if ($username === '' || $password === '') {
            $this->error('ADMIN_USERNAME and ADMIN_PASSWORD must be set in the environment.');

            return self::FAILURE;
        }

        $this->info($service->ensure($username, $password) ? 'Admin created.' : 'Admin already exists.');

        return self::SUCCESS;
    }
}
