<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Unit;

use Contenir\SiteToken\Base64Url;
use Contenir\SiteToken\Replay\InMemoryReplayGuard;
use Contenir\SiteToken\Tests\TestAsset\FrozenClock;
use Contenir\SiteToken\Tests\TestAsset\Tokens;
use Contenir\SiteToken\TokenSigner;
use Contenir\SiteToken\TokenType;
use Contenir\SiteToken\TokenVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

use function explode;
use function json_decode;
use function preg_match;
use function str_contains;

/**
 * The CMS signing a token of one protocol's type that a site holding the same secret accepts.
 */
#[Group('unit')]
final class TokenSignerTest extends TestCase
{
    #[Test]
    public function givesEachTokenA128BitRandomIdByDefault(): void
    {
        $signer = new TokenSigner(Tokens::keys(), new FrozenClock());
        $first  = $this->segment($signer->sign(Tokens::type(), '12'), 1)['jti'];
        $second = $this->segment($signer->sign(Tokens::type(), '12'), 1)['jti'];

        $this->assertSame([1, 1, true], [
            preg_match('/^[0-9a-f]{32}$/', $first),
            preg_match('/^[0-9a-f]{32}$/', $second),
            $first !== $second,
        ]);
    }

    #[Test]
    public function refusesAProtocolsClaimNamingASharedOne(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException(
            'A protocol\'s claims may not name the shared claims: exp, sub.',
        ));

        $this->signer()->sign(Tokens::type(), '12', ['exp' => 0, 'scope' => 'all', 'sub' => 'admin']);
    }

    #[Test]
    public function signsATokenTheSiteAccepts(): void
    {
        $clock    = new FrozenClock();
        $verifier = new TokenVerifier(Tokens::keys(), $clock, new InMemoryReplayGuard($clock));

        $this->assertSame(Tokens::TOKEN_ID, $verifier->verify(Tokens::type(), $this->sign())->tokenId);
    }

    #[Test]
    public function signsTheSharedClaimsForTheTypesLifetimeThenTheProtocolsOwn(): void
    {
        $this->assertSame(
            [
                'iss'   => Tokens::ISSUER,
                'aud'   => Tokens::AUDIENCE,
                'iat'   => 1_792_236_000,
                'exp'   => 1_792_236_060,
                'jti'   => Tokens::TOKEN_ID,
                'sub'   => '12',
                'ver'   => 3,
                'scope' => 'all',
            ],
            $this->segment($this->signer()->sign(new TokenType('contenir-cache+jwt', 3, 60), '12', [
                'scope' => 'all',
            ]), 1),
        );
    }

    #[Test]
    public function signsTheTypesHeaderWithTheKeyItSignsWith(): void
    {
        $this->assertSame(
            ['alg' => 'HS256', 'typ' => Tokens::TYPE, 'kid' => '2026-10'],
            $this->segment($this->sign(), 0),
        );
    }

    #[Test]
    public function writesSlashesAndTextAsTheyAre(): void
    {
        $payload = (string) Base64Url::decode(explode('.', $this->signer()->sign(Tokens::type(), '12', [
            'path'  => '/spa/rituals',
            'title' => 'Spa “Rituals”',
        ]))[1]);

        $this->assertSame(
            [true, true],
            [str_contains($payload, '"path":"/spa/rituals"'), str_contains($payload, '"title":"Spa “Rituals”"')],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function segment(#[SensitiveParameter] string $token, int $index): array
    {
        return json_decode((string) Base64Url::decode(explode('.', $token)[$index]), associative: true);
    }

    private function sign(): string
    {
        return $this->signer()->sign(Tokens::type(), '12', ['scope' => 'all']);
    }

    private function signer(): TokenSigner
    {
        return new TokenSigner(Tokens::keys(), new FrozenClock(), static fn(): string => Tokens::TOKEN_ID);
    }
}
