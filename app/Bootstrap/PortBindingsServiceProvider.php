<?php

namespace App\Bootstrap;

use App\Adapters\Outbound\Persistence\DbUserRepository;
use App\Adapters\Outbound\Security\Argon2PasswordHasher;
use App\Adapters\Outbound\Security\JwtTokenIssuer;
use App\Adapters\Outbound\Security\SystemClock;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Article III: the only place where a port meets its adapter.
 * Nothing under app/Adapters is instantiated anywhere else.
 */
final class PortBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, fn () => new SystemClock((int) config('library.clock_offset_days')));
        $this->app->singleton(PasswordHasher::class, Argon2PasswordHasher::class);
        $this->app->singleton(UserRepository::class, DbUserRepository::class);
        $this->app->singleton(TokenIssuer::class, fn ($app) => new JwtTokenIssuer(
            (string) config('library.jwt_secret'),
            (int) config('library.jwt_ttl'),
            $app->make(Clock::class),
        ));
    }
}
