<?php

namespace App\Application\Ports\Outbound;

/** Everything inside `run` is one transaction: taking a copy and creating the line are one operation. */
interface UnitOfWork
{
    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed;
}
