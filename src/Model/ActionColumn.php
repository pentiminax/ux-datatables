<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\Action;

class ActionColumn implements ColumnInterface
{
    public static function new(string $name, string $title, Action $action, string $actionLabel, string $actionUrl): self
    {
        return new self($name, $title, $action, $actionLabel, $actionUrl);
    }

    private function __construct(
        private string $name,
        private string $title,
        private Action $action,
        private string $actionLabel,
        private string $actionUrl
    ){
    }

    public function jsonSerialize(): array
    {
        return [
            'data' => null,
            'className' => 'not-exportable',
            'name' => $this->name,
            'title' => $this->title,
            'action' => $this->action->value,
            'actionLabel' => $this->actionLabel,
            'actionUrl' => $this->actionUrl,
        ];
    }
}