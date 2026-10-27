<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use Contenir\SiteToken\Replay\ReplayGuardInterface;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

use function array_diff_key;
use function hash_equals;
use function hash_hmac;
use function is_string;

/**
 * A site's side: checks a token of one protocol's type, in this order, stopping at the first
 * failure, and gives back what it carries:
 *
 * 1. something was sent, no larger than the type allows;
 * 2. three base64url segments, a JSON header and payload;
 * 3. `alg` is HS256, `typ` is the type's, `kid` is a key this site holds;
 * 4. the signature matches, compared in constant time;
 * 5. `ver` is the type's, `iss` and `aud` are this site's, `iat` and `exp` are within bounds;
 * 6. `jti` is 32 hex digits, not seen before.
 *
 * @api
 */
final readonly class TokenVerifier
{
    private Claims $claims;

    private Lifetime $lifetime;

    public function __construct(
        private SiteKeys $keys,
        ClockInterface $clock,
        private ReplayGuardInterface $replay,
    ) {
        $this->claims   = new Claims($keys);
        $this->lifetime = new Lifetime($clock);
    }

    /**
     * @throws RefusedToken
     */
    public function verify(TokenType $type, #[SensitiveParameter] ?string $token): VerifiedToken
    {
        $parsed   = ParsedToken::parse($token, $type->maxBytes);
        $expected = hash_hmac('sha256', $parsed->signed, $this->secretFor($parsed->header, $type), binary: true);
        if (null === $parsed->signature || ! hash_equals($expected, $parsed->signature)) {
            throw new RefusedToken(Refusal::BadSignature);
        }

        $payload = $parsed->payload;
        $this->claims->check($payload, $type);
        [$issuedAt, $expiresAt] = $this->lifetime->of($payload, $type);
        $tokenId = $this->claims->tokenId($payload);
        if (! $this->replay->claim($tokenId, $expiresAt)) {
            throw new RefusedToken(Refusal::Replayed);
        }

        /** @var mixed $subject */
        $subject = $payload['sub'] ?? null;

        return new VerifiedToken(
            $tokenId,
            is_string($subject) ? $subject : '',
            (new DateTimeImmutable())->setTimestamp($issuedAt),
            (new DateTimeImmutable())->setTimestamp($expiresAt),
            array_diff_key($payload, TokenSigner::RESERVED),
        );
    }

    /**
     * @param array<array-key, mixed> $header
     *
     * @throws RefusedToken
     */
    private function secretFor(array $header, TokenType $type): string
    {
        if ('HS256' !== ($header['alg'] ?? null) || $type->name !== ($header['typ'] ?? null)) {
            throw new RefusedToken(Refusal::WrongSigning);
        }

        /** @var mixed $keyId */
        $keyId  = $header['kid'] ?? null;
        $secret = is_string($keyId) ? $this->keys->secret($keyId) : null;

        return $secret ?? throw new RefusedToken(Refusal::UnknownKey);
    }
}
