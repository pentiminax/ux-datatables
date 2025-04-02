<?php

namespace Pentiminax\UX\DataTables\Enum;

enum ButtonType: string
{
    case COPY = 'copy';
    case CSV = 'csv';
    case EXCEL = 'excel';
    case PDF = 'pdf';
    case PRINT = 'print';
}