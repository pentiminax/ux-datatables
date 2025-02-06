<?php



namespace Pentiminax\UX\DataTables\Model;

class DataTable
{
    private DataTableFeaturesOptions $featuresOptions;

    private DataTableDataOptions $dataOptions;

    public function __construct(
        private readonly string $id,
        private array $options = [],
        private array $attributes = []
    ){
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getFeaturesOptions(): DataTableFeaturesOptions
    {
        return $this->featuresOptions;
    }

    public function setFeaturesOptions(DataTableFeaturesOptions $featuresOptions): static
    {
        $this->featuresOptions = $featuresOptions;

        return $this;
    }

    public function getDataOptions(): DataTableDataOptions
    {
        return $this->dataOptions;
    }

    public function setDataOptions(DataTableDataOptions $dataOptions): static
    {
        $this->dataOptions = $dataOptions;

        return $this;
    }

    public function getDataController(): ?string
    {
        return $this->attributes['data-controller'] ?? null;
    }
}
