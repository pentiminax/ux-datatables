<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SelectExtension::class)]
final class SelectExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_to_array(): void
    {
        $extension = new SelectExtension();

        $serializedExtension = $extension->jsonSerialize();

        $expectedArray = [
            'blurable'       => false,
            'className'      => 'selected',
            'info'           => true,
            'items'          => 'row',
            'keys'           => false,
            'style'          => 'single',
            'toggleable'     => true,
            'headerCheckbox' => false,
            'withCheckbox'   => false,
        ];

        $this->assertEquals($expectedArray, $serializedExtension);
    }

    #[Test]
    public function it_configures_style(): void
    {
        $extension = new SelectExtension(SelectStyle::MULTI);

        $this->assertEquals('multi', $extension->jsonSerialize()['style']);
    }
}
