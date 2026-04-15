<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\ExtensionInterface;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ResponsiveExtension;
use Pentiminax\UX\DataTables\Model\Options\SearchOption;
use Symfony\Component\String\Inflector\EnglishInflector;

class DataTable
{
    /** @var ColumnInterface[] */
    private array $columns = [];

    private DataTableOptions $options;

    private DataTableExtensions $extensions;

    private bool $templateColumnsRendered = false;

    private ?MercureConfig $mercureConfig = null;

    private ?string $editModalTemplate = null;

    private ?string $editModalAdapter = null;

    public function __construct(
        private readonly string $id,
        array $options = [],
        private array $attributes = [],
        array $extensions = [],
    ) {
        $this->options    = new DataTableOptions($options);
        $this->extensions = new DataTableExtensions($extensions);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOption(string $name): mixed
    {
        if ('columns' === $name) {
            return $this->getColumnDefinitions();
        }

        return $this->options->get($name);
    }

    public function getOptions(): array
    {
        $options            = $this->options->getOptions();
        $options['columns'] = $this->getColumnDefinitions();

        $this->addButtonsToLayout($options);

        if (null !== $this->mercureConfig) {
            $options['mercure'] = $this->mercureConfig->jsonSerialize();
        }

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

    public function editModalTemplate(string $template): static
    {
        $this->editModalTemplate = $template;

        return $this;
    }

    public function getEditModalTemplate(): ?string
    {
        return $this->editModalTemplate;
    }

    public function editModalAdapter(string $adapter): static
    {
        $this->editModalAdapter = $adapter;

        return $this;
    }

    public function getEditModalAdapter(): ?string
    {
        return $this->editModalAdapter;
    }

    /**
     * Feature control DataTables' smart column width handling.
     */
    public function autoWidth(bool $autoWidth = true): static
    {
        $this->options->set('autoWidth', $autoWidth);

        return $this;
    }

    /**
     * Initial order (sort) to apply to the table.
     *
     * @param array $order Array of order configurations. Each element can be:
     *                     - An array with [column_index, direction]
     *                     - An object with {idx: number, dir: 'asc'|'desc'}
     *                     - An object with {name: string, dir: 'asc'|'desc'}
     */
    public function order(array $order): static
    {
        $this->options->set('order', $order);

        return $this;
    }

    /**
     * Set a caption for the table.
     */
    public function caption(string $caption): static
    {
        $this->options->set('caption', $caption);

        return $this;
    }

    public function add(ColumnInterface $column): static
    {
        if (!$this->options->has('columns')) {
            $this->options->set('columns', []);
        }

        $this->columns[$column->getName()] = $column;

        return $this;
    }

    /**
     * @param ColumnInterface[] $columns
     */
    public function columns(array $columns): static
    {
        if (!$this->options->has('columns')) {
            $this->options->set('columns', []);
        }

        $this->columns = [];

        foreach ($columns as $column) {
            $this->columns[$column->getName()] = $column;
        }

        return $this;
    }

    public function getColumnByName(string $name): ?ColumnInterface
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * Feature control deferred rendering for additional speed of initialisation.
     */
    public function deferRender(bool $deferRender): static
    {
        $this->options->set('deferRender', $deferRender);

        return $this;
    }

    /**
     * Feature control table information display field.
     */
    public function info(bool $info): static
    {
        $this->options->set('info', $info);

        return $this;
    }

    /**
     * Feature control the end user's ability to change the paging display length of the table.
     */
    public function lengthChange(bool $lengthChange): static
    {
        $this->options->set('lengthChange', $lengthChange);

        return $this;
    }

    /**
     * Feature control ordering (sorting) abilities in DataTables.
     */
    public function ordering(bool $handler = true, bool $indicators = true): static
    {
        $this->options->set('ordering', [
            'handler'    => $handler,
            'indicators' => $indicators,
        ]);

        return $this;
    }

    public function withoutOrdering(): static
    {
        $this->options->set('ordering', false);

        return $this;
    }

    public function paging(
        bool $boundaryNumbers = true,
        int $buttons = 7,
        bool $firstLast = true,
        bool $numbers = true,
        bool $previousNext = true,
    ): static {
        $this->options->set('paging', [
            'boundaryNumbers' => $boundaryNumbers,
            'buttons'         => $buttons,
            'firstLast'       => $firstLast,
            'numbers'         => $numbers,
            'previousNext'    => $previousNext,
        ]);

        return $this;
    }

    /**
     * Enable or disable table pagination.
     */
    public function withoutPaging(): static
    {
        $this->options->set('paging', false);

        return $this;
    }

    /**
     * Feature control the processing indicator.
     */
    public function processing(bool $processing = true): static
    {
        $this->options->set('processing', $processing);

        return $this;
    }

    /**
     * Horizontal scrolling.
     */
    public function scrollX(bool $scrollX): static
    {
        $this->options->set('scrollX', $scrollX);

        return $this;
    }

    /**
     * Vertical scrolling.
     */
    public function scrollY(string $scrollY): static
    {
        $this->options->set('scrollY', $scrollY);

        return $this;
    }

    /**
     * Feature control search (filtering) abilities.
     */
    public function searching(bool $searching = true): static
    {
        $this->options->set('searching', $searching);

        return $this;
    }

    /**
     * Feature control table information display field.
     */
    public function serverSide(bool $serverSide = true): static
    {
        $this->options->set('serverSide', $serverSide);

        return $this;
    }

    /**
     * Enable API Platform client-side adapter mode in the Stimulus controller.
     */
    public function apiPlatform(bool $enabled = true): static
    {
        $this->options->set('apiPlatform', $enabled);

        return $this;
    }

    /**
     * Enable Mercure SSE real-time updates for this DataTable.
     * Requires symfony/mercure-bundle to be installed.
     *
     * @param string   $hubUrl          The Mercure hub URL (e.g. "/.well-known/mercure")
     * @param string[] $topics          Mercure topics. Defaults to ["/datatables/{pluralized-id}/{id}"]
     * @param bool     $withCredentials Whether to send credentials with the SSE request
     * @param int|null $debounceMs      Debounce delay in ms (default: 500)
     */
    public function mercure(
        string $hubUrl,
        array $topics = [],
        bool $withCredentials = false,
        ?int $debounceMs = null,
    ): static {
        $this->mercureConfig = new MercureConfig(
            hubUrl: $hubUrl,
            topics: [] !== $topics ? $topics : [$this->buildFallbackMercureTopic()],
            withCredentials: $withCredentials,
            debounceMs: $debounceMs,
        );

        return $this;
    }

    public function getMercureConfig(): ?MercureConfig
    {
        return $this->mercureConfig;
    }

    /**
     * Feature control table information display field.
     */
    public function stateSave(bool $stateSave = true): static
    {
        $this->options->set('stateSave', $stateSave);

        return $this;
    }

    /**
     * Define the starting point for data display when using DataTables with pagination.
     */
    public function displayStart(int $displayStart): static
    {
        $this->options->set('displayStart', $displayStart);

        return $this;
    }

    /**
     * Load data for the table's content from an Ajax source.
     */
    public function ajax(string $url, ?string $dataSrc = null, string $type = 'GET'): static
    {
        $ajax = [
            'type' => $type,
            'url'  => $url,
        ];

        if ($dataSrc) {
            $ajax['dataSrc'] = $dataSrc;
        }

        $this->options->set('ajax', $ajax);

        return $this;
    }

    /**
     * Data to use as the display data for the table.
     */
    public function data(array $data): static
    {
        $this->options->set('data', $data);

        return $this;
    }

    /**
     * Change the options in the page length select list.
     */
    public function lengthMenu(array $lengthMenu): static
    {
        $this->options->set('lengthMenu', $lengthMenu);

        return $this;
    }

    /**
     * Change the initial page length (number of rows per page).
     */
    public function pageLength(int $pageLength): static
    {
        $this->options->set('pageLength', $pageLength);

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

    /**
     * Configure the layout of DataTables UI features.
     *
     * Keys are DataTables position names (e.g. 'topStart', 'topEnd', 'bottomStart',
     * 'bottomEnd', 'top', 'bottom', 'top2Start', ...). Each value can be:
     *   - A Feature enum (e.g. Feature::SEARCH)
     *   - An array of Feature enums (e.g. [Feature::SEARCH, Feature::BUTTONS])
     *   - null to hide the position
     *   - A DataTables feature object (e.g. ['div' => ['html' => '<h2>Title</h2>']])
     *
     * @param array<string, Feature|Feature[]|array<string, mixed>|null> $layout
     */
    public function layout(array $layout): static
    {
        $this->options->set('layout', $layout);

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
        $this->options->set('search', $searchOption->jsonSerialize());

        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getColumnDefinitions(): array
    {
        return array_values(array_map(
            static fn (ColumnInterface $column): array => $column->jsonSerialize(),
            $this->columns,
        ));
    }

    public function markTemplateColumnsRendered(bool $rendered = true): static
    {
        $this->templateColumnsRendered = $rendered;

        return $this;
    }

    public function areTemplateColumnsRendered(): bool
    {
        return $this->templateColumnsRendered;
    }

    public function isServerSide(): bool
    {
        return $this->options->get('serverSide') ?? false;
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

        $buttonsConfig = ['buttons' => $buttonsExtension->jsonSerialize()];

        foreach ($layout as $position => $value) {
            if ($value === Feature::BUTTONS->value) {
                $options['layout'][$position] = $buttonsConfig;
            } elseif (\is_array($value)) {
                foreach ($value as $i => $item) {
                    if ($item === Feature::BUTTONS->value) {
                        $options['layout'][$position][$i] = $buttonsConfig;
                    }
                }
            }
        }
    }

    private function buildFallbackMercureTopic(): string
    {
        $slug = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $this->id));

        return '/datatables/'.$this->pluralize($slug).'/{id}';
    }

    private function pluralize(string $value): string
    {
        return (new EnglishInflector())->pluralize($value)[0];
    }
}
