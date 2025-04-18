<?php

namespace Pentiminax\UX\DataTables\Enum;

enum Feature: string
{
    case BUTTONS = 'buttons';
    case INFO = 'info';
    case PAGE_LENGTH = 'pageLength';
    case PAGING = 'paging';
    case SEARCH = 'search';
}