<?php

declare(strict_types=1);

namespace Tests\Health;

use App\Health\HealthChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryCache;
use Tests\Fakes\InMemoryMessageRepository;
use Tests\Fakes\ThrowingCache;

final class HealthCheckerTest extends TestCase
{
    #[Test]
    public function liveness_is_always_ok(): void
    {
        self::assertSame(['status' => 'ok'], (new HealthChecker())->liveness());
    }

    #[Test]
    public function readiness_is_true_when_db_and_redis_are_up(): void
    {
        $result = (new HealthChecker())->readiness(
            new InMemoryMessageRepository(healthy: true),
            new InMemoryCache(healthy: true),
        );

        self::assertTrue($result['ready']);
        self::assertSame(['db' => true, 'redis' => true], $result['checks']);
    }

    #[Test]
    public function readiness_is_false_when_db_is_down(): void
    {
        $result = (new HealthChecker())->readiness(
            new InMemoryMessageRepository(healthy: false),
            new InMemoryCache(healthy: true),
        );

        self::assertFalse($result['ready']);
        self::assertFalse($result['checks']['db']);
        self::assertTrue($result['checks']['redis']);
    }

    #[Test]
    public function readiness_is_false_when_redis_is_down(): void
    {
        $result = (new HealthChecker())->readiness(
            new InMemoryMessageRepository(healthy: true),
            new InMemoryCache(healthy: false),
        );

        self::assertFalse($result['ready']);
        self::assertTrue($result['checks']['db']);
        self::assertFalse($result['checks']['redis']);
    }

    #[Test]
    public function readiness_treats_a_throwing_probe_as_unavailable(): void
    {
        $result = (new HealthChecker())->readiness(
            new InMemoryMessageRepository(healthy: true),
            new ThrowingCache(),
        );

        self::assertFalse($result['ready']);
        self::assertFalse($result['checks']['redis']);
    }
}
