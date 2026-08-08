<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

final class RowGroupExtension extends AbstractExtension
{
    /**
     * @param int|string|list<int|string> $dataSrc
     */
    public function __construct(
        private readonly int|string|array $dataSrc,
        private readonly bool $enable = true,
        private readonly string $className = 'group',
        private readonly string $startClassName = 'group-start',
        private readonly string $endClassName = 'group-end',
        private readonly ?string $emptyDataGroup = 'No group',
    ) {
    }

    public function getKey(): string
    {
        return 'rowGroup';
    }

    public function jsonSerialize(): array
    {
        return [
            'dataSrc'        => $this->dataSrc,
            'enable'         => $this->enable,
            'className'      => $this->className,
            'startClassName' => $this->startClassName,
            'endClassName'   => $this->endClassName,
            'emptyDataGroup' => $this->emptyDataGroup,
        ];
    }
}
