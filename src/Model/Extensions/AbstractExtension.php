<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Contracts\ExtensionInterface;

abstract class AbstractExtension implements ExtensionInterface
{
    abstract public function getKey(): string;
}
