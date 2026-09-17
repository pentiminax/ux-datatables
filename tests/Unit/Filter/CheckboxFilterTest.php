<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Filter\CheckboxFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CheckboxFilter::class)]
final class CheckboxFilterTest extends TestCase
{
    #[Test]
    public function it_serializes_as_a_checkbox(): void
    {
        $filter = CheckboxFilter::new('vip')->label('VIP only');

        $this->assertSame([
            'name'  => 'vip',
            'type'  => 'checkbox',
            'label' => 'VIP only',
        ], $filter->jsonSerialize());
    }

    #[Test]
    public function it_runs_the_query_closure(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $called = false;
        $filter = CheckboxFilter::new('vip')->query(function (QueryBuilder $builder, mixed $value, string $alias) use ($qb, &$called): void {
            $called = true;
            $this->assertSame($qb, $builder);
            $this->assertSame('1', $value);
            $this->assertSame('e', $alias);
        });

        $filter->apply($qb, '1', 'e');

        $this->assertTrue($called);
    }

    #[Test]
    public function it_throws_without_a_query_closure(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $this->expectException(\LogicException::class);

        CheckboxFilter::new('vip')->apply($qb, '1', 'e');
    }
}
