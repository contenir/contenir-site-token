<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Replay;

use Contenir\SiteToken\TokenType;
use Override;
use Psr\Clock\ClockInterface;

use function array_key_exists;

/**
 * A replay guard for one long-running process, or for tests: ids live in memory and are forgotten
 * once their tokens could no longer be accepted.
 *
 * @api
 */
final class InMemoryReplayGuard implements ReplayGuardInterface
{
    /** @var array<string, int> Token id => expiry */
    private array $claimed = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    #[Override]
    public function claim(string $tokenId, int $expiresAt): bool
    {
        $now = $this->clock->now()->getTimestamp();
        foreach ($this->claimed as $id => $expiry) {
            if (($expiry + TokenType::LEEWAY) >= $now) {
                continue;
            }

            unset($this->claimed[$id]);
        }

        if (array_key_exists($tokenId, $this->claimed)) {
            return false;
        }

        $this->claimed[$tokenId] = $expiresAt;

        return true;
    }
}
