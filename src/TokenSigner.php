<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use Closure;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

use function array_intersect_key;
use function array_keys;
use function bin2hex;
use function hash_hmac;
use function implode;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The CMS's side: signs a token of one protocol's type for whoever asked, living the type's longest
 * lifetime and carrying a fresh one-time id, with the protocol's own claims after the shared ones.
 *
 * @api
 */
final readonly class TokenSigner
{
    /** The payload's shared claims, which a protocol's own claims may not name. */
    public const array RESERVED = [
        'iss' => true,
        'aud' => true,
        'iat' => true,
        'exp' => true,
        'jti' => true,
        'sub' => true,
        'ver' => true,
    ];

    /** @var Closure(): string */
    private Closure $tokenId;

    /**
     * @param (Closure(): string)|null $tokenId Makes each token's id; 128 random bits as hex by default
     */
    public function __construct(
        private SiteKeys $keys,
        private ClockInterface $clock,
        ?Closure $tokenId = null,
    ) {
        $this->tokenId = $tokenId ?? self::randomId(...);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 128 bits from the system's secure source, as hex.
     */
    private static function randomId(): string
    {
        return bin2hex((new Randomizer())->getBytes(16));
    }

    /**
     * @param string               $subject Who the token is for, such as the CMS user asking; for the site's logs only
     * @param array<string, mixed> $claims  The protocol's own claims
     *
     * @throws InvalidArgumentException When $claims names one of the shared claims.
     */
    public function sign(TokenType $type, string $subject, array $claims = []): string
    {
        $clash = array_intersect_key($claims, self::RESERVED);
        if ([] !== $clash) {
            throw new InvalidArgumentException(sprintf(
                'A protocol\'s claims may not name the shared claims: %s.',
                implode(', ', array_keys($clash)),
            ));
        }

        $issuedAt = $this->clock->now()->getTimestamp();
        $header   = Base64Url::encode(self::json([
            'alg' => 'HS256',
            'typ' => $type->name,
            'kid' => $this->keys->signWith,
        ]));
        $payload = Base64Url::encode(self::json([
            'iss' => $this->keys->issuer,
            'aud' => $this->keys->audience,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $type->maxLifetime,
            'jti' => ($this->tokenId)(),
            'sub' => $subject,
            'ver' => $type->version,
            ...$claims,
        ]));
        $secret    = (string) $this->keys->secret($this->keys->signWith);
        $signature = Base64Url::encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, binary: true));

        return "{$header}.{$payload}.{$signature}";
    }
}
