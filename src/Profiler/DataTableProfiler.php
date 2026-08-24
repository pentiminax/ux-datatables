<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Profiler;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\ExtensionInterface;
use Pentiminax\UX\DataTables\Contracts\LayoutAwareExtensionInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * Shared, request-scoped debug context for the Web Profiler.
 *
 * Registered with the `kernel.reset` tag so its state is cleared between
 * requests — required for FrankenPHP worker mode, where the process (and this
 * shared service) survives across requests.
 *
 * Records hold scalars and plain arrays only: row data never reaches the
 * profile, so business values are not copied into var/cache/dev/profiler.
 */
final class DataTableProfiler
{
    /** @var array<int, array<string, mixed>> */
    private array $renderedTables = [];

    /** @var array<int, array<string, mixed>> */
    private array $ajaxQueries = [];

    /**
     * @param array{entityClass?: string|null, originalColumns?: list<ColumnInterface>, allowedColumns?: list<ColumnInterface>} $context
     *                                                                                                                                   Rendering context only available to the caller, such as the columns removed
     *                                                                                                                                   by static permission filtering
     */
    public function collectRenderedTable(string $class, DataTable $table, array $context = []): void
    {
        $options = $table->getOptions();

        $this->renderedTables[] = [
            'id'                       => $table->getId(),
            'class'                    => $class,
            'entityClass'              => $context['entityClass'] ?? null,
            'serverSide'               => $table->isServerSide(),
            'columnCount'              => \count($table->getColumns()),
            'extensions'               => $this->describeExtensions($table),
            'ajax'                     => $table->getOption('ajax'),
            'hasData'                  => !empty($options['data']),
            'rowCount'                 => \is_array($options['data'] ?? null) ? \count($options['data']) : 0,
            'dataController'           => $table->getDataController(),
            'forwardedQueryParameters' => $table->getForwardedQueryParameters(),
            'columns'                  => $this->describeColumns($table->getColumns()),
            'deniedColumns'            => $this->describeDeniedColumns($context),
            'filters'                  => $table->getFilters()?->jsonSerialize() ?? [],
            'mercure'                  => $this->describeMercure($table),
            'editModal'                => [
                'template' => $table->getEditModalTemplate(),
                'adapter'  => $table->getEditModalAdapter(),
            ],
        ];
    }

    public function collectAjaxQuery(
        string $class,
        ?string $token,
        ?DataTableRequest $request,
        int $recordsTotal,
        int $recordsFiltered,
        float $durationMs,
        ?string $providerClass = null,
        ?string $entityClass = null,
        int $rowCount = 0,
        int $payloadBytes = 0,
        ?int $httpStatus = null,
    ): void {
        $this->ajaxQueries[] = [
            'class'           => $class,
            'token'           => $token,
            'request'         => $request,
            'requestSummary'  => $this->summarizeRequest($request),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'durationMs'      => $durationMs,
            'providerClass'   => $providerClass,
            'entityClass'     => $entityClass,
            'rowCount'        => $rowCount,
            'payloadBytes'    => $payloadBytes,
            'httpStatus'      => $httpStatus,
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

    /**
     * Reads the mutable collection instead of DataTable::getExtensions(), whose
     * JSON payload drops layout-aware extensions such as Buttons.
     *
     * @return list<array<string, mixed>>
     */
    private function describeExtensions(DataTable $table): array
    {
        return array_values(array_map(
            static fn (ExtensionInterface $extension): array => [
                'key'         => $extension->getKey(),
                'class'       => $extension::class,
                'layoutAware' => $extension instanceof LayoutAwareExtensionInterface,
                'options'     => $extension->jsonSerialize(),
            ],
            $table->getExtensionsCollection()->all(),
        ));
    }

    /**
     * @param array<string, ColumnInterface> $columns
     *
     * @return list<array<string, mixed>>
     */
    private function describeColumns(array $columns): array
    {
        return array_values(array_map(
            static fn (ColumnInterface $column): array => $column->jsonSerialize(),
            $columns,
        ));
    }

    /**
     * @param array{originalColumns?: list<ColumnInterface>, allowedColumns?: list<ColumnInterface>} $context
     *
     * @return list<string>
     */
    private function describeDeniedColumns(array $context): array
    {
        $original = $context['originalColumns'] ?? null;
        $allowed  = $context['allowedColumns']  ?? null;

        if (!\is_array($original) || !\is_array($allowed)) {
            return [];
        }

        $names = static fn (array $columns): array => array_map(
            static fn (ColumnInterface $column): string => $column->getName(),
            $columns,
        );

        return array_values(array_diff($names($original), $names($allowed)));
    }

    /** @return array<string, mixed>|null */
    private function describeMercure(DataTable $table): ?array
    {
        $config = $table->getMercureConfig();

        if (null === $config) {
            return null;
        }

        // Not jsonSerialize(): it throws until the hub URL has been resolved.
        return [
            'topics'          => $config->topics,
            'hubUrl'          => $config->hubUrl,
            'withCredentials' => $config->withCredentials,
            'debounceMs'      => $config->debounceMs,
        ];
    }

    /**
     * Flattens the request into scalars so the panel can render real tables
     * instead of a collapsed variable dump.
     *
     * @return array<string, mixed>|null
     */
    private function summarizeRequest(?DataTableRequest $request): ?array
    {
        if (null === $request) {
            return null;
        }

        $length = $request->length;

        return [
            'draw'   => $request->draw,
            'start'  => $request->start,
            'length' => $length,
            'page'   => $length > 0 ? intdiv($request->start, $length) + 1 : 1,
            'search' => null === $request->search ? null : [
                'value' => $request->search->value,
                'regex' => $request->search->regex,
            ],
            'order' => array_values(array_map(
                static fn (Order $order): array => [
                    'column' => $order->column,
                    'name'   => $order->name,
                    'dir'    => $order->dir,
                ],
                $request->order,
            )),
            'columns' => $this->summarizeRequestColumns($request),
            'filters' => $request->filters,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function summarizeRequestColumns(DataTableRequest $request): array
    {
        $columns = [];
        $index   = 0;

        foreach ($request->columns->all() as $column) {
            \assert($column instanceof Column);

            $columns[] = [
                'index'         => $index++,
                'data'          => $column->data,
                'name'          => $column->name,
                'searchable'    => $column->searchable,
                'orderable'     => $column->orderable,
                'searchValue'   => $column->search?->value,
                'columnControl' => $this->summarizeColumnControl($column->columnControl),
            ];
        }

        return $columns;
    }

    /**
     * ColumnControl submits its own scalar search (value/logic/type) and searchList
     * (checkbox list of selected values) independently of the plain column search box
     * above -- summarized separately here so it shows up in the panel at all instead of
     * being silently dropped alongside the request.
     *
     * @return array{value: ?string, logic: ?string, type: ?string, list: list<mixed>}|null
     */
    private function summarizeColumnControl(?ColumnControl $columnControl): ?array
    {
        if (null === $columnControl) {
            return null;
        }

        $search = $columnControl->search;

        if (null === $search && [] === $columnControl->list) {
            return null;
        }

        return [
            'value' => $search?->value,
            'logic' => $search?->logic->value,
            'type'  => $search?->type,
            'list'  => $columnControl->list,
        ];
    }
}
