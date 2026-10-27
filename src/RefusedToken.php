<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use RuntimeException;

/**
 * A token TokenVerifier refuses, saying why. A protocol may answer with its own words for each
 * Refusal; the message here is the plain one.
 *
 * @api
 */
final class RefusedToken extends RuntimeException
{
    public function __construct(
        public readonly Refusal $refusal,
    ) {
        parent::__construct($refusal->reason(), $refusal->status());
    }
}
