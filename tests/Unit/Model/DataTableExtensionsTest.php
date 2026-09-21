<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTableExtensions::class)]
final class DataTableExtensionsTest extends TestCase
{
    #[Test]
    public function it_serializes_constructor_extensions_except_the_layout_aware_ones(): void
    {
        $extensions = new DataTableExtensions([
            'buttons' => ['copy', 'csv', 'excel', 'pdf', 'print'],
            'select'  => ['style' => 'single'],
        ]);

        $serialized = $extensions->jsonSerialize();

        $this->assertArrayHasKey('select', $serialized);
        $this->assertArrayNotHasKey('buttons', $serialized);
    }

    #[Test]
    public function it_serializes_added_extensions_except_the_layout_aware_ones(): void
    {
        $extensions = (new DataTableExtensions())
            ->addExtension(new ButtonsExtension(['copy']))
            ->addExtension(new SelectExtension());

        $serialized = $extensions->jsonSerialize();

        $this->assertArrayHasKey('select', $serialized);
        $this->assertArrayNotHasKey('buttons', $serialized);
    }
}
