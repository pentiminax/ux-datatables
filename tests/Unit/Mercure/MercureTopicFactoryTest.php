<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\MercureTopicFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MercureTopicFactory::class)]
final class MercureTopicFactoryTest extends TestCase
{
    #[Test]
    #[DataProvider('provideNames')]
    public function it_builds_the_fallback_topic(string $shortName, string $expected): void
    {
        $this->assertSame($expected, MercureTopicFactory::fallbackTopic($shortName));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNames(): iterable
    {
        yield 'simple singular' => ['Product', '/datatables/products/{id}'];
        yield 'camel case' => ['BookCategory', '/datatables/book-categories/{id}'];
        yield 'already plural-y' => ['Company', '/datatables/companies/{id}'];
    }
}
