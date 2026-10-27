<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use function is_string;
use function preg_match;

/**
 * The shared claims a signed token must carry besides its lifetime: its type's version, the issuer
 * and audience this site holds, and a well-formed one-time id.
 *
 * @internal
 */
final readonly class Claims
{
    public function __construct(
        private SiteKeys $keys,
    ) {}

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws RefusedToken
     */
    public function check(array $payload, TokenType $type): void
    {
        if ($type->version !== ($payload['ver'] ?? null)) {
            throw new RefusedToken(Refusal::WrongVersion);
        }

        if ($this->keys->issuer !== ($payload['iss'] ?? null) || $this->keys->audience !== ($payload['aud'] ?? null)) {
            throw new RefusedToken(Refusal::WrongParties);
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws RefusedToken
     */
    public function tokenId(array $payload): string
    {
        /** @var mixed $tokenId */
        $tokenId = $payload['jti'] ?? null;
        if (! is_string($tokenId) || 1 !== preg_match('/^[0-9a-f]{32}\\z/', $tokenId)) {
            throw new RefusedToken(Refusal::NoTokenId);
        }

        return $tokenId;
    }
}
