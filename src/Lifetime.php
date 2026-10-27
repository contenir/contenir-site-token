<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use Psr\Clock\ClockInterface;

use function is_int;

/**
 * A token's lifetime: at most its type's longest, begun and not yet ended, give or take the
 * clocks' leeway.
 *
 * @internal
 */
final readonly class Lifetime
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return array{int, int} The token's issue and expiry times
     *
     * @throws RefusedToken
     */
    public function of(array $payload, TokenType $type): array
    {
        /** @var mixed $issuedAt */
        $issuedAt = $payload['iat'] ?? null;
        /** @var mixed $expiresAt */
        $expiresAt = $payload['exp'] ?? null;
        if (
            ! is_int($issuedAt)
            || ! is_int($expiresAt)
            || $expiresAt < $issuedAt
            || ($expiresAt - $issuedAt) > $type->maxLifetime
        ) {
            throw new RefusedToken(Refusal::BadLifetime);
        }

        $now = $this->clock->now()->getTimestamp();
        if ($issuedAt > ($now + TokenType::LEEWAY)) {
            throw new RefusedToken(Refusal::IssuedInFuture);
        }
        if (($expiresAt + TokenType::LEEWAY) < $now) {
            throw new RefusedToken(Refusal::Expired);
        }

        return [$issuedAt, $expiresAt];
    }
}
