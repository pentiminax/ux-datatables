<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\DuplicateActionNameException;
use Pentiminax\UX\DataTables\Exception\InvalidDataTableTokenException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\BulkAction;

/**
 * A DataTable derived from a signed action token, together with the entity it
 * operates on. Every entity-scoped Ajax route resolves one of these instead of
 * trusting a client-supplied entity or DataTable class name.
 */
final readonly class ResolvedDataTable
{
    /**
     * @param class-string|null               $entityClass
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    public function __construct(
        public AbstractDataTable $table,
        public ?string $entityClass,
        public string $dataTableClass,
    ) {
    }

    /**
     * @return class-string
     *
     * @throws InvalidDataTableTokenException when the table declares no entity class to operate on
     */
    public function requireEntityClass(): string
    {
        return $this->entityClass ?? throw InvalidDataTableTokenException::missingEntityClass($this->dataTableClass);
    }

    /**
     * @throws DuplicateActionNameException when more than one matching action shares the same name
     */
    public function findAction(ActionType $type, bool $collapsible = false): ?Action
    {
        $matches = [];

        foreach ($this->table->getConfiguredDataTable()->getColumns() as $column) {
            if (!$column instanceof ActionsProvidingColumnInterface) {
                continue;
            }

            foreach ($column->getActions()?->getActions() ?? [] as $action) {
                if ($type !== $action->getType()) {
                    continue;
                }

                if ($collapsible && !$action->isCollapsible()) {
                    continue;
                }

                $matches[] = $action;
            }
        }

        if (\count($matches) > 1) {
            throw DuplicateActionNameException::forName($matches[0]->getName());
        }

        return $matches[0] ?? null;
    }

    /**
     * The bulk action the table declares under this name, if any.
     *
     * Unlike {@see self::findAction()}, bulk actions are addressed by name: they are all custom,
     * so their type carries no information.
     */
    public function findBulkAction(string $name): ?BulkAction
    {
        return $this->table->getConfiguredDataTable()->getBulkActions()?->get($name);
    }
}
