<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Unit\Replay;

use Contenir\SiteToken\Replay\InMemoryReplayGuard;
use Contenir\SiteToken\Tests\TestAsset\FrozenClock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Remembering accepted token ids in memory until their tokens could no longer be accepted.
 */
#[Group('unit')]
final class InMemoryReplayGuardTest extends TestCase
{
    #[Test]
    public function acceptsEachIdOnce(): void
    {
        $guard = new InMemoryReplayGuard(new FrozenClock());

        $this->assertSame([true, false, true], [
            $guard->claim('a', 1_792_236_300),
            $guard->claim('a', 1_792_236_300),
            $guard->claim('b', 1_792_236_300),
        ]);
    }

    #[Test]
    public function forgetsEveryLapsedIdWhicheverOrderTheyCameIn(): void
    {
        $clock = new FrozenClock();
        $guard = new InMemoryReplayGuard($clock);
        $guard->claim('later', 1_792_236_600);
        $guard->claim('sooner', 1_792_236_300);

        $clock->advance(331);

        $this->assertSame([true, false], [
            $guard->claim('sooner', 1_792_236_631),
            $guard->claim('later', 1_792_236_600),
        ]);
    }

    #[Test]
    public function remembersAnIdUntilItsTokenCanNoLongerBeAccepted(): void
    {
        $clock = new FrozenClock();
        $guard = new InMemoryReplayGuard($clock);
        $guard->claim('a', 1_792_236_300);

        $clock->advance(330);
        $stillHeld = ! $guard->claim('a', 1_792_236_300);
        $clock->advance(1);

        $this->assertSame([true, true], [$stillHeld, $guard->claim('a', 1_792_236_300)]);
    }
}
