<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\Mercure\MercureTableConfigResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Runtime\TableDefinition;
use Pentiminax\UX\DataTables\Runtime\TableDefinitionBuilder;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Psr\Log\LoggerInterface;

/**
 * Builds the bundle runtime by hand, the way the container wires it, for tests that exercise a table
 * without booting a kernel. Everything not named gets the bundle default: no profiler, no Twig, no
 * `data_tables` defaults, and a permissive authorization checker.
 *
 * @internal
 */
trait BuildsRuntime
{
    /**
     * @param array<string, mixed> $options               `data_tables.options`
     * @param array<string, mixed> $attributes            `data_tables.table_attributes`
     * @param array<string, mixed> $extensions            `data_tables.extensions`
     * @param iterable<object>     $defaultsConfigurators
     */
    private static function buildRuntime(
        array $options = [],
        array $attributes = [],
        array $extensions = [],
        iterable $defaultsConfigurators = [],
        ?AuthorizationChecker $checker = null,
        ?RenderingPreparer $preparer = null,
        ?AutoDataProviderFactory $autoDataProviderFactory = null,
        ?DataTableProfiler $profiler = null,
        ?LoggerInterface $logger = null,
        int $hydrationRowThreshold = 0,
        int $maxPageLength = 1000,
        ?ApiResourceCollectionUrlResolver $collectionUrlResolver = null,
        ?TableDefinitionBuilder $definitions = null,
        ?TemplateColumnRenderer $templateColumnRenderer = null,
        ?ManagerRegistry $doctrine = null,
        ?ActionRowDataResolver $actionRowDataResolver = null,
        ?UrlColumnDataResolver $urlColumnDataResolver = null,
        ?MercureTableConfigResolver $mercure = null,
    ): DataTableRuntime {
        $checker ??= new AuthorizationChecker();
        $columnResolver = new ColumnResolver($checker);

        return new DataTableRuntime(
            definitions: $definitions ?? new TableDefinitionBuilder(
                $columnResolver,
                $options,
                $attributes,
                $extensions,
                defaultsConfigurators: $defaultsConfigurators,
                collectionUrlResolver: $collectionUrlResolver,
            ),
            renderingPreparer: $preparer ?? new RenderingPreparer($checker, $columnResolver, $collectionUrlResolver),
            columnResolver: $columnResolver,
            permissionChecker: $checker,
            queryFilterPipeline: new QueryFilterPipeline(new DefaultDataTableQueryIntentFactory()),
            autoDataProviderFactory: $autoDataProviderFactory ?? new AutoDataProviderFactory(),
            templateColumnRenderer: $templateColumnRenderer,
            actionRowDataResolver: $actionRowDataResolver,
            urlColumnDataResolver: $urlColumnDataResolver,
            maxPageLength: $maxPageLength,
            doctrine: $doctrine,
            profiler: $profiler,
            logger: $logger,
            hydrationRowThreshold: $hydrationRowThreshold,
            mercure: $mercure ?? new MercureTableConfigResolver(),
        );
    }

    private static function definitionOf(AbstractDataTable $table, ?DataTableRuntime $runtime = null): TableDefinition
    {
        return ($runtime ?? self::buildRuntime())->definition($table);
    }
}
