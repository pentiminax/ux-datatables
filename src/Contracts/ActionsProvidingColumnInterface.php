<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Model\Actions;

/**
 * Marker interface for columns that contribute per-row action metadata
 * (URLs/labels) merged into the row payload by the action resolver.
 */
interface ActionsProvidingColumnInterface extends ColumnInterface
{
    /**
     * Returns the actions exposed for each row, or null when none are configured.
     */
    public function getActions(): ?Actions;
}
