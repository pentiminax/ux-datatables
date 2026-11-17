<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Highlight\HighlightConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;

/**
 * Everything one render of a table computed, built by {@see RenderingPreparer} without writing back to
 * the table. {@see self::getView()} is the payload the Stimulus controller receives.
 *
 * @internal
 */
final readonly class PreparedDataTable
{
    /**
     * @param array<string, mixed>                                                            $attributes        HTML attributes of the table element
     * @param array<string, mixed>                                                            $options           DataTables.net options, with the permitted columns, the rows and the data URL
     * @param array<string, mixed>                                                            $extensions        serialized extensions, keyed by option name
     * @param list<ColumnInterface>                                                           $columns           the columns the current user may see
     * @param list<ColumnInterface>                                                           $configuredColumns
     * @param array<string, mixed>                                                            $bundleOptions     bundle keys the table set through the fluent API (`pollInterval`, `urlState`, ...)
     * @param array<string, mixed>|null                                                       $bulkActions       the bulk actions the current user may run, with their bar settings
     * @param array<string, string>                                                           $bulkActionLabels
     * @param array<string, string>|null                                                      $rowActionUrls
     * @param array{heading: string, description: ?string, icon: ?string, action: mixed}|null $emptyState
     * @param array<string, string>                                                           $emptyStateLabels
     * @param array<string, string>|null                                                      $actionLabels
     * @param array<string, string>|null                                                      $summaryLabels
     * @param array<string, mixed>|null                                                       $forwardedQuery
     * @param array<string, mixed>|null                                                       $filters
     * @param array<string, string>|null                                                      $filterLabels
     * @param array<string, mixed>|null                                                       $highlight
     */
    public function __construct(
        public string $id,
        public array $attributes,
        public ?string $dataTableClass,
        public DataSourceMode $dataSourceMode,
        public array $options,
        public array $extensions = [],
        public array $columns = [],
        public array $configuredColumns = [],
        public array $bundleOptions = [],
        public ?bool $apiPlatform = null,
        public bool $apiPlatformServerSide = false,
        public ?string $exportUrl = null,
        public ?MercureConfig $mercure = null,
        public ?array $highlight = null,
        public ?array $bulkActions = null,
        public ?string $bulkActionsUrl = null,
        public array $bulkActionLabels = [],
        public ?string $bulkRowIdField = null,
        public bool $selectionForBulkActions = false,
        public bool $declaresBulkActions = false,
        public ?array $rowActionUrls = null,
        public ?array $emptyState = null,
        public array $emptyStateLabels = [],
        public bool $recordUrl = false,
        public ?array $actionLabels = null,
        public ?array $summaryLabels = null,
        public ?array $forwardedQuery = null,
        public ?array $filters = null,
        public bool $showHeaderResetButton = false,
        public ?array $filterLabels = null,
        public ?string $actionToken = null,
        public ?string $editModalAdapter = null,
        public ?string $locale = null,
        public ?string $csrfToken = null,
    ) {
    }

    /**
     * The serialized payload: DataTables.net options, extensions and bundle keys in one map.
     *
     * @return array<string, mixed>
     *
     * @throws \LogicException when two of them share a key
     */
    public function getView(): array
    {
        $view = $this->options;

        $this->add($view, 'apiPlatform', $this->apiPlatform);
        if ($this->apiPlatformServerSide) {
            $this->add($view, 'apiPlatformServerSide', true);
        }

        foreach ($this->bundleOptions as $key => $value) {
            $this->add($view, $key, $value);
        }

        $this->add($view, 'exportUrl', $this->exportUrl);
        $this->add($view, 'mercure', $this->mercure?->jsonSerialize());

        if (null !== $this->highlight) {
            $this->add($view, 'highlight', $this->highlight);
            $view['rowId'] ??= HighlightConfig::ROW_ID_KEY;
        }

        if (null !== $this->bulkActions) {
            $this->add($view, 'bulkActions', [
                'actions'               => $this->bulkActions['actions'],
                'selectCurrentPageOnly' => $this->bulkActions['selectCurrentPageOnly'],
                // Client-side API Platform rows skip RowIdStage; the Stimulus adapter reads this
                // to stamp DT_RowId from the same field setIdField() / Doctrine remapping chose.
                'idField'  => $this->bulkRowIdField ?? $this->bulkActions['idField'],
                'url'      => $this->bulkActionsUrl,
                'labels'   => $this->bulkActionLabels,
                'position' => $this->bulkActions['position'],
            ]);
        }

        // A table declaring bulk actions keys its rows by id even when the user may run none of them.
        if ($this->declaresBulkActions) {
            $view['rowId'] ??= HighlightConfig::ROW_ID_KEY;
        }

        $this->add($view, 'rowActionUrls', $this->rowActionUrls);

        if (null !== $this->emptyState) {
            $this->add($view, 'emptyState', $this->emptyState + ['labels' => $this->emptyStateLabels]);
        }

        if ($this->recordUrl) {
            $this->add($view, 'recordUrl', true);
        }

        $this->add($view, 'actionLabels', $this->actionLabels);
        $this->add($view, 'summaryLabels', $this->summaryLabels);
        $this->add($view, 'forwardedQuery', $this->forwardedQuery);
        $this->add($view, 'filters', $this->filters);
        if ($this->showHeaderResetButton) {
            $this->add($view, 'showHeaderResetButton', true);
        }
        $this->add($view, 'filterLabels', $this->filterLabels);

        foreach ($this->extensions as $key => $value) {
            $this->add($view, $key, $value);
        }

        $this->add($view, 'dataTable', $this->actionToken, keepNull: true);
        $this->add($view, 'editModal', ['adapter' => $this->editModalAdapter]);
        $this->add($view, 'mutationsEnabled', null !== $this->csrfToken);
        $this->add($view, 'locale', $this->locale);
        $this->add($view, 'csrfToken', $this->csrfToken);

        return $view;
    }

    /**
     * A key of the serialized payload.
     */
    public function getOption(string $name): mixed
    {
        return $this->getView()[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $view
     */
    private function add(array &$view, string $key, mixed $value, bool $keepNull = false): void
    {
        if (null === $value && !$keepNull) {
            return;
        }

        if (\array_key_exists($key, $view)) {
            throw new \LogicException(\sprintf('The payload key "%s" of table "%s" is set twice. Rename the option or the extension that sets it.', $key, $this->dataTableClass ?? $this->id));
        }

        $view[$key] = $value;
    }
}
