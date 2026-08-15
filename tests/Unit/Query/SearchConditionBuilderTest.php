<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\SearchConditionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchConditionBuilder::class)]
final class SearchConditionBuilderTest extends TestCase
{
    /**
     * Each case gives the builder method, the searched field path and value, the expected
     * DQL fragment, the exact setParameter() arguments and the expected left join.
     *
     * @return iterable<string, array{string, string, string, string, array<int, mixed>, ?array{string, string}}>
     */
    public static function conditions(): iterable
    {
        yield 'text on a simple field' => [
            'text',
            'name',
            'hello',
            'e.name LIKE :param_0',
            ['param_0', '%hello%'],
            null,
        ];

        yield 'text on a relation path' => [
            'text',
            'author.firstName',
            'john',
            'author.firstName LIKE :param_0',
            ['param_0', '%john%'],
            ['e.author', 'author'],
        ];

        yield 'numeric on a simple field' => [
            'numeric',
            'id',
            '42',
            'e.id = :param_0',
            ['param_0', '42', null],
            null,
        ];

        yield 'numeric on a relation path' => [
            'numeric',
            'order.total',
            '99',
            'order.total = :param_0',
            ['param_0', '99', null],
            ['e.order', 'order'],
        ];
    }

    /**
     * @param array<int, mixed>      $expectedParameter
     * @param ?array{string, string} $expectedJoin
     */
    #[Test]
    #[DataProvider('conditions')]
    public function it_builds_the_condition_and_binds_the_value(
        string $method,
        string $fieldPath,
        string $value,
        string $expectedCondition,
        array $expectedParameter,
        ?array $expectedJoin,
    ): void {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        if (null === $expectedJoin) {
            $qb->expects($this->never())->method('leftJoin');
        } else {
            $qb->expects($this->once())
                ->method('leftJoin')
                ->with($expectedJoin[0], $expectedJoin[1])
                ->willReturn($qb);
        }

        $qb->expects($this->once())
            ->method('setParameter')
            ->with(...$expectedParameter);

        $result = SearchConditionBuilder::$method($qb, 'e', $fieldPath, $value, 'param_0');

        $this->assertSame($expectedCondition, $result);
    }

    #[Test]
    public function equality_binds_the_given_doctrine_type(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('param_0', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'ulid');

        $result = SearchConditionBuilder::equality($qb, 'e', 'id', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'param_0', 'ulid');

        $this->assertSame('e.id = :param_0', $result);
    }
}
