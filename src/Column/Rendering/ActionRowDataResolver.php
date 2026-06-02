<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column\Rendering;

use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface as PropertyAccessExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class ActionRowDataResolver
{
    public const string ROW_ACTIONS_KEY = '__ux_datatables_actions';

    private readonly PermissionChecker $permissionChecker;
    private readonly PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        ?PermissionChecker $permissionChecker = null,
        ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new PermissionChecker();
        $this->propertyAccessor  = $propertyAccessor  ?? PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param iterable<ColumnInterface> $columns
     */
    public function resolveRow(array $row, mixed $sourceRow, iterable $columns): array
    {
        if (\array_key_exists(self::ROW_ACTIONS_KEY, $row)) {
            return $row;
        }

        $actions = [];

        foreach ($columns as $column) {
            if (!$column instanceof ActionsProvidingColumnInterface) {
                continue;
            }

            foreach ($column->getActions()?->getActions() ?? [] as $action) {
                if ($action->hasPerRowPermission()) {
                    $resolver = $action->getPermissionSubjectResolver();
                    $subject  = null !== $resolver ? $resolver($sourceRow) : null;

                    if (!$this->permissionChecker->isGranted((string) $action->getPermission(), $subject)) {
                        continue;
                    }
                }

                $actionData = $this->resolveActionData($action, $sourceRow);

                if ([] === $actionData) {
                    continue;
                }

                $actions[$action->getType()->value] = $actionData;
            }
        }

        if ([] === $actions) {
            return $row;
        }

        $row[self::ROW_ACTIONS_KEY] = $actions;

        return $row;
    }

    /**
     * @return array{url?: string, id?: string|int}
     */
    private function resolveActionData(Action $action, mixed $sourceRow): array
    {
        $data = [];
        $url  = $action->resolveUrl($sourceRow);

        if (null !== $url) {
            $data['url'] = $url;
        }

        if (ActionType::Detail !== $action->getType()) {
            $id = $this->resolveId($sourceRow, $action->getIdField());

            if (null !== $id) {
                $data['id'] = $id;
            }
        }

        return $data;
    }

    private function resolveId(mixed $sourceRow, string $idField): mixed
    {
        if (\is_array($sourceRow)) {
            return \array_key_exists($idField, $sourceRow) ? $this->normalizeId($sourceRow[$idField]) : null;
        }

        if (!\is_object($sourceRow)) {
            return null;
        }

        try {
            if (!$this->propertyAccessor->isReadable($sourceRow, $idField)) {
                return null;
            }

            return $this->normalizeId($this->propertyAccessor->getValue($sourceRow, $idField));
        } catch (PropertyAccessExceptionInterface) {
            return null;
        }
    }

    private function normalizeId(mixed $id): string|int|null
    {
        if (\is_string($id) || \is_int($id)) {
            return $id;
        }

        if ($id instanceof \Stringable) {
            return (string) $id;
        }

        return null;
    }
}
