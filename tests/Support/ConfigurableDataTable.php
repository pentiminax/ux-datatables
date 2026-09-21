<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * A minimal table whose configuration hooks are supplied per instance.
 *
 * Use it for tests that only need columns, actions, extensions, or table options.
 * Fixtures needing a class-level attribute, an infrastructure override, or any extra
 * behavior must keep declaring their own subclass.
 *
 * The properties are prefixed because AbstractDataTable already owns $columns and $table.
 *
 * @internal
 */
final class ConfigurableDataTable extends AbstractDataTable
{
    /**
     * @param list<ColumnInterface>                     $columnsConfig
     * @param (\Closure(Actions): Actions)|null         $actions
     * @param (\Closure(BulkActions): BulkActions)|null $bulkActions
     * @param (\Closure(DataTable): DataTable)|null     $configureTable
     */
    public function __construct(
        private readonly array $columnsConfig,
        private readonly ?\Closure $actions = null,
        private readonly ?\Closure $configureTable = null,
        private readonly ?DataProviderInterface $dataProvider = null,
        private readonly ?\Closure $bulkActions = null,
    ) {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return null === $this->configureTable ? $table : ($this->configureTable)($table);
    }

    public function configureColumns(): iterable
    {
        return $this->columnsConfig;
    }

    public function configureActions(Actions $actions): Actions
    {
        return null === $this->actions ? $actions : ($this->actions)($actions);
    }

    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return null === $this->bulkActions ? $actions : ($this->bulkActions)($actions);
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return $this->dataProvider;
    }
}
