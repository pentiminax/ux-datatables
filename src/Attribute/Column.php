<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

/**
 * @deprecated since 1.1, use {@see DataTableColumn} instead. Removed in 2.0.
 *
 * Every named parameter below became an entry of {@see DataTableColumn::$options}, which any column
 * setter can answer instead of the fixed list this signature froze.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Column extends DataTableColumn
{
    public function __construct(
        ?string $type = null,
        ?string $name = null,
        public readonly ?string $title = null,
        public readonly bool $orderable = true,
        public readonly bool $searchable = true,
        public readonly bool $visible = true,
        public readonly bool $exportable = true,
        public readonly bool $globalSearchable = true,
        public readonly ?string $width = null,
        public readonly ?string $className = null,
        public readonly ?string $cellType = null,
        public readonly ?string $defaultContent = null,
        public readonly ?string $field = null,
        public readonly ?string $format = null,
        public readonly array|bool $renderAsBadges = false,
        public readonly bool $hideWhenUpdating = false,
        ?int $position = null,
        public readonly ?int $responsivePriority = null,
    ) {
        trigger_deprecation('pentiminax/ux-datatables', '1.1', 'The "%s" attribute is deprecated, use "%s" instead.', self::class, DataTableColumn::class);

        $options = [
            'title'              => $title,
            'orderable'          => $orderable,
            'searchable'         => $searchable,
            'visible'            => $visible,
            'exportable'         => $exportable,
            'globalSearchable'   => $globalSearchable,
            'hideWhenUpdating'   => $hideWhenUpdating,
            'width'              => $width,
            'className'          => $className,
            'cellType'           => $cellType,
            'defaultContent'     => $defaultContent,
            'field'              => $field,
            'format'             => $format,
            'responsivePriority' => $responsivePriority,
            'renderAsBadges'     => false === $renderAsBadges ? null : $renderAsBadges,
        ];

        parent::__construct(
            type: $type,
            options: array_filter($options, static fn (mixed $value): bool => null !== $value),
            name: $name,
            position: $position,
        );
    }
}
