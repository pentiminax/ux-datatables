<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTableInfrastructure::class)]
final class DataTableInfrastructureTest extends TestCase
{
    #[Test]
    public function it_builds_default_collaborators_and_exposes_them(): void
    {
        $infrastructure = DataTableInfrastructure::createDefault();

        $this->assertInstanceOf(ColumnResolver::class, $infrastructure->columnResolver());
        $this->assertInstanceOf(RenderingPreparer::class, $infrastructure->renderingPreparer());
        $this->assertInstanceOf(DataTableRuntimeFactory::class, $infrastructure->runtimeFactory());
        $this->assertInstanceOf(DefaultDataTableQueryIntentFactory::class, $infrastructure->queryIntentFactory());
        $this->assertInstanceOf(QueryFilterPipeline::class, $infrastructure->queryFilterPipeline());
    }

    #[Test]
    public function it_returns_the_collaborators_it_was_given(): void
    {
        $columnResolver      = new ColumnResolver();
        $renderingPreparer   = new RenderingPreparer();
        $runtimeFactory      = new DataTableRuntimeFactory();
        $intentFactory       = new DefaultDataTableQueryIntentFactory();
        $queryFilterPipeline = new QueryFilterPipeline($intentFactory);

        $infrastructure = DataTableInfrastructure::createDefault(
            columnResolver: $columnResolver,
            renderingPreparer: $renderingPreparer,
            runtimeFactory: $runtimeFactory,
            queryIntentFactory: $intentFactory,
            queryFilterPipeline: $queryFilterPipeline,
        );

        $this->assertSame($columnResolver, $infrastructure->columnResolver());
        $this->assertSame($renderingPreparer, $infrastructure->renderingPreparer());
        $this->assertSame($runtimeFactory, $infrastructure->runtimeFactory());
        $this->assertSame($intentFactory, $infrastructure->queryIntentFactory());
        $this->assertSame($queryFilterPipeline, $infrastructure->queryFilterPipeline());
    }
}
