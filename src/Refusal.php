<?php

declare(strict_types=1);

namespace Contenir\SiteToken;

/**
 * Why a token was refused, in the order TokenVerifier checks, with the HTTP status each answers
 * with: 400 for what is not a token, 401 for one that can't be trusted, 409 for one already used.
 *
 * @api
 */
enum Refusal
{
    case Missing;
    case TooLarge;
    case Malformed;
    case WrongSigning;
    case UnknownKey;
    case BadSignature;
    case WrongVersion;
    case WrongParties;
    case BadLifetime;
    case IssuedInFuture;
    case Expired;
    case NoTokenId;
    case Replayed;

    /**
     * A plain reason, which never names the secret, the token or the values expected.
     */
    public function reason(): string
    {
        return match ($this) {
            self::Missing => 'No token was sent.',
            self::TooLarge => 'The token is too large.',
            self::Malformed => 'This is not a token.',
            self::WrongSigning => 'The token is not signed the way this site expects.',
            self::UnknownKey => 'The token is signed with a key this site does not hold.',
            self::BadSignature => 'The token\'s signature does not match.',
            self::WrongVersion => 'The token uses a protocol version this site does not know.',
            self::WrongParties => 'The token was not issued for this site.',
            self::BadLifetime => 'The token\'s lifetime is not one this site accepts.',
            self::IssuedInFuture => 'The token was issued in the future. Check the clocks of the CMS and the site.',
            self::Expired => 'The token has expired.',
            self::NoTokenId => 'The token has no id.',
            self::Replayed => 'The token has already been used.',
        };
    }

    public function status(): int
    {
        return match ($this) {
            self::Missing, self::TooLarge, self::Malformed, self::NoTokenId => 400,
            self::Replayed => 409,
            default => 401,
        };
    }
}
