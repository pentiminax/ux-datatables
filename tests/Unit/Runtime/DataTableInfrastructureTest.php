<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
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

        $this->assertInstanceOf(ColumnResolver::class, $infrastructure->columnResolver);
        $this->assertInstanceOf(RenderingPreparer::class, $infrastructure->renderingPreparer);
        $this->assertInstanceOf(DataTableRuntimeFactory::class, $infrastructure->runtimeFactory);
        $this->assertInstanceOf(QueryFilterPipeline::class, $infrastructure->queryFilterPipeline);
        $this->assertSame([], $infrastructure->options);
        $this->assertSame([], $infrastructure->attributes);
        $this->assertSame([], $infrastructure->extensions);
        $this->assertNull($infrastructure->profiler);
    }

    #[Test]
    public function the_default_rendering_preparer_filters_columns_by_permission(): void
    {
        $preparer = DataTableInfrastructure::createDefault()->renderingPreparer;
        $table    = (new DataTable('secured'))->add(TextColumn::new('secret')->permission('ROLE_ADMIN'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ROLE_ADMIN');

        $preparer->prepareAfterDataHydration($table, $preparer->resolveOptIns($table, null));
    }

    #[Test]
    public function it_returns_the_collaborators_it_was_given(): void
    {
        $columnResolver      = new ColumnResolver(new AuthorizationChecker());
        $renderingPreparer   = new RenderingPreparer(new AuthorizationChecker(), new ColumnResolver(new AuthorizationChecker()));
        $runtimeFactory      = new DataTableRuntimeFactory(new AuthorizationChecker());
        $intentFactory       = new DefaultDataTableQueryIntentFactory();
        $queryFilterPipeline = new QueryFilterPipeline($intentFactory);
        $profiler            = new DataTableProfiler();

        $infrastructure = DataTableInfrastructure::createDefault(
            columnResolver: $columnResolver,
            renderingPreparer: $renderingPreparer,
            runtimeFactory: $runtimeFactory,
            queryIntentFactory: $intentFactory,
            queryFilterPipeline: $queryFilterPipeline,
            profiler: $profiler,
        );

        $this->assertSame($columnResolver, $infrastructure->columnResolver);
        $this->assertSame($renderingPreparer, $infrastructure->renderingPreparer);
        $this->assertSame($runtimeFactory, $infrastructure->runtimeFactory);
        $this->assertSame($queryFilterPipeline, $infrastructure->queryFilterPipeline);
        $this->assertSame($profiler, $infrastructure->profiler);
    }

    #[Test]
    public function it_stamps_the_bundle_defaults_onto_every_table_it_creates(): void
    {
        $infrastructure = DataTableInfrastructure::createDefault(
            options: ['pageLength' => 25],
            attributes: ['class' => 'table table-striped'],
            extensions: ['select' => ['style' => 'multi']],
        );

        $table = $infrastructure->createDataTable('books');

        $this->assertSame('books', $table->getId());
        $this->assertSame(25, $table->getOption('pageLength'));
        $this->assertSame(['class' => 'table table-striped'], $table->getAttributes());
        $this->assertSame('multi', $table->getExtensions()['select']['style']);
    }

    #[Test]
    public function it_creates_tables_without_defaults_when_none_are_configured(): void
    {
        $table = DataTableInfrastructure::createDefault()->createDataTable('books');

        $this->assertSame([], $table->getAttributes());
        $this->assertSame([], $table->getExtensions());
        $this->assertNull($table->getOption('pageLength'));
    }
}
