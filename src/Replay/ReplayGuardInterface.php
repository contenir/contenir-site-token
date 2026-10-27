<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Replay;

/**
 * Remembers the tokens a site has accepted, so each is accepted once. A site on more than one
 * server needs one guard all of them share.
 *
 * @api
 */
interface ReplayGuardInterface
{
    /**
     * Records a token's id until it expires. False when the id has been recorded already.
     *
     * @param int $expiresAt The token's expiry, seconds since the epoch
     */
    public function claim(string $tokenId, int $expiresAt): bool;
}
