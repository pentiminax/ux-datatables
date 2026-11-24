<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SearchBuilderExtension;

/**
 * What a table's definition needs its data source to apply. It is fixed by the definition, never by a request.
 *
 * @internal
 */
final readonly class TableRequirements
{
    /**
     * @param list<DataSourceFeature> $features          the data source features the table requires
     * @param bool                    $servesRowActions  whether a column renders an action served by the bundle's Ajax endpoints
     * @param bool                    $rendersRowActions whether a column renders action URLs, served by the bundle or not
     */
    public function __construct(
        public array $features,
        public bool $servesRowActions,
        public bool $rendersRowActions,
    ) {
    }

    public static function of(DataTable $table): self
    {
        $features = [];

        foreach ([DataSourceFeature::SearchBuilder, DataSourceFeature::ColumnControl, DataSourceFeature::ConfiguredFilters, DataSourceFeature::Summaries] as $feature) {
            if (self::configures($table, $feature)) {
                $features[] = $feature;
            }
        }

        $servesRowActions = self::servesRowActions($table);

        if (($servesRowActions || $table->hasBulkActions()) && !$table->allowsUnscopedMutations()) {
            $features[] = DataSourceFeature::RowScope;
        }

        return new self(
            features: $features,
            servesRowActions: $servesRowActions,
            rendersRowActions: self::rendersRowActions($table),
        );
    }

    public function has(DataSourceFeature $feature): bool
    {
        return \in_array($feature, $this->features, true);
    }

    private static function configures(DataTable $table, DataSourceFeature $feature): bool
    {
        $extensions = $table->getExtensionsCollection()->all();

        return match ($feature) {
            DataSourceFeature::SearchBuilder     => ($extensions['searchBuilder'] ?? null) instanceof SearchBuilderExtension,
            DataSourceFeature::ColumnControl     => ($extensions['columnControl'] ?? null) instanceof ColumnControlExtension && self::offersColumnControlSearch($extensions['columnControl'], $table->getColumns()),
            DataSourceFeature::ConfiguredFilters => [] !== ($table->getFilters()?->getFilters() ?? []),
            DataSourceFeature::Summaries         => [] !== ColumnResolver::filterSummarized($table->getColumns()),
            DataSourceFeature::RowScope,
            DataSourceFeature::IdentifierCollection => false,
        };
    }

    private static function servesRowActions(DataTable $table): bool
    {
        foreach ($table->getColumns() as $column) {
            if ($column instanceof BooleanColumn && $column->isRenderedAsSwitch()) {
                return true;
            }

            if (!$column instanceof ActionsProvidingColumnInterface) {
                continue;
            }

            foreach ($column->getActions()?->getActions() ?? [] as $action) {
                if ($action->hasTarget()) {
                    continue;
                }

                if (ActionType::Delete === $action->getType() || ActionType::Edit === $action->getType() || ($action->isCollapsible() && ActionType::Detail === $action->getType())) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function rendersRowActions(DataTable $table): bool
    {
        foreach ($table->getColumns() as $column) {
            if ($column instanceof ActionsProvidingColumnInterface || ($column instanceof BooleanColumn && $column->isRenderedAsSwitch())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $columns
     */
    private static function offersColumnControlSearch(ColumnControlExtension $extension, array $columns): bool
    {
        if (self::containsSearchControl($extension->jsonSerialize())) {
            return true;
        }

        foreach ($columns as $column) {
            if (self::containsSearchControl($column->getColumnControl())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Content descriptors nest (a group or a dropdown holds a `content` list) and come as names,
     * `extend` arrays, or objects such as SearchList that serialize to one. Every ColumnControl search
     * type starts with "search"; `searchClear` only clears one, and other keys (icon, text) are
     * labels.
     */
    private static function containsSearchControl(mixed $content): bool
    {
        if ($content instanceof \JsonSerializable) {
            return self::containsSearchControl($content->jsonSerialize());
        }

        if (\is_string($content)) {
            return str_starts_with($content, 'search') && 'searchClear' !== $content;
        }

        if (!\is_array($content)) {
            return false;
        }

        if (!array_is_list($content)) {
            return self::containsSearchControl($content['extend'] ?? null)
                || self::containsSearchControl($content['content'] ?? null);
        }

        foreach ($content as $item) {
            if (self::containsSearchControl($item)) {
                return true;
            }
        }

        return false;
    }
}
