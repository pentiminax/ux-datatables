<?php



namespace Pentiminax\UX\DataTables\Model;

class DataTable
{

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

    public function getDataController(): ?string
    {
        return $this->attributes['data-controller'] ?? null;
    }

    /**
     * Feature control DataTables' smart column width handling.
     */
    public function autoWidth(bool $autoWidth): static
    {
        $this->options['autoWidth'] = $autoWidth;

        return $this;
    }

    /**
     * Set a caption for the table
     */
    public function caption(string $caption): static
    {
        $this->options['caption'] = $caption;

        return $this;
    }

    /**
     * Feature control deferred rendering for additional speed of initialisation.
     */
    public function deferRender(bool $deferRender): static
    {
        $this->options['deferRender'] = $deferRender;

        return $this;
    }

    /**
     * Feature control table information display field.
     */
    public function info(bool $info): static
    {
        $this->options['info'] = $info;

        return $this;
    }

    /**
     * Feature control the end user's ability to change the paging display length of the table.
     */
    public function lengthChange(bool $lengthChange): static
    {
        $this->options['lengthChange'] = $lengthChange;

        return $this;
    }

    /**
     * Feature control ordering (sorting) abilities in DataTables.
     */
    public function ordering(bool $ordering): static
    {
        $this->options['ordering'] = $ordering;

        return $this;
    }

    /**
     * Enable or disable table pagination.
     */
    public function paging(bool $paging): static
    {
        $this->options['paging'] = $paging;

        return $this;
    }

    /**
     * Feature control the processing indicator.
     */
    public function processing(bool $processing): static
    {
        $this->options['processing'] = $processing;

        return $this;
    }

    /**
     * Horizontal scrolling
     */
    public function scrollX(bool $scrollX): static
    {
        $this->options['scrollX'] = $scrollX;

        return $this;
    }

    /**
     * Vertical scrolling
     */
    public function scrollY(string $scrollY): static
    {
        $this->options['scrollY'] = $scrollY;

        return $this;
    }

    /**
     * Feature control search (filtering) abilities
     */
    public function searching(bool $searching): static
    {
        $this->options['searching'] = $searching;

        return $this;
    }

    /**
     * Feature control table information display field.
     */
    public function serverSide(bool $serverSide): static
    {
        $this->options['serverSide'] = $serverSide;

        return $this;
    }

    /**
     * Feature control table information display field.
     */
    public function stateSave(bool $stateSave): static
    {
        $this->options['stateSave'] = $stateSave;

        return $this;
    }
}
