<?php

namespace Pentiminax\UX\DataTables\Enum;

enum Action: string
{
    case DELETE = 'DELETE';
    case EDIT = 'EDIT';
    case VIEW = 'VIEW';
}