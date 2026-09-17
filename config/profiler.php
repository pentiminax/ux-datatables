<?php

declare(strict_types=1);

use Pentiminax\UX\DataTables\Profiler\DataTableCollector;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Only imported when kernel.debug is true (see DataTablesBundle::loadExtension()) -- these
 * services eagerly record rendered tables and AJAX queries on every request via direct calls
 * from application code, not just when Symfony's own profiler pulls a collect(), so they
 * cannot be left registered unconditionally without a real (if small) per-request cost in
 * production. Every consumer of datatables.profiler (DataTablesExtension, AbstractDataTable,
 * DataTableInfrastructure) already treats it as optional and no-ops when it is absent, so
 * this import guard is the only thing standing between debug-only and always-on.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('datatables.profiler', DataTableProfiler::class)
        ->tag('kernel.reset', ['method' => 'reset'])
        ->private();

    $services->set('datatables.data_collector', DataTableCollector::class)
        ->arg(0, service('datatables.profiler'))
        ->tag('data_collector', [
            'id'       => 'datatables',
            'template' => '@DataTables/Collector/data_collector.html.twig',
        ])
        ->private();
};
