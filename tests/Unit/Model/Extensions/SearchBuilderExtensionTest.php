<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\SearchBuilderLogic;
use Pentiminax\UX\DataTables\Model\Extensions\SearchBuilderExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchBuilderExtension::class)]
final class SearchBuilderExtensionTest extends TestCase
{
    #[Test]
    public function it_serializes_an_empty_payload_by_default(): void
    {
        $extension = new SearchBuilderExtension();

        $this->assertSame('searchBuilder', $extension->getKey());
        $this->assertSame([], $extension->jsonSerialize());
    }

    #[Test]
    public function it_serializes_every_option_that_differs_from_the_default(): void
    {
        $extension = new SearchBuilderExtension(
            columns: [1, 2],
            conditions: ['num' => ['!=' => null]],
            depthLimit: 2,
            enterSearch: true,
            greyscale: true,
            liveSearch: false,
            logic: SearchBuilderLogic::Or,
            preDefined: ['criteria' => [['data' => 'Age', 'condition' => '>', 'value' => [50]]], 'logic' => 'AND'],
        );

        $this->assertSame([
            'columns'     => [1, 2],
            'conditions'  => ['num' => ['!=' => null]],
            'depthLimit'  => 2,
            'enterSearch' => true,
            'greyscale'   => true,
            'liveSearch'  => false,
            'logic'       => 'OR',
            'preDefined'  => ['criteria' => [['data' => 'Age', 'condition' => '>', 'value' => [50]]], 'logic' => 'AND'],
        ], $extension->jsonSerialize());
    }
}
