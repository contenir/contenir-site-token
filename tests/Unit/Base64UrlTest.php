<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Unit;

use Contenir\SiteToken\Base64Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Base64url without padding, as a compact JWS encodes its segments.
 */
#[Group('unit')]
final class Base64UrlTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function notBase64UrlProvider(): array
    {
        return [
            'the standard alphabet' => ['+/8'],
            'padded'                => ['YQ=='],
            'not base64 at all'     => ['!!'],
        ];
    }

    #[Test]
    public function decodesWhatItEncodes(): void
    {
        $this->assertSame("\xfb\xff\xfe\x00", Base64Url::decode(Base64Url::encode("\xfb\xff\xfe\x00")));
    }

    #[Test]
    public function encodesWithoutPaddingInTheUrlAlphabet(): void
    {
        $this->assertSame('-_8', Base64Url::encode("\xfb\xff"));
    }

    #[DataProvider('notBase64UrlProvider')]
    #[Test]
    public function refusesTextNotInItsAlphabet(string $text): void
    {
        $this->assertNull(Base64Url::decode($text));
    }
}
