<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\Unit;

use Contenir\SiteToken\TokenType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * One protocol's tokens: its name, version, longest lifetime and largest size.
 */
#[Group('unit')]
final class TokenTypeTest extends TestCase
{
    /**
     * @return array<string, array{int, int, int, string}>
     */
    public static function boundsBelowOneProvider(): array
    {
        return [
            'a version of 0'  => [0, 300, 1, 'version'],
            'a lifetime of 0' => [1, 0, 1, 'lifetime'],
            'a size of 0'     => [1, 300, 0, 'size'],
            'a negative size' => [1, 300, -1, 'size'],
        ];
    }

    #[Test]
    public function acceptsBoundsOfOne(): void
    {
        $type = new TokenType('t', 1, 1, 1);

        $this->assertSame([1, 1, 1], [$type->version, $type->maxLifetime, $type->maxBytes]);
    }

    #[Test]
    public function decodesAtMostAMegabyteUnlessToldOtherwise(): void
    {
        $type = new TokenType('contenir-cache+jwt', 1, 60);

        $this->assertSame(
            ['contenir-cache+jwt', 1, 60, 1_048_576],
            [$type->name, $type->version, $type->maxLifetime, $type->maxBytes],
        );
    }

    #[Test]
    #[DataProvider('boundsBelowOneProvider')]
    public function refusesABoundBelowOne(int $version, int $lifetime, int $bytes, string $bound): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("A token type's {$bound} must be at least 1."));

        new TokenType('t', $version, $lifetime, $bytes);
    }

    #[Test]
    public function refusesATypeWithNoName(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('A token type needs a name.'));

        new TokenType('', 1, 60);
    }
}
