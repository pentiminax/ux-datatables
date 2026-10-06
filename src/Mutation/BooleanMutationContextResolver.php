<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Exception\InvalidBooleanMutationContextException;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;

final readonly class BooleanMutationContextResolver
{
    public function __construct(
        private AjaxDataTableRegistry $registry,
        private readonly AuthorizationChecker $permissionChecker = new AuthorizationChecker(),
    ) {
    }

    public function resolve(string $dataTableToken, string $field): BooleanMutationContext
    {
        $resolved = $this->registry->resolveAction($dataTableToken);

        foreach ($resolved->table->getConfiguredDataTable()->getColumns() as $column) {
            if (!$column instanceof BooleanColumn || !$column->isRenderedAsSwitch()) {
                continue;
            }

            $permission = $column->getPermission();
            if (null !== $permission && !$this->permissionChecker->isGranted($permission)) {
                continue;
            }

            $effectiveField = $this->resolveEffectiveField($column);
            if ('' === $effectiveField || $field !== $effectiveField) {
                continue;
            }

            return new BooleanMutationContext(
                entityClass: $column->getEntityClass() ?? $resolved->requireEntityClass(),
                dataTableClass: $resolved->dataTableClass,
                field: $field,
                idField: $this->resolveIdField($column),
            );
        }

        throw InvalidBooleanMutationContextException::fieldNotSwitchable($field, $resolved->dataTableClass);
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

    private function resolveIdField(BooleanColumn $column): string
    {
        $idField = $column->getCustomOption(BooleanColumn::OPTION_TOGGLE_ID_FIELD);

        return \is_string($idField) && '' !== $idField ? $idField : 'id';
    }
}
