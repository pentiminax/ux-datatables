<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Profiler;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * Shared, request-scoped debug context for the Web Profiler.
 *
 * Registered with the `kernel.reset` tag so its state is cleared between
 * requests — required for FrankenPHP worker mode, where the process (and this
 * shared service) survives across requests.
 */
final class DataTableProfiler
{
    /** @var array<int, array<string, mixed>> */
    private array $renderedTables = [];

    /** @var array<int, array<string, mixed>> */
    private array $ajaxQueries = [];

    public function collectRenderedTable(string $class, DataTable $table): void
    {
        $options = $table->getOptions();

        $this->renderedTables[] = [
            'id'          => $table->getId(),
            'class'       => $class,
            'serverSide'  => $table->isServerSide(),
            'columnCount' => \count($table->getColumns()),
            'extensions'  => array_keys($table->getExtensions()),
            'ajax'        => $table->getOption('ajax'),
            'hasData'     => !empty($options['data']),
        ];
    }

    public function collectAjaxQuery(
        string $class,
        ?string $token,
        ?DataTableRequest $request,
        int $recordsTotal,
        int $recordsFiltered,
        float $durationMs,
    ): void {
        $this->ajaxQueries[] = [
            'class'           => $class,
            'token'           => $token,
            'request'         => $request,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'durationMs'      => $durationMs,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getRenderedTables(): array
    {
        return $this->renderedTables;
    }

    /** @return array<int, array<string, mixed>> */
    public function getAjaxQueries(): array
    {
        return $this->ajaxQueries;
    }

    public function reset(): void
    {
        $this->renderedTables = [];
        $this->ajaxQueries    = [];
    }
}
