<?php

namespace Pentiminax\UX\DataTables\Model;

class ActionColumn implements ColumnInterface
{
    private array $actions = [];

    public static function new(string $name, string $title, array $actions): self
    {
        return new self($name, $title, $actions);
    }

    private function __construct(
        private readonly string $name,
        private readonly string $title,
        array $actions
    )
    {
        foreach ($actions as $action) {
            $this->addAction($action);
        }
    }

    public function addAction(array $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'data' => null,
            'className' => 'not-exportable',
            'name' => $this->name,
            'title' => $this->title,
            'actions' => $this->actions,
        ];
    }
}