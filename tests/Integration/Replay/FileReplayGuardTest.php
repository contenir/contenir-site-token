<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Integration\Replay;

use Contenir\SiteToken\Replay\FileReplayGuard;
use Contenir\SiteToken\Tests\TestAsset\FrozenClock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function glob;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function touch;
use function unlink;

/**
 * Remembering accepted token ids as files in a directory every process on the server shares.
 */
#[Group('integration')]
final class FileReplayGuardTest extends TestCase
{
    private string $directory;

    #[Test]
    public function acceptsEachIdOnce(): void
    {
        $guard = new FileReplayGuard("{$this->directory}/", new FrozenClock());

        $this->assertSame(
            [true, false, true],
            [$guard->claim('a', 1_792_236_300), $guard->claim('a', 1_792_236_300), $guard->claim('b', 1_792_236_300)],
        );
    }

    #[Test]
    public function forgetsEveryLapsedIdWhicheverOrderTheyLieIn(): void
    {
        $clock = new FrozenClock();
        $guard = new FileReplayGuard($this->directory, $clock);
        $guard->claim('a', 1_792_236_600);
        $guard->claim('b', 1_792_236_300);

        $clock->advance(331);

        $this->assertSame([true, false], [$guard->claim('b', 1_792_236_631), $guard->claim('a', 1_792_236_600)]);
    }

    #[Test]
    public function keepsGuardsWithOtherPrefixesApart(): void
    {
        (new FileReplayGuard($this->directory, new FrozenClock(), 'preview-'))->claim('a', 1_792_236_300);

        $this->assertSame(
            [true, ["{$this->directory}/contenir-site-token-a", "{$this->directory}/preview-a"]],
            [
                (new FileReplayGuard($this->directory, new FrozenClock()))->claim('a', 1_792_236_300),
                glob("{$this->directory}/*"),
            ],
        );
    }

    #[Test]
    public function leavesFilesThatAreNotItsOwnAlone(): void
    {
        file_put_contents("{$this->directory}/someone-else", data: 'kept');
        touch("{$this->directory}/someone-else", mtime: 1_000_000_000);

        (new FileReplayGuard($this->directory, new FrozenClock()))->claim('a', 1_792_236_300);

        $this->assertFileExists("{$this->directory}/someone-else");
    }

    #[Test]
    public function refusesWithoutItsDirectory(): void
    {
        $this->assertFalse((new FileReplayGuard("{$this->directory}/missing", new FrozenClock()))->claim(
            'a',
            1_792_236_300,
        ));
    }

    #[Test]
    public function remembersAnIdUntilItsTokenCanNoLongerBeAccepted(): void
    {
        $clock = new FrozenClock();
        $guard = new FileReplayGuard($this->directory, $clock);
        $guard->claim('a', 1_792_236_300);

        $clock->advance(330);
        $stillHeld = ! $guard->claim('a', 1_792_236_300);
        $clock->advance(1);

        $this->assertSame([true, true], [$stillHeld, $guard->claim('a', 1_792_236_631)]);
    }

    #[Test]
    public function sharesWhatItRemembersWithAnotherGuardOnTheDirectory(): void
    {
        (new FileReplayGuard($this->directory, new FrozenClock()))->claim('a', 1_792_236_300);

        $this->assertFalse((new FileReplayGuard($this->directory, new FrozenClock()))->claim('a', 1_792_236_300));
    }

    protected function setUp(): void
    {
        $temp            = sys_get_temp_dir();
        $suffix          = bin2hex(random_bytes(length: 6));
        $this->directory = "{$temp}/contenir-site-token-test-{$suffix}";
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $paths = glob("{$this->directory}/*");
        foreach (false === $paths ? [] : $paths as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }
}
