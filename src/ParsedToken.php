<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

use JsonException;
use SensitiveParameter;

use function count;
use function explode;
use function is_array;
use function json_decode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * A token taken apart: its header and payload decoded, the text its signature covers, and the
 * signature's bytes. Nothing in it is trusted until the signature is.
 *
 * @internal
 */
final readonly class ParsedToken
{
    /**
     * @param array<array-key, mixed> $header
     * @param array<array-key, mixed> $payload
     */
    private function __construct(
        public array $header,
        public array $payload,
        public string $signed,
        public ?string $signature,
    ) {}

    /**
     * @throws RefusedToken
     */
    public static function parse(#[SensitiveParameter] ?string $token, int $maxBytes): self
    {
        if (null === $token || '' === $token) {
            throw new RefusedToken(Refusal::Missing);
        }
        if (strlen($token) > $maxBytes) {
            throw new RefusedToken(Refusal::TooLarge);
        }

        $segments = explode('.', $token);
        if (3 !== count($segments)) {
            throw new RefusedToken(Refusal::Malformed);
        }
        $header    = $segments[0];
        $payload   = $segments[1] ?? '';
        $signature = $segments[2] ?? '';

        return new self(
            self::decode($header),
            self::decode($payload),
            "{$header}.{$payload}",
            Base64Url::decode($signature),
        );
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws RefusedToken
     */
    private static function decode(string $segment): array
    {
        $json = Base64Url::decode($segment);
        if (null === $json) {
            throw new RefusedToken(Refusal::Malformed);
        }

        try {
            /** @var mixed $data */
            $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RefusedToken(Refusal::Malformed);
        }

        return is_array($data) ? $data : throw new RefusedToken(Refusal::Malformed);
    }
}
