<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum ButtonType: string
{
    case COLLECTION                  = 'collection';
    case COLUMN_CONTROL_SEARCH_CLEAR = 'ccSearchClear';
    case COLUMN_VISIBILITY           = 'colvis';
    case COLUMN_VISIBILITY_GROUP     = 'colvisGroup';
    case COLUMN_VISIBILITY_RESTORE   = 'colvisRestore';
    case COPY                        = 'copy';
    case CSV                         = 'csv';
    case CUSTOM                      = 'custom';
    case EXCEL                       = 'excel';
    case PAGE_LENGTH                 = 'pageLength';
    case PDF                         = 'pdf';
    case PRINT                       = 'print';
    case SPACER                      = 'spacer';
}
