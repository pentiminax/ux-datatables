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

    protected function isSummarizable(): bool
    {
        return false;
    }

    public static function fromActions(string $name, string $title, Actions $actions): static
    {
        $instance = new static(
            name: $name,
            title: '' === $title ? $name : $title,
        );

        $instance->actions = $actions;

        $className = trim(implode(' ', array_filter([
            $actions->getColumnClassName(),
            $actions->getAlignment()?->cssClass(),
        ])));

        if ('' !== $className) {
            $instance->className($className);
        }

        $columnControl = $actions->getColumnControl();

        if (null !== $columnControl) {
            $instance->columnControl($columnControl);
        }

        return $instance;
    }

    private function __construct(string $name, string $title)
    {
        $this
            ->name($name)
            ->title($title)
            ->data(ActionRowDataResolver::ROW_ACTIONS_KEY)
            ->defaultContent('')
            ->orderable(false)
            ->searchable(false)
            ->disableGlobalSearch()
            ->disableColumnControl()
            ->type(ColumnType::STRING)
            ->exportable(false)
            ->responsivePriority(1);
    }

    public function getActions(): ?Actions
    {
        return $this->actions;
    }

    public function __clone(): void
    {
        if (null !== $this->actions) {
            $this->actions = clone $this->actions;
        }
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'actions' => $this->actions?->jsonSerialize(),
        ]);
    }
}
