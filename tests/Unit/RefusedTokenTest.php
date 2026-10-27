<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Unit;

use Contenir\SiteToken\Refusal;
use Contenir\SiteToken\RefusedToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Why a token is refused, the status each refusal answers with, and its plain reason.
 */
#[Group('unit')]
final class RefusedTokenTest extends TestCase
{
    /**
     * @return array<string, array{Refusal, int, string}>
     */
    public static function refusalProvider(): array
    {
        return [
            'nothing sent'     => [Refusal::Missing, 400, 'No token was sent.'],
            'too large'        => [Refusal::TooLarge, 400, 'The token is too large.'],
            'not a token'      => [Refusal::Malformed, 400, 'This is not a token.'],
            'signed otherwise' => [Refusal::WrongSigning, 401, 'The token is not signed the way this site expects.'],
            'an unknown key'   => [Refusal::UnknownKey, 401, 'The token is signed with a key this site does not hold.'],
            'a bad signature'  => [Refusal::BadSignature, 401, 'The token\'s signature does not match.'],
            'another version'  => [
                Refusal::WrongVersion,
                401,
                'The token uses a protocol version this site does not know.',
            ],
            'another site'     => [Refusal::WrongParties, 401, 'The token was not issued for this site.'],
            'a bad lifetime'   => [Refusal::BadLifetime, 401, 'The token\'s lifetime is not one this site accepts.'],
            'from the future'  => [
                Refusal::IssuedInFuture,
                401,
                'The token was issued in the future. Check the clocks of the CMS and the site.',
            ],
            'expired'          => [Refusal::Expired, 401, 'The token has expired.'],
            'no id'            => [Refusal::NoTokenId, 400, 'The token has no id.'],
            'used already'     => [Refusal::Replayed, 409, 'The token has already been used.'],
        ];
    }

    #[Test]
    #[DataProvider('refusalProvider')]
    public function saysWhyWithTheStatusItAnswers(Refusal $refusal, int $status, string $reason): void
    {
        $refused = new RefusedToken($refusal);

        $this->assertSame(
            [$refusal, $status, $status, $reason],
            [$refused->refusal, $refusal->status(), $refused->getCode(), $refused->getMessage()],
        );
    }
}
