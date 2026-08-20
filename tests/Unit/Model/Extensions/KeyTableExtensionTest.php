<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\KeyTableExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(KeyTableExtension::class)]
final class KeyTableExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_with_default_options(): void
    {
        $extension = new KeyTableExtension();

        $this->assertSame([
            'blurable'            => true,
            'className'           => 'focus',
            'clipboard'           => true,
            'clipboardOrthogonal' => 'display',
            'columns'             => '',
            'editOnFocus'         => false,
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_custom_options(): void
    {
        $extension = new KeyTableExtension(
            blurable: false,
            className: 'cell-focus',
            clipboard: false,
            clipboardOrthogonal: 'filter',
            columns: ':not(:first-child)',
            editOnFocus: true,
            focus: [0, 1],
            keys: [37, 38, 39, 40],
            tabIndex: 0,
        );

        $this->assertSame([
            'blurable'            => false,
            'className'           => 'cell-focus',
            'clipboard'           => false,
            'clipboardOrthogonal' => 'filter',
            'columns'             => ':not(:first-child)',
            'editOnFocus'         => true,
            'focus'               => [0, 1],
            'keys'                => [37, 38, 39, 40],
            'tabIndex'            => 0,
        ], $extension->jsonSerialize());
    }
}
