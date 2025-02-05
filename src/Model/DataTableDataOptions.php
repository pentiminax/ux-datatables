<?php

namespace Pentiminax\UX\DataTables\Model;

class DataTableDataOptions
{
    private array $options = [];

    /**
     * Load data for the table's content from an Ajax source.
     */
    public function ajax(AjaxOption $ajaxOption): static
    {
        $this->options['ajax'] = $ajaxOption->toArray();

        return $this;
    }

    /**
     * Data to use as the display data for the table.
     */
    public function data(array $data): static
    {
        $this->options['data'] = $data;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}