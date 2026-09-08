<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

/**
 * ColumnControl's `columnControl` configuration: a list of control groups, each placing content in
 * one header or footer row.
 *
 * A target is either a header row index (`0`, `1`, ...) or a `tfoot` string (`'tfoot'`,
 * `'tfoot:1'`). ColumnControl creates the targeted row when the table does not render one, so a
 * footer target needs no `<tfoot>` markup.
 *
 * Constructed without arguments it serializes {@see self::DEFAULT_CONTROLS}: order controls on the
 * first header row and a search input on the second. Pass an explicit list to replace them, `[]`
 * to start from nothing, or call {@see self::add()} to build the list up.
 */
class ColumnControlExtension extends AbstractExtension
{
    /**
     * @var list<array{target: int|string, content: list<mixed>}>
     */
    public const DEFAULT_CONTROLS = [
        [
            'target'  => 0,
            'content' => [
                'order',
                [
                    'orderAsc',
                    'orderDesc',
                    'spacer',
                    'orderAddAsc',
                    'orderAddDesc',
                    'spacer',
                    'orderRemove',
                ],
            ],
        ],
        [
            'target'  => 1,
            'content' => ['search'],
        ],
    ];

    /**
     * @param list<array{target: int|string, content: list<mixed>}>|null $controls null keeps the
     *                                                                             defaults
     */
    public function __construct(
        private ?array $controls = null,
    ) {
    }

    /**
     * Append one control group, dropping the defaults on the first call so an explicitly built
     * configuration never inherits them.
     *
     * @param list<mixed> $content content descriptors, as in the DataTables `columnControl` option
     */
    public function add(int|string $target, array $content): static
    {
        $this->controls ??= [];

        $this->controls[] = [
            'target'  => $target,
            'content' => $content,
        ];

        return $this;
    }

    public function getKey(): string
    {
        return 'columnControl';
    }

    public function jsonSerialize(): array
    {
        return $this->controls ?? self::DEFAULT_CONTROLS;
    }
}
