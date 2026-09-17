<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchListOptionsProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchListOptionsNormalizer;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SearchListOptionsResolver
{
    public function __construct(
        private ?TranslatorInterface $translator = null,
        private ?ColumnResolver $columnResolver = null,
    ) {
    }

    public function prepare(DataTable $table): void
    {
        $seen = [];

        foreach ($this->configurations($table) as $configuration) {
            foreach ($this->searchLists($configuration) as $searchList) {
                $id = spl_object_id($searchList);
                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $searchList->prepareStaticOptions($this->translator);
            }
        }
    }

    /**
     * @param list<ColumnInterface> $columns configured columns; permission filtering happens here
     */
    public function resolve(DataTable $table, array $columns, DataTableRequest $request): ?\stdClass
    {
        $tableGroups = $this->groups($this->tableConfiguration($table));
        $response    = new \stdClass();
        $configured  = false;

        $hasProvider = false;
        foreach ($columns as $column) {
            if ($column->isSearchable() && '' !== $column->getName() && [] !== $this->providers($column, $tableGroups)) {
                $hasProvider = true;

                break;
            }
        }

        if (!$hasProvider) {
            return null;
        }

        $columns = ($this->columnResolver ?? new ColumnResolver())->filterStaticPermissions(
            $columns,
            $table->getDataTableClass(),
        );

        foreach ($columns as $column) {
            if (!$column->isSearchable() || '' === $column->getName()) {
                continue;
            }

            $providers = $this->providers($column, $tableGroups);

            if ([] === $providers) {
                continue;
            }

            $configured = true;

            if (1 !== \count($providers)) {
                throw new \LogicException(\sprintf('Column "%s" has more than one effective search list Ajax options provider.', $column->getName()));
            }

            $provider = reset($providers);
            $options  = $provider instanceof SearchListOptionsProviderInterface
                ? $provider->provide($request, $column)
                : $provider($request, $column);

            if (null === $options) {
                continue;
            }

            $response->{$column->getName()} = SearchListOptionsNormalizer::normalizeIterable(
                $options,
                $this->translator,
            );
        }

        return $configured ? $response : null;
    }

    /**
     * @param array<string, array<mixed>> $tableGroups
     *
     * @return array<int, SearchListOptionsProviderInterface|\Closure>
     */
    private function providers(ColumnInterface $column, array $tableGroups): array
    {
        $serialized  = $column->jsonSerialize();
        $hasOverride = \array_key_exists('columnControl', $serialized);
        $override    = $hasOverride && \is_array($serialized['columnControl'])
            ? $serialized['columnControl']
            : null;

        if ($hasOverride && [] === $override) {
            return [];
        }

        $columnGroups = null === $override ? [] : $this->groups($override);
        $providers    = [];

        foreach (array_unique([...array_keys($tableGroups), ...array_keys($columnGroups)]) as $target) {
            $content = \array_key_exists($target, $columnGroups)
                ? $columnGroups[$target]
                : ($tableGroups[$target] ?? []);

            foreach ($this->searchLists($content) as $searchList) {
                $provider = $searchList->getAjaxOptionsProvider();
                if (null !== $provider) {
                    $providers[spl_object_id($provider)] = $provider;
                }
            }
        }

        return $providers;
    }

    /**
     * @return iterable<array<mixed>>
     */
    private function configurations(DataTable $table): iterable
    {
        $tableConfiguration = $this->tableConfiguration($table);
        if (null !== $tableConfiguration) {
            yield $tableConfiguration;
        }

        foreach ($table->getColumns() as $column) {
            $configuration = $column->jsonSerialize()['columnControl'] ?? null;
            if (\is_array($configuration)) {
                yield $configuration;
            }
        }
    }

    /**
     * @return array<mixed>|null
     */
    private function tableConfiguration(DataTable $table): ?array
    {
        $extension = $table->getExtensionsCollection()->all()['columnControl'] ?? null;

        return null === $extension ? null : $extension->jsonSerialize();
    }

    /**
     * @param array<mixed>|null $configuration
     *
     * @return array<string, array<mixed>>
     */
    private function groups(?array $configuration): array
    {
        if (null === $configuration || [] === $configuration) {
            return [];
        }

        if ($this->isTargetDefinition($configuration)) {
            return [$this->targetKey($configuration['target']) => $this->content($configuration)];
        }

        $groups         = [];
        $defaultContent = [];

        foreach ($configuration as $item) {
            if (\is_array($item) && $this->isTargetDefinition($item)) {
                $key = $this->targetKey($item['target']);
                $groups[$key] ??= $this->content($item);

                continue;
            }

            $defaultContent[] = $item;
        }

        if ([] !== $defaultContent) {
            $groups[$this->targetKey(0)] = $defaultContent;
        }

        return $groups;
    }

    /**
     * @param array<mixed> $definition
     */
    private function isTargetDefinition(array $definition): bool
    {
        return \array_key_exists('target', $definition);
    }

    /**
     * @param array<mixed> $definition
     *
     * @return array<mixed>
     */
    private function content(array $definition): array
    {
        return \is_array($definition['content'] ?? null) ? $definition['content'] : [];
    }

    private function targetKey(mixed $target): string
    {
        if (!\is_int($target) && !\is_string($target)) {
            throw new \InvalidArgumentException('Column control target must be an integer or string.');
        }

        return \is_int($target) ? 'header:'.$target : 'target:'.$target;
    }

    /**
     * @return list<SearchList>
     */
    private function searchLists(mixed $content): array
    {
        if ($content instanceof SearchList) {
            return [$content];
        }

        if (!\is_array($content)) {
            return [];
        }

        $searchLists = [];
        foreach ($content as $item) {
            array_push($searchLists, ...$this->searchLists($item));
        }

        return $searchLists;
    }
}
