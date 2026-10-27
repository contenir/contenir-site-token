<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\TestAsset;

use InvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgument;

/**
 * The PSR-16 exception ArrayCache throws for a key it is told to refuse.
 */
final class RefusedKey extends InvalidArgumentException implements CacheInvalidArgument {}
