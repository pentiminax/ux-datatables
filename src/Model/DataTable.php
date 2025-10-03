<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ExtensionInterface;
use Pentiminax\UX\DataTables\Model\Extensions\ResponsiveExtension;
use Pentiminax\UX\DataTables\Model\Options\AjaxOption;
use Pentiminax\UX\DataTables\Model\Options\LayoutOption;
use Pentiminax\UX\DataTables\Model\Options\SearchOption;

class DataTable
{
    private DataTableOptions $options;

    private DataTableExtensions $extensions;

    public function __construct(
        private readonly string $id,
        array                   $options = [],
        private array           $attributes = [],
        array                   $extensions = [],
    )
    {
        $this->options = new DataTableOptions($options);
        $this->extensions = new DataTableExtensions($extensions);
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function getOptions(): array
    {
        $options = $this->options->getOptions();

        $this->addButtonsToLayout($options);

        return $options;
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
     * Initial order (sort) to apply to the table.
     * @param array $order Array of order configurations. Each element can be:
     *                     - An array with [column_index, direction]
     *                     - An object with {idx: number, dir: 'asc'|'desc'}
     *                     - An object with {name: string, dir: 'asc'|'desc'}
     */
    public function order(array $order): static
    {
        $this->options['order'] = $order;

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

    public function add(ColumnInterface $column): static
    {
        $this->options['columns'][] = $column;

        return $this;
    }

    /**
     * @param array|ColumnInterface[] $columns
     */
    public function columns(array $columns): static
    {
        foreach ($columns as $column) {
            $this->options->addColumn(
                $column instanceof ColumnInterface ? $column->jsonSerialize() : $column
            );
        }

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
    public function ordering(bool $handler = true, bool $indicators = true): static
    {
        $this->options['ordering'] = [
            'handler' => $handler,
            'indicators' => $indicators,
        ];

        return $this;
    }

    public function withoutOrdering(): static
    {
        $this->options['ordering'] = false;

        return $this;
    }


    public function paging(
        bool $boundaryNumbers = true,
        int $buttons = 7,
        bool $firstLast = true,
        bool $numbers = true,
        bool $previousNext = true
    ): static
    {
        $this->options['paging'] = [
            'boundaryNumbers' => $boundaryNumbers,
            'buttons' => $buttons,
            'firstLast' => $firstLast,
            'numbers' => $numbers,
            'previousNext' => $previousNext,
        ];

        return $this;
    }

    /**
     * Enable or disable table pagination.
     */
    public function withoutPaging(): static
    {
        $this->options['paging'] = false;

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

    /**
     * Define the starting point for data display when using DataTables with pagination.
     */
    public function displayStart(int $displayStart): static
    {
        $this->options['displayStart'] = $displayStart;

        return $this;
    }

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

    /**
     * Change the options in the page length select list.
     */
    public function lengthMenu(array $lengthMenu): static
    {
        $this->options['lengthMenu'] = $lengthMenu;

        return $this;
    }

    /**
     * Change the initial page length (number of rows per page).
     */
    public function pageLength(int $pageLength): static
    {
        $this->options['pageLength'] = $pageLength;

        return $this;
    }

    public function addExtension(ExtensionInterface $extension): static
    {
        $this->extensions->addExtension($extension);

        return $this;
    }

    /**
     * @param ExtensionInterface[] $extensions
     */
    public function extensions(array $extensions): static
    {
        foreach ($extensions as $extension) {
            $this->extensions->addExtension($extension);
        }

        return $this;
    }

    public function getExtensions(): array
    {
        return $this->extensions->jsonSerialize();
    }

    public function setExtensions(DataTableExtensions $extensions): static
    {
        $this->extensions = $extensions;

        return $this;
    }

    public function language(Language $language): static
    {
        $this->options->setLanguage($language);

        return $this;
    }

    /**
     * Set an initial search in DataTables and / or search options.
     */
    public function search(string $search): static
    {
        $this->options->setSearch($search);

        return $this;
    }

    public function layout(
        Feature $topStart = Feature::PAGE_LENGTH,
        Feature $topEnd = Feature::SEARCH,
        Feature $bottomStart = Feature::INFO,
        Feature $bottomEnd = Feature::PAGING,
    ): static
    {
        $this->options['layout'] = new LayoutOption(
            table: $this,
            topStart: $topStart,
            topEnd: $topEnd,
            bottomStart: $bottomStart,
            bottomEnd: $bottomEnd,
        );

        return $this;
    }

    public function responsive(): static
    {
        $this->extensions->addExtension(new ResponsiveExtension());

        return $this;
    }

    public function columnControl(): static
    {
        $this->extensions->addExtension(new ColumnControlExtension());

        return $this;
    }

    public function withSearchOption(SearchOption $searchOption): static
    {
        $this->options['search'] = $searchOption->jsonSerialize();

        return $this;
    }

    /**
     * @return ColumnInterface[]
     */
    public function getColumns(): array
    {
        return $this->options['columns'] ?? [];
    }

    public function isServerSide(): bool
    {
        return $this->options['serverSide'] ?? false;
    }

    private function addButtonsToLayout(array &$options): void
    {
        $layout = $options['layout'] ?? [];

        if (!\is_array($layout)) {
            return;
        }

        $buttonsExtension = $this->extensions->getButtonsExtension();

        if (!$buttonsExtension) {
            return;
        }

        foreach ($layout as $position => $feature) {
            if ($feature === Feature::BUTTONS->value) {
                $options['layout'][$position] = [
                    'buttons' => $buttonsExtension->jsonSerialize(),
                ];
                break;
            }
        }
    }
}
