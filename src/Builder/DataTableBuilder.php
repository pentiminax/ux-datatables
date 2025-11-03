<?php

namespace Pentiminax\UX\DataTables\Builder;

use Pentiminax\UX\DataTables\Model\DataTable;

class DataTableBuilder implements DataTableBuilderInterface
{
    public function __construct(
        public array $options = [],
        public array $attributes = [],
        public array $extensions = [],
    ) {
    }

    public function createDataTable(?string $id = null): DataTable
    {
        return new DataTable(
            id: $id,
            options: $this->options,
            attributes: $this->attributes,
            extensions: $this->extensions
        );
    }
}
