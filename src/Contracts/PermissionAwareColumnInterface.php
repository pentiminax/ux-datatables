<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

interface PermissionAwareColumnInterface
{
    public function getPermission(): ?string;
}
