<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataCollector;

use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DataTableCollector extends AbstractDataCollector
{
    public function __construct(
        private readonly DataTableProfiler $profiler,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $tables = $this->profiler->getRenderedTables();

        // Rendered-table records hold only scalars/arrays, so they serialize as-is.
        // AJAX query records carry a DataTableRequest object -> clone that field only.
        $queries = array_map(function (array $query): array {
            $query['request'] = $this->cloneVar($query['request']);

            return $query;
        }, $this->profiler->getAjaxQueries());

        $this->data = [
            'tables'     => $tables,
            'queries'    => $queries,
            'tableCount' => \count($tables),
            'queryCount' => \count($queries),
        ];
    }

    public function getName(): string
    {
        return 'datatables';
    }

    /** @return array<int, array<string, mixed>> */
    public function getTables(): array
    {
        return $this->data['tables'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getQueries(): array
    {
        return $this->data['queries'] ?? [];
    }

    public function getTableCount(): int
    {
        return $this->data['tableCount'] ?? 0;
    }

    public function getQueryCount(): int
    {
        return $this->data['queryCount'] ?? 0;
    }
}
