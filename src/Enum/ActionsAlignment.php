<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum ActionsAlignment: string
{
    case Left   = 'left';
    case Center = 'center';
    case Right  = 'right';

    public function cssClass(): string
    {
        return "dt-$this->value";
    }
}
