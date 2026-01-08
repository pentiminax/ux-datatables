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
            ->with($this->equalTo('e.columnName IN (:columnName_in)'));
        
        $qb->expects($this->once())
            ->method('setParameter')
            ->with($this->equalTo(':columnName_in'), $this->equalTo(['value1', 'value2']));
        
        $strategy->applyForList($qb, 'columnName', ['value1', 'value2'], 'e');
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
        
        $strategy->applyForList($qb, 'columnName', [], 'e');
    }
    
    public function testGetLogic(): void
    {
        $strategy = new InListSearchStrategy();
        $this->assertEquals('in', $strategy->getLogic());
    }
}