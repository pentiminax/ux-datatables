<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColumnControlExtension::class)]
final class ColumnControlExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_to_array(): void
    {
        $extension = new ColumnControlExtension();

        $expectedArray = [
            [
                'target'  => 0,
                'content' => [
                    'order',
                    [
                        'orderAsc',
                        'orderDesc',
                        'spacer',
                        'orderAddAsc',
                        'orderAddDesc',
                        'spacer',
                        'orderRemove',
                    ],
                ],
            ],
            [
                'target'  => 1,
                'content' => ['search'],
            ],
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_only_the_added_controls(): void
    {
        $extension = (new ColumnControlExtension([]))->add('tfoot', ['search']);

        $this->assertSame([
            ['target' => 'tfoot', 'content' => ['search']],
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_appends_every_added_control_in_call_order(): void
    {
        $extension = (new ColumnControlExtension([]))
            ->add(0, ['order'])
            ->add('tfoot', ['searchText']);

        $this->assertSame([
            ['target' => 0, 'content' => ['order']],
            ['target' => 'tfoot', 'content' => ['searchText']],
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_drops_the_defaults_as_soon_as_a_control_is_added(): void
    {
        $extension = (new ColumnControlExtension())->add('tfoot', ['search']);

        $this->assertSame([
            ['target' => 'tfoot', 'content' => ['search']],
        ], $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_an_explicitly_empty_configuration(): void
    {
        $this->assertSame([], (new ColumnControlExtension([]))->jsonSerialize());
    }

    #[Test]
    public function it_serializes_a_configuration_passed_to_the_constructor(): void
    {
        $controls = [['target' => 'tfoot:1', 'content' => ['searchNumber']]];

        $this->assertSame($controls, (new ColumnControlExtension($controls))->jsonSerialize());
    }
}
