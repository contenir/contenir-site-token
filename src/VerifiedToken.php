<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use DateTimeImmutable;

/**
 * A token TokenVerifier has accepted: its one-time id, who it names, its lifetime, and the
 * protocol's own claims, everything in the payload besides the shared ones.
 *
 * @api
 */
final readonly class VerifiedToken
{
    /**
     * @param array<array-key, mixed> $claims The protocol's own claims, unchecked
     */
    public function __construct(
        public string $tokenId,
        public string $subject,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public array $claims,
    ) {}
}
