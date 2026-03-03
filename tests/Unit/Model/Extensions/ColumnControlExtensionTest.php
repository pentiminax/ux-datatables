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
}
