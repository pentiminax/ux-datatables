<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ColumnControlExtension implements ExtensionInterface
{
    public function getKey(): string
    {
        return 'columnControl';
    }

    public function jsonSerialize(): array
    {
        return [
            [
                'target' => 0,
                'content' => [
                    'order',
                    [
                        'orderAsc',
                        'orderDesc',
                        'spacer',
                        'orderAddAsc',
                        'orderAddDesc',
                        'spacer',
                        'orderRemove'
                    ]
                ],
            ],
            [
                'target' => 1,
                'content' => ['search'],
            ],
        ];
    }
}