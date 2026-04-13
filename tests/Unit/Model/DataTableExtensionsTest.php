<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Contracts\LayoutAwareExtensionInterface;
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
    public function it_constructs_with_valid_extensions(): void
    {
        $extensions = [
            'buttons' => [
                'copy',
                'csv',
                'excel',
                'pdf',
                'print',
            ],
            'select' => [
                'style' => 'single',
            ],
        ];

        $dataTableExtensions = new DataTableExtensions($extensions);

        $this->assertArrayHasKey('select', $dataTableExtensions->jsonSerialize());
    }

    #[Test]
    public function it_excludes_layout_aware_extensions_from_json_serialize(): void
    {
        $extensions = new DataTableExtensions();
        $extensions->addExtension(new ButtonsExtension(['copy']));

        $this->assertArrayNotHasKey('buttons', $extensions->jsonSerialize());
    }

    #[Test]
    public function it_includes_non_layout_aware_extensions_in_json_serialize(): void
    {
        $extensions = new DataTableExtensions();
        $extensions->addExtension(new SelectExtension());

        $this->assertArrayHasKey('select', $extensions->jsonSerialize());
    }

    #[Test]
    public function buttons_extension_implements_layout_aware_interface(): void
    {
        $this->assertInstanceOf(LayoutAwareExtensionInterface::class, new ButtonsExtension(['copy']));
    }
}
