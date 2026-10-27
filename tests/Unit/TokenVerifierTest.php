<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Unit;

use Contenir\SiteToken\Base64Url;
use Contenir\SiteToken\Refusal;
use Contenir\SiteToken\RefusedToken;
use Contenir\SiteToken\Replay\InMemoryReplayGuard;
use Contenir\SiteToken\Tests\TestAsset\FrozenClock;
use Contenir\SiteToken\Tests\TestAsset\Tokens;
use Contenir\SiteToken\TokenType;
use Contenir\SiteToken\TokenVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

use function explode;
use function str_repeat;

/**
 * A site checking a token of one protocol's type, the refusal it gives each failure, and what an
 * accepted token carries.
 */
#[Group('unit')]
final class TokenVerifierTest extends TestCase
{
    /**
     * @return array<string, array{string|null, Refusal}>
     */
    public static function notATokenProvider(): array
    {
        $valid = Tokens::sign(Tokens::header(), Tokens::payload());

        return [
            'nothing sent'           => [null, Refusal::Missing],
            'an empty field'         => ['', Refusal::Missing],
            'more than a megabyte'   => [str_repeat('a', times: 1_048_577), Refusal::TooLarge],
            'exactly a megabyte'     => [str_repeat('a', times: 1_048_576), Refusal::Malformed],
            'two segments'           => ['abc.def', Refusal::Malformed],
            'four segments'          => ["{$valid}.abc", Refusal::Malformed],
            'a header not base64url' => ['a+b.' . Base64Url::encode('{}') . '.sig', Refusal::Malformed],
            'a header not JSON'      => [
                Base64Url::encode('not json') . '.' . Base64Url::encode('{}') . '.sig',
                Refusal::Malformed,
            ],
            'a header not an object' => [
                Base64Url::encode('"text"') . '.' . Base64Url::encode('{}') . '.sig',
                Refusal::Malformed,
            ],
            'a payload not JSON'     => [
                Base64Url::encode('{}') . '.' . Base64Url::encode('[') . '.sig',
                Refusal::Malformed,
            ],
        ];
    }

    /**
     * @return array<string, array{string, Refusal}>
     */
    public static function untrustedProvider(): array
    {
        $header  = Tokens::header();
        $payload = Tokens::payload();
        $valid   = Tokens::sign($header, $payload);
        [$head, $body, $signature] = explode('.', $valid);

        return [
            'another algorithm'         => [Tokens::sign(['alg' => 'none'] + $header, $payload), Refusal::WrongSigning],
            'another protocol\'s type'  => [
                Tokens::sign(['typ' => 'contenir-preview+jwt'] + $header, $payload),
                Refusal::WrongSigning,
            ],
            'no type'                   => [Tokens::sign(['typ' => null] + $header, $payload), Refusal::WrongSigning],
            'a key this site lacks'     => [
                Tokens::sign(['kid' => '2025-01'] + $header, $payload),
                Refusal::UnknownKey,
            ],
            'a key id not text'         => [Tokens::sign(['kid' => 7] + $header, $payload), Refusal::UnknownKey],
            'another secret'            => [
                Tokens::sign($header, $payload, 'another-secret-of-at-least-thirty-two-bytes'),
                Refusal::BadSignature,
            ],
            'a signature not base64url' => ["{$head}.{$body}.a+b", Refusal::BadSignature],
            'a payload altered'         => [
                "{$head}." . Base64Url::encode('{"ver":2}') . ".{$signature}",
                Refusal::BadSignature,
            ],
            'another version'           => [Tokens::sign($header, ['ver' => 1] + $payload), Refusal::WrongVersion],
            'a version as text'         => [Tokens::sign($header, ['ver' => '2'] + $payload), Refusal::WrongVersion],
            'another issuer'            => [
                Tokens::sign($header, ['iss' => 'https://elsewhere.test'] + $payload),
                Refusal::WrongParties,
            ],
            'another audience'          => [
                Tokens::sign($header, ['aud' => 'https://elsewhere.test'] + $payload),
                Refusal::WrongParties,
            ],
            'no issue time'             => [
                Tokens::sign($header, ['iat' => '1792236000'] + $payload),
                Refusal::BadLifetime,
            ],
            'no expiry'                 => [Tokens::sign($header, ['exp' => null] + $payload), Refusal::BadLifetime],
            'expiring before issue'     => [
                Tokens::sign($header, ['exp' => 1_792_235_999] + $payload),
                Refusal::BadLifetime,
            ],
            'living past its type'      => [
                Tokens::sign($header, ['exp' => 1_792_236_301] + $payload),
                Refusal::BadLifetime,
            ],
            'issued in the future'      => [
                Tokens::sign($header, Tokens::payload(1_792_236_031)),
                Refusal::IssuedInFuture,
            ],
            'expired'                   => [Tokens::sign($header, Tokens::payload(1_792_235_669)), Refusal::Expired],
            'no id'                     => [Tokens::sign($header, ['jti' => null] + $payload), Refusal::NoTokenId],
            'an id not 32 hex digits'   => [Tokens::sign($header, ['jti' => '0123'] + $payload), Refusal::NoTokenId],
            'an id in capitals'         => [
                Tokens::sign($header, ['jti' => '0123456789ABCDEF0123456789ABCDEF'] + $payload),
                Refusal::NoTokenId,
            ],
            'an id with text before it' => [
                Tokens::sign($header, ['jti' => 'x' . Tokens::TOKEN_ID] + $payload),
                Refusal::NoTokenId,
            ],
            'an id with text after it'  => [
                Tokens::sign($header, ['jti' => Tokens::TOKEN_ID . 'x'] + $payload),
                Refusal::NoTokenId,
            ],
            'an id ending in a newline' => [
                Tokens::sign($header, ['jti' => Tokens::TOKEN_ID . "\n"] + $payload),
                Refusal::NoTokenId,
            ],
        ];
    }

    #[Test]
    public function acceptsATokenNamingNoOne(): void
    {
        $payload = Tokens::payload();
        unset($payload['sub']);

        $this->assertSame(
            '',
            $this->verifier()->verify(Tokens::type(), Tokens::sign(Tokens::header(), $payload))->subject,
        );
    }

    #[Test]
    public function acceptsATokenOnceOnly(): void
    {
        $verifier = $this->verifier();
        $token    = Tokens::sign(Tokens::header(), Tokens::payload());
        $verifier->verify(Tokens::type(), $token);

        $this->assertRefused(Refusal::Replayed, $token, $verifier);
    }

    #[Test]
    public function acceptsATokenThatExpiresAsItIsIssued(): void
    {
        $token = Tokens::sign(Tokens::header(), ['exp' => 1_792_236_000] + Tokens::payload());

        $this->assertSame(1_792_236_000, $this->verifier()->verify(Tokens::type(), $token)->expiresAt->getTimestamp());
    }

    #[Test]
    public function allowsTheClocksThirtySecondsEitherWay(): void
    {
        $verifier = $this->verifier();
        $ahead    = Tokens::payload(1_792_236_030);
        $behind   = ['jti' => 'ffffffffffffffffffffffffffffffff'] + Tokens::payload(1_792_235_670);

        $this->assertSame(
            [1_792_236_030, 1_792_235_670],
            [
                $verifier->verify(Tokens::type(), Tokens::sign(Tokens::header(), $ahead))->issuedAt->getTimestamp(),
                $verifier->verify(Tokens::type(), Tokens::sign(Tokens::header(), $behind))->issuedAt->getTimestamp(),
            ],
        );
    }

    #[Test]
    public function decodesNoMoreThanItsTypeAllows(): void
    {
        $small = new TokenType(Tokens::TYPE, 2, 300, 16);

        $this->assertSame(
            [Refusal::TooLarge, Refusal::Malformed],
            [$this->refusal($small, str_repeat('a', times: 17)), $this->refusal($small, str_repeat('a', times: 16))],
        );
    }

    #[Test]
    public function givesBackWhatAnAcceptedTokenCarries(): void
    {
        $token = $this->verifier()->verify(Tokens::type(), Tokens::sign(Tokens::header(), Tokens::payload()));

        $this->assertSame(
            [Tokens::TOKEN_ID, '12', 1_792_236_000, 1_792_236_300, ['scope' => 'all']],
            [
                $token->tokenId,
                $token->subject,
                $token->issuedAt->getTimestamp(),
                $token->expiresAt->getTimestamp(),
                $token->claims,
            ],
        );
    }

    #[Test]
    #[DataProvider('untrustedProvider')]
    public function refusesATokenItCannotTrust(#[SensitiveParameter] string $token, Refusal $refusal): void
    {
        $this->assertRefused($refusal, $token);
    }

    #[Test]
    #[DataProvider('notATokenProvider')]
    public function refusesWhatIsNotAToken(#[SensitiveParameter] ?string $token, Refusal $refusal): void
    {
        $this->assertRefused($refusal, $token);
    }

    private function assertRefused(
        Refusal $refusal,
        #[SensitiveParameter]
        ?string $token,
        ?TokenVerifier $verifier = null,
    ): void {
        $this->assertSame($refusal, $this->refusal(Tokens::type(), $token, $verifier));
    }

    private function refusal(
        TokenType $type,
        #[SensitiveParameter]
        ?string $token,
        ?TokenVerifier $verifier = null,
    ): Refusal {
        try {
            ($verifier ?? $this->verifier())->verify($type, $token);
        } catch (RefusedToken $refused) {
            return $refused->refusal;
        }

        $this->fail('The token was accepted.');
    }

    private function verifier(): TokenVerifier
    {
        $clock = new FrozenClock();

        return new TokenVerifier(Tokens::keys(), $clock, new InMemoryReplayGuard($clock));
    }
}
