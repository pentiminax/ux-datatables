<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

final class Permission
{
    public const string DT_ACCESS_TABLE     = 'DT_ACCESS_TABLE';
    public const string DT_EXECUTE_ACTION   = 'DT_EXECUTE_ACTION';
    public const string DT_EDIT_ROW         = 'DT_EDIT_ROW';
    public const string DT_DELETE_ROW       = 'DT_DELETE_ROW';
    public const string DT_VIEW_ROW_DETAILS = 'DT_VIEW_ROW_DETAILS';

    private function __construct()
    {
    }
}
