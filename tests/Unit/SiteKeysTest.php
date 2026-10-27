<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Unit;

use Contenir\SiteToken\SiteKeys;
use Contenir\SiteToken\Tests\TestAsset\Tokens;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function str_repeat;

/**
 * The settings the CMS and a site share.
 */
#[Group('unit')]
final class SiteKeysTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function settingsThatAreNotOrigins(): array
    {
        return [
            'a path'             => ['https://admin.test/admin'],
            'a path and a slash' => ['https://admin.test/admin/'],
            'a query'            => ['https://admin.test?preview=1'],
            'a fragment'         => ['https://admin.test#preview'],
            'credentials'        => ['https://editor:secret@admin.test'],
            'no scheme'          => ['admin.test'],
            'no host'            => ['https://'],
            'a scheme, no host'  => ['file:///'],
        ];
    }

    #[Test]
    public function acceptsAnOriginWithAPortOrATrailingSlash(): void
    {
        $keys = new SiteKeys('https://admin.test:8443', 'https://www.test/', ['k' => Tokens::SHARED], 'k');

        $this->assertSame(['https://admin.test:8443', 'https://www.test/'], [$keys->issuer, $keys->audience]);
    }

    #[Test]
    public function acceptsASecretOfExactly32Bytes(): void
    {
        $this->assertSame(
            str_repeat('a', times: 32),
            (new SiteKeys(
                'https://admin.test',
                'https://www.test',
                ['k' => str_repeat('a', times: 32)],
                'k',
            ))->secret(
                'k',
            ),
        );
    }

    #[Test]
    public function findsASecretByItsKeyId(): void
    {
        $keys = new SiteKeys(
            'https://admin.test',
            'https://www.test',
            ['old' => Tokens::SHARED, 'new' => Tokens::SHARED . '2'],
            'new',
        );

        $this->assertSame([Tokens::SHARED . '2', null], [$keys->secret('new'), $keys->secret('gone')]);
    }

    #[Test]
    #[DataProvider('settingsThatAreNotOrigins')]
    public function refusesAnAudienceThatIsNotAnOrigin(string $audience): void
    {
        $this->expectExceptionObject(new InvalidArgumentException(sprintf(
            'The audience "%s" must be an origin: a scheme, a host and an optional port, and nothing else.',
            $audience,
        )));

        new SiteKeys('https://admin.test', $audience, ['k' => Tokens::SHARED], 'k');
    }

    #[Test]
    #[DataProvider('settingsThatAreNotOrigins')]
    public function refusesAnIssuerThatIsNotAnOrigin(string $issuer): void
    {
        $this->expectExceptionObject(new InvalidArgumentException(sprintf(
            'The issuer "%s" must be an origin: a scheme, a host and an optional port, and nothing else.',
            $issuer,
        )));

        new SiteKeys($issuer, 'https://www.test', ['k' => Tokens::SHARED], 'k');
    }

    #[Test]
    public function refusesASecretShorterThan32Bytes(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException('The secret for the key id "old" is shorter than 32 bytes.'),
        );

        new SiteKeys(
            'https://admin.test',
            'https://www.test',
            ['old' => str_repeat('a', times: 31), 'new' => Tokens::SHARED],
            'new',
        );
    }

    #[Test]
    public function refusesToSignWithAKeyItDoesNotHold(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('No secret is configured for the key id "new".'));

        new SiteKeys('https://admin.test', 'https://www.test', ['old' => Tokens::SHARED], 'new');
    }
}
