<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ResponsiveExtension extends AbstractExtension
{
    /**
     * @param list<array{name: string, width: int}>|null $breakpoints   omitted when null, so DataTables
     *                                                                  keeps its own built-in breakpoint
     *                                                                  list (its widest entry is not
     *                                                                  JSON-representable)
     * @param int|string                                 $detailsTarget column index or selector the
     *                                                                  hidden-column details control
     *                                                                  attaches to
     * @param string|false                               $detailsType   'inline'|'column'|'colvis', or
     *                                                                  false to disable the details
     *                                                                  display entirely
     */
    public function __construct(
        private readonly bool $auto = true,
        private readonly ?array $breakpoints = null,
        private readonly int|string $detailsTarget = 0,
        private readonly string|false $detailsType = 'inline',
        private readonly string $orthogonal = 'display',
    ) {
    }

    public function getKey(): string
    {
        return 'responsive';
    }

    public function jsonSerialize(): array
    {
        $config = [
            'auto'    => $this->auto,
            'details' => [
                'target' => $this->detailsTarget,
                'type'   => $this->detailsType,
            ],
            'orthogonal' => $this->orthogonal,
        ];

        if (null !== $this->breakpoints) {
            $config['breakpoints'] = $this->breakpoints;
        }

        return $config;
    }
}
