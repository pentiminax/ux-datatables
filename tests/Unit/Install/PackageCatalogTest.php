<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Install;

use Pentiminax\UX\DataTables\Install\PackageCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PackageCatalog::class)]
final class PackageCatalogTest extends TestCase
{
    #[Test]
    #[DataProvider('optionKeys')]
    public function it_converts_between_extension_names_and_option_keys(string $name, string $key): void
    {
        $this->assertSame($key, PackageCatalog::optionKey($name));
        $this->assertSame($name, PackageCatalog::fromOptionKey($key));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function optionKeys(): iterable
    {
        yield 'renamed key' => ['keyTable', 'keys'];
        yield 'same name' => ['select', 'select'];
        yield 'buttons' => ['buttons', 'buttons'];
    }

    #[Test]
    public function it_ignores_option_keys_that_are_not_extensions(): void
    {
        $this->assertNull(PackageCatalog::fromOptionKey('columns'));
        $this->assertNull(PackageCatalog::fromOptionKey('keyTablex'));
    }

    #[Test]
    public function it_lists_every_extension_once_after_buttons(): void
    {
        $selectable = PackageCatalog::selectable();

        $this->assertSame('buttons', $selectable[0]);
        $this->assertSame(array_unique($selectable), $selectable);
    }
}
