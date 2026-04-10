<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Enum;

use Pentiminax\UX\DataTables\Enum\Feature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Feature::class)]
final class FeatureTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_cases(): void
    {
        $expected = [
            'buttons'       => 'buttons',
            'info'          => 'info',
            'pageLength'    => 'pageLength',
            'paging'        => 'paging',
            'search'        => 'search',
            'searchBuilder' => 'searchBuilder',
            'searchPanes'   => 'searchPanes',
        ];

        $actual = [];
        foreach (Feature::cases() as $case) {
            $actual[$case->value] = $case->value;
        }

        $this->assertSame($expected, $actual);
    }
}
