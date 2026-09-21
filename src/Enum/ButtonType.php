<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum ButtonType: string
{
    case COLLECTION                  = 'collection';
    case COLUMN_CONTROL_SEARCH_CLEAR = 'ccSearchClear';
    case COLUMN_VISIBILITY           = 'colvis';
    case COPY                        = 'copy';
    case CSV                         = 'csv';
    case CUSTOM                      = 'custom';
    case EXCEL                       = 'excel';
    case PDF                         = 'pdf';
    case PRINT                       = 'print';
}
