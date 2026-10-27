<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Unit\Replay;

use Contenir\SiteToken\Replay\CacheReplayGuard;
use Contenir\SiteToken\Tests\TestAsset\ArrayCache;
use Contenir\SiteToken\Tests\TestAsset\FrozenClock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Remembering accepted token ids in a PSR-16 cache that servers share.
 */
#[Group('unit')]
final class CacheReplayGuardTest extends TestCase
{
    #[Test]
    public function acceptsEachIdOnce(): void
    {
        $guard = new CacheReplayGuard(new ArrayCache(), new FrozenClock());

        $this->assertSame([true, false], [$guard->claim('a', 1_792_236_300), $guard->claim('a', 1_792_236_300)]);
    }

    #[Test]
    public function keepsAnIdASecondOnceItsTokenHasJustLapsed(): void
    {
        $cache = new ArrayCache();
        (new CacheReplayGuard($cache, new FrozenClock()))->claim('a', 1_792_235_970);

        $this->assertSame(['contenir_site_token_a' => 1], $cache->ttls);
    }

    #[Test]
    public function keepsAnIdAtLeastASecond(): void
    {
        $cache = new ArrayCache();
        (new CacheReplayGuard($cache, new FrozenClock()))->claim('a', 1_792_235_000);

        $this->assertSame(['contenir_site_token_a' => 1], $cache->ttls);
    }

    #[Test]
    public function keepsAnIdForTheLastSecondItCouldBeAccepted(): void
    {
        $cache = new ArrayCache();
        (new CacheReplayGuard($cache, new FrozenClock()))->claim('a', 1_792_235_971);

        $this->assertSame(['contenir_site_token_a' => 1], $cache->ttls);
    }

    #[Test]
    public function keepsAnIdUntilItsTokenCanNoLongerBeAccepted(): void
    {
        $cache = new ArrayCache();
        (new CacheReplayGuard($cache, new FrozenClock(), 'site_'))->claim('a', 1_792_236_300);

        $this->assertSame([['site_a' => 1_792_236_300], ['site_a' => 330]], [$cache->values, $cache->ttls]);
    }

    #[Test]
    public function refusesAnIdTheCacheCannotRemember(): void
    {
        $guard = new CacheReplayGuard(new ArrayCache('contenir_site_token_a'), new FrozenClock());

        $this->assertFalse($guard->claim('a', 1_792_236_300));
    }
}
