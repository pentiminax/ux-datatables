<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchListOptionsProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Runtime\SearchListOptionsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(SearchListOptionsResolver::class)]
final class SearchListOptionsResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_a_table_provider_for_each_searchable_named_column(): void
    {
        $seen     = [];
        $provider = static function (DataTableRequest $request, ColumnInterface $column) use (&$seen): ?iterable {
            $seen[] = [$request->draw, $column->getName()];

            return 'office' === $column->getName() ? ['Paris', 'London'] : null;
        };
        $columns = [
            TextColumn::new('name'),
            TextColumn::new('office'),
            TextColumn::new('secret')->setSearchable(false),
        ];
        $table = $this->table($columns, SearchList::new()->ajaxOptionsProvider($provider));

        $resolved = (new SearchListOptionsResolver())->resolve($table, $columns, $this->request());

        $this->assertSame([[7, 'name'], [7, 'office']], $seen);
        $this->assertSame([
            'office' => [
                ['label' => 'Paris', 'value' => 'Paris'],
                ['label' => 'London', 'value' => 'London'],
            ],
        ], json_decode(json_encode($resolved, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function it_ignores_provider_keys_when_values_are_structured_options(): void
    {
        $column = TextColumn::new('office');
        $table  = $this->table([$column], SearchList::new()->ajaxOptionsProvider(
            static fn (): array => [
                42 => ['label' => 'Paris', 'value' => 42],
                99 => SearchListTranslatableStatus::Draft,
            ],
        ));

        $resolved = (new SearchListOptionsResolver())->resolve($table, [$column], $this->request());

        $this->assertSame(
            ['office' => [
                ['label' => 'Paris', 'value' => 42],
                ['label' => 'Draft', 'value' => 'draft'],
            ]],
            json_decode(json_encode($resolved, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_distinguishes_no_provider_null_options_and_an_explicit_empty_list(): void
    {
        $column = TextColumn::new('status');

        $withoutProvider = $this->table([$column], SearchList::new());
        $nullProvider    = $this->table([$column], SearchList::new()->ajaxOptionsProvider(
            static fn (): null => null,
        ));
        $emptyProvider = $this->table([$column], SearchList::new()->ajaxOptionsProvider(
            static fn (): array => [],
        ));

        $resolver = new SearchListOptionsResolver();

        $this->assertNull($resolver->resolve($withoutProvider, [$column], $this->request()));
        $this->assertSame('{}', json_encode($resolver->resolve($nullProvider, [$column], $this->request()), \JSON_THROW_ON_ERROR));
        $this->assertSame(
            ['status' => []],
            json_decode(json_encode($resolver->resolve($emptyProvider, [$column], $this->request()), \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_does_not_evaluate_column_permissions_when_no_ajax_provider_is_configured(): void
    {
        $column = TextColumn::new('status')->setPermission('ROLE_ADMIN');
        $table  = $this->table([$column], SearchList::new());

        $this->assertNull((new SearchListOptionsResolver())->resolve($table, [$column], $this->request()));
    }

    #[Test]
    public function a_column_target_override_takes_priority_over_the_table_provider(): void
    {
        $provider = static fn (): array => ['table'];
        $column   = TextColumn::new('status')->setColumnControl([
            ['target' => 1, 'content' => ['searchText']],
        ]);
        $table = $this->table([$column], SearchList::new()->ajaxOptionsProvider($provider));

        $this->assertNull((new SearchListOptionsResolver())->resolve($table, [$column], $this->request()));
    }

    #[Test]
    public function a_column_override_on_another_target_keeps_the_table_provider(): void
    {
        $provider = static fn (): array => ['table'];
        $column   = TextColumn::new('status')->setColumnControl([
            ['target' => 0, 'content' => ['orderAsc']],
        ]);
        $table = $this->table([$column], SearchList::new()->ajaxOptionsProvider($provider));

        $resolved = (new SearchListOptionsResolver())->resolve($table, [$column], $this->request());

        $this->assertSame(
            ['status' => [['label' => 'table', 'value' => 'table']]],
            json_decode(json_encode($resolved, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function disabling_column_control_prevents_provider_execution(): void
    {
        $calls    = 0;
        $provider = static function () use (&$calls): array {
            ++$calls;

            return ['hidden'];
        };
        $column = TextColumn::new('status')->disableColumnControl();
        $table  = $this->table([$column], SearchList::new()->ajaxOptionsProvider($provider));

        $this->assertNull((new SearchListOptionsResolver())->resolve($table, [$column], $this->request()));
        $this->assertSame(0, $calls);
    }

    #[Test]
    public function a_column_provider_replaces_the_table_provider_on_the_same_target(): void
    {
        $tableProvider  = static fn (): array => ['table'];
        $columnProvider = static fn (): array => ['column'];
        $column         = TextColumn::new('status')->setColumnControl([
            ['target' => 1, 'content' => [SearchList::new()->ajaxOptionsProvider($columnProvider)]],
        ]);
        $table = $this->table([$column], SearchList::new()->ajaxOptionsProvider($tableProvider));

        $resolved = (new SearchListOptionsResolver())->resolve($table, [$column], $this->request());

        $this->assertSame(
            ['status' => [['label' => 'column', 'value' => 'column']]],
            json_decode(json_encode($resolved, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_rejects_different_effective_providers_for_one_column(): void
    {
        $column = TextColumn::new('status');
        $table  = (new DataTable('orders'))
            ->columns([$column])
            ->addExtension(
                (new ColumnControlExtension([]))
                    ->add(0, [SearchList::new()->ajaxOptionsProvider(static fn (): array => ['first'])])
                    ->add(1, [SearchList::new()->ajaxOptionsProvider(static fn (): array => ['second'])]),
            );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Column "status" has more than one effective search list Ajax options provider.');

        (new SearchListOptionsResolver())->resolve($table, [$column], $this->request());
    }

    #[Test]
    public function it_allows_the_same_provider_on_multiple_targets(): void
    {
        $provider = static fn (): array => ['active'];
        $column   = TextColumn::new('status');
        $table    = (new DataTable('orders'))
            ->columns([$column])
            ->addExtension(
                (new ColumnControlExtension([]))
                    ->add(0, [SearchList::new()->ajaxOptionsProvider($provider)])
                    ->add(1, [SearchList::new()->ajaxOptionsProvider($provider)]),
            );

        $resolved = (new SearchListOptionsResolver())->resolve($table, [$column], $this->request());

        $this->assertSame(
            ['status' => [['label' => 'active', 'value' => 'active']]],
            json_decode(json_encode($resolved, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_rejects_an_invalid_target_before_running_a_provider(): void
    {
        $column = TextColumn::new('status')->setColumnControl([
            ['target' => null, 'content' => [SearchList::new()->ajaxOptionsProvider(static fn (): array => [])]],
        ]);
        $table = (new DataTable('orders'))->columns([$column]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column control target must be an integer or string.');

        (new SearchListOptionsResolver())->resolve($table, [$column], $this->request());
    }

    #[Test]
    public function it_translates_static_and_dynamic_enum_options_when_a_translator_is_available(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['status.draft', [], null, null, 'Brouillon'],
            ['status.published', [], null, null, 'Publié'],
        ]);
        $static = SearchList::new()->options(SearchListTranslatableStatus::class);
        $table  = $this->table([TextColumn::new('status')], $static);

        $this->assertSame(['Draft', 'Published'], array_column($static->jsonSerialize()['options'], 'label'));

        $resolver = new SearchListOptionsResolver($translator);
        $resolver->prepare($table);

        $this->assertSame([
            'extend'  => 'searchList',
            'options' => [
                ['label' => 'Brouillon', 'value' => 'draft'],
                ['label' => 'Publié', 'value' => 'published'],
            ],
        ], $static->jsonSerialize());

        $dynamic = SearchList::new()->ajaxOptionsProvider(
            new class implements SearchListOptionsProviderInterface {
                public function provide(DataTableRequest $request, ColumnInterface $column): ?iterable
                {
                    return SearchListTranslatableStatus::cases();
                }
            },
        );
        $dynamicTable = $this->table([TextColumn::new('status')], $dynamic);

        $this->assertSame(
            [
                'status' => [
                    ['label' => 'Brouillon', 'value' => 'draft'],
                    ['label' => 'Publié', 'value' => 'published'],
                ],
            ],
            json_decode(json_encode($resolver->resolve($dynamicTable, $dynamicTable->getColumns(), $this->request()), \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param list<ColumnInterface> $columns
     */
    private function table(array $columns, SearchList $searchList): DataTable
    {
        return (new DataTable('orders'))
            ->columns($columns)
            ->addExtension((new ColumnControlExtension([]))->add(1, [$searchList]));
    }

    private function request(): DataTableRequest
    {
        return new DataTableRequest(
            draw: 7,
            columns: new Columns([]),
            start: 0,
            length: 10,
        );
    }
}

enum SearchListTranslatableStatus: string implements TranslatableInterface
{
    case Draft     = 'draft';
    case Published = 'published';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('status.'.$this->value, [], null, $locale);
    }
}
