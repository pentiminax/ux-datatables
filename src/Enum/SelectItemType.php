<?php

namespace Pentiminax\UX\DataTables\Enum;

enum SelectItemType: string
{
    case ROW = 'row';

    case COLUMN = 'column';

    case CELL = 'cell';
}
