<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use function base64_decode;
use function base64_encode;
use function rtrim;
use function strtr;

/**
 * Base64url without padding (RFC 4648 §5), as a compact JWS encodes its segments.
 *
 * @api
 */
final class Base64Url
{
    /**
     * The decoded bytes, or null where the text is not base64url.
     */
    public static function decode(string $text): ?string
    {
        $decoded = base64_decode(strtr($text, from: '-_', to: '+/'), strict: true);

        return false === $decoded || self::encode($decoded) !== $text ? null : $decoded;
    }

    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), from: '+/', to: '-_'), characters: '=');
    }
}
