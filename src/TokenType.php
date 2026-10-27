<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use InvalidArgumentException;

use function sprintf;

/**
 * One protocol's tokens: the `typ` its header carries, the `ver` its payload carries, how long one
 * may live and how large one may be. Each protocol has its own type, so a token signed for one is
 * refused by every other, even under the same secret.
 *
 * @api
 */
final readonly class TokenType
{
    /** Seconds either side's clock may be out. */
    public const int LEEWAY = 30;

    /** The largest token decoded by default, in bytes. */
    public const int MAX_BYTES = 1_048_576;

    /**
     * @param string $name        The header's `typ`, such as "contenir-preview+jwt"
     * @param int    $version     The payload's `ver`
     * @param int    $maxLifetime Seconds a token lives, at most
     * @param int    $maxBytes    The largest token decoded, in bytes
     *
     * @throws InvalidArgumentException If the name is empty or a bound is not positive.
     */
    public function __construct(
        public string $name,
        public int $version,
        public int $maxLifetime,
        public int $maxBytes = self::MAX_BYTES,
    ) {
        if ('' === $name) {
            throw new InvalidArgumentException('A token type needs a name.');
        }

        foreach (['version' => $version, 'lifetime' => $maxLifetime, 'size' => $maxBytes] as $bound => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException(sprintf('A token type\'s %s must be at least 1.', $bound));
            }
        }
    }
}
