<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Replay;

use Contenir\SiteToken\TokenType;
use Override;
use Psr\Clock\ClockInterface;
use RuntimeException;
use SplFileInfo;
use SplFileObject;

use function glob;
use function is_file;
use function touch;
use function unlink;

/**
 * A replay guard in a directory, which every process on one server shares: each accepted token
 * leaves a file named by its prefix and id, created only if none exists, so two requests can never
 * both accept the same token. The file is stamped with the token's expiry and cleared once the
 * token could no longer be accepted. The directory must exist; the guard touches only files with
 * its prefix, so the system's temporary directory serves. Servers that share no disk need
 * CacheReplayGuard.
 *
 * @api
 */
final readonly class FileReplayGuard implements ReplayGuardInterface
{
    /**
     * @param string $prefix Starts each file's name, so protocols sharing a directory keep apart
     */
    public function __construct(
        private string $directory,
        private ClockInterface $clock,
        private string $prefix = 'contenir-site-token-',
    ) {}

    #[Override]
    public function claim(string $tokenId, int $expiresAt): bool
    {
        $this->forgetLapsed();

        $path = "{$this->directory}/{$this->prefix}{$tokenId}";
        try {
            new SplFileObject($path, mode: 'x');
        } catch (RuntimeException) {
            return false;
        }

        return touch($path, $expiresAt);
    }

    private function forgetLapsed(): void
    {
        $now   = $this->clock->now()->getTimestamp();
        $paths = glob("{$this->directory}/{$this->prefix}*");
        foreach (false === $paths ? [] : $paths as $path) {
            try {
                /** @var int $stamped SplFileInfo throws, rather than answering false, for a file gone */
                $stamped = (new SplFileInfo($path))->getMTime();
            } catch (RuntimeException) {
                continue;
            }

            if (($stamped + TokenType::LEEWAY) < $now && is_file($path)) {
                unlink($path);
            }
        }
    }
}
