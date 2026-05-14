<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column\Rendering;

use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Security\PermissionChecker;

final class ActionRowDataResolver
{
    public const string ROW_ACTIONS_KEY = '__ux_datatables_actions';

    private readonly PermissionChecker $permissionChecker;

    public function __construct(?PermissionChecker $permissionChecker = null)
    {
        $this->permissionChecker = $permissionChecker ?? new PermissionChecker();
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

                $url = $action->resolveUrl($sourceRow);

                if (null === $url) {
                    continue;
                }

                $actions[$action->getType()->value] = [
                    'url' => $url,
                ];
            }
        }

        if ([] === $actions) {
            return $row;
        }

        $row[self::ROW_ACTIONS_KEY] = $actions;

        return $row;
    }
}
