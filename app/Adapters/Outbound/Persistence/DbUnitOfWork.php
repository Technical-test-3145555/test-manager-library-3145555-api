<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\UnitOfWork;
use Illuminate\Support\Facades\DB;

final class DbUnitOfWork implements UnitOfWork
{
    public function run(callable $work): mixed
    {
        return DB::transaction($work);
    }
}
