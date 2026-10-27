<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use InvalidArgumentException;

use function array_key_exists;
use function is_array;
use function parse_url;
use function sprintf;
use function strlen;

/**
 * The settings the CMS and a site share: who issues tokens and for which site (both origins), the
 * secrets that sign them by key id, and the one the issuer signs with. A site may hold several keys
 * while rotating; the issuer signs with one.
 *
 * @api
 */
final readonly class SiteKeys
{
    /** The shortest secret either side accepts, in bytes. */
    public const int MIN_SECRET_BYTES = 32;

    /**
     * @param non-empty-array<string, string> $secrets  Key id => secret
     * @param string                          $signWith The key id the issuer signs with
     *
     * @throws InvalidArgumentException If a setting cannot be used as it stands.
     */
    public function __construct(
        public string $issuer,
        public string $audience,
        public array $secrets,
        public string $signWith,
    ) {
        if (! array_key_exists($signWith, $secrets)) {
            throw new InvalidArgumentException(sprintf('No secret is configured for the key id "%s".', $signWith));
        }

        foreach ($secrets as $keyId => $secret) {
            if (strlen($secret) < self::MIN_SECRET_BYTES) {
                throw new InvalidArgumentException(sprintf(
                    'The secret for the key id "%s" is shorter than %d bytes.',
                    $keyId,
                    self::MIN_SECRET_BYTES,
                ));
            }
        }

        self::checkOrigin('issuer', $issuer);
        self::checkOrigin('audience', $audience);
    }

    /**
     * Each side is named by an origin and nothing more, matched whole against a token's `iss` and
     * `aud`, so each must be the one string both sides hold. A protocol appends its own fixed path
     * to the audience to reach the site.
     *
     * @throws InvalidArgumentException If $value carries anything beyond a scheme, host and port.
     */
    private static function checkOrigin(string $setting, string $value): void
    {
        $url    = parse_url($value);
        $parts  = is_array($url) ? $url : [];
        $scheme = $parts['scheme'] ?? null;
        $host   = $parts['host'] ?? null;
        $port   = $parts['port'] ?? null;

        if (null !== $scheme && null !== $host) {
            $origin = null === $port ? "{$scheme}://{$host}" : "{$scheme}://{$host}:{$port}";
            if ($value === $origin || $value === "{$origin}/") {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'The %s "%s" must be an origin: a scheme, a host and an optional port, and nothing else.',
            $setting,
            $value,
        ));
    }

    /**
     * The secret held under $keyId, or null when this side holds none.
     */
    public function secret(string $keyId): ?string
    {
        return $this->secrets[$keyId] ?? null;
    }
}
