<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Exception\InvalidBooleanMutationContextException;

final readonly class BooleanMutationContextResolver
{
    public function __construct(
        private AjaxDataTableRegistry $registry,
    ) {
    }

    public function resolve(string $dataTableToken, string $field): BooleanMutationContext
    {
        $dataTable = $this->registry->getForBooleanMutation($dataTableToken);

        if (null === $dataTable) {
            throw InvalidBooleanMutationContextException::invalidDataTableToken();
        }

        $entityClass = $dataTable->getEntityClass();

        if (null === $entityClass) {
            throw InvalidBooleanMutationContextException::missingEntityClass($dataTable::class);
        }

        foreach ($dataTable->getConfiguredDataTable()->getColumns() as $column) {
            if (!$column instanceof BooleanColumn || !$column->isRenderedAsSwitch()) {
                continue;
            }

            if ($field !== $this->resolveEffectiveField($column)) {
                continue;
            }

            return new BooleanMutationContext(
                entityClass: $entityClass,
                dataTableClass: $dataTable::class,
                field: $field,
            );
        }

        throw InvalidBooleanMutationContextException::fieldNotSwitchable($field, $dataTable::class);
    }

    private function resolveEffectiveField(BooleanColumn $column): string
    {
        foreach ([$column->getToggleField(), $column->getField(), $column->getData(), $column->getName()] as $field) {
            if (\is_string($field) && '' !== $field) {
                return $field;
            }
        }

        return '';
    }
}
