<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Model\Actions;

class ActionColumn extends AbstractColumn implements ActionsProvidingColumnInterface
{
    private ?Actions $actions = null;

    public static function fromActions(string $name, string $title, Actions $actions): static
    {
        $instance = new self(
            name: $name,
            title: '' === $title ? $name : $title,
        );

        $instance->actions = $actions;

        return $instance;
    }

    private function __construct(string $name, string $title)
    {
        $this
            ->setName($name)
            ->setTitle($title)
            ->setData(ActionRowDataResolver::ROW_ACTIONS_KEY)
            ->setDefaultContent('')
            ->setOrderable(false)
            ->setSearchable(false)
            ->disableGlobalSearch()
            ->disableColumnControl()
            ->setType(ColumnType::STRING)
            ->setExportable(false);
    }

    public function getActions(): ?Actions
    {
        return $this->actions;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'actions'            => $this->actions?->jsonSerialize(),
            'responsivePriority' => 1,
        ]);
    }
}
