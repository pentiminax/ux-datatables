<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\Strategy\InListSearchStrategy;
use PHPUnit\Framework\TestCase;

class InListSearchStrategyTest extends TestCase
{
    public function testApplyForList(): void
    {
        $strategy = new InListSearchStrategy();

        // Create a mock QueryBuilder
        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->equalTo('e.columnField IN (:columnField_in)'));

        $qb->expects($this->once())
            ->method('setParameter')
            ->with($this->equalTo(':columnField_in'), $this->equalTo(['value1', 'value2']));

        $strategy->applyForList($qb, 'columnField', ['value1', 'value2'], 'e');
    }

    public function testApplyForListWithDotNotation(): void
    {
        $strategy = new InListSearchStrategy();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.author', 'author')
            ->willReturn($qb);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->equalTo('author.firstName IN (:author_firstName_in)'));

        $qb->expects($this->once())
            ->method('setParameter')
            ->with($this->equalTo(':author_firstName_in'), $this->equalTo(['Alice', 'Bob']));

        $strategy->applyForList($qb, 'author.firstName', ['Alice', 'Bob'], 'e');
    }

    public function testApplyForListWithEmptyArray(): void
    {
        $strategy = new InListSearchStrategy();

        // Create a mock QueryBuilder
        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->never())
            ->method('andWhere');

        $qb->expects($this->never())
            ->method('setParameter');

        $strategy->applyForList($qb, 'columnField', [], 'e');
    }

    public function testGetLogic(): void
    {
        $strategy = new InListSearchStrategy();
        $this->assertEquals('in', $strategy->getLogic());
    }
}
