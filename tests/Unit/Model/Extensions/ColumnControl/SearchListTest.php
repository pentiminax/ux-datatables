<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions\ColumnControl;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchListOptionsProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchListOptionsNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchList::class)]
#[CoversClass(SearchListOptionsNormalizer::class)]
final class SearchListTest extends TestCase
{
    #[Test]
    public function it_serializes_only_explicit_configuration(): void
    {
        $searchList = SearchList::new()
            ->ajaxOnly(false)
            ->hidable(false)
            ->orthogonal('filter')
            ->search(false)
            ->select(false)
            ->title('Choose [title]');

        $this->assertSame([
            'extend'     => 'searchList',
            'ajaxOnly'   => false,
            'hidable'    => false,
            'orthogonal' => 'filter',
            'search'     => false,
            'select'     => false,
            'title'      => 'Choose [title]',
        ], $searchList->jsonSerialize());
    }

    /**
     * @param array<mixed>|class-string<\BackedEnum>    $options
     * @param list<array{label: string, value: scalar}> $expected
     */
    #[Test]
    #[DataProvider('provideStaticOptions')]
    public function it_normalizes_static_options(array|string $options, array $expected): void
    {
        $this->assertSame(
            ['extend' => 'searchList', 'options' => $expected],
            SearchList::new()->options($options)->jsonSerialize(),
        );
    }

    public static function provideStaticOptions(): iterable
    {
        yield 'simple values' => [
            ['draft', 'published'],
            [
                ['label' => 'draft', 'value' => 'draft'],
                ['label' => 'published', 'value' => 'published'],
            ],
        ];

        yield 'label to value map' => [
            ['Draft' => 'draft', 'Published' => 'published'],
            [
                ['label' => 'Draft', 'value' => 'draft'],
                ['label' => 'Published', 'value' => 'published'],
            ],
        ];

        yield 'DataTables option objects' => [
            [
                ['label' => 'Enabled', 'value' => 1],
                ['label' => 'Disabled', 'value' => 0],
            ],
            [
                ['label' => 'Enabled', 'value' => 1],
                ['label' => 'Disabled', 'value' => 0],
            ],
        ];

        yield 'backed enum class' => [
            SearchListStatus::class,
            [
                ['label' => 'Draft order', 'value' => 'draft'],
                ['label' => 'Published order', 'value' => 'published'],
            ],
        ];

        yield 'backed enum cases' => [
            SearchListPriority::cases(),
            [
                ['label' => 'Low priority', 'value' => 1],
                ['label' => 'High priority', 'value' => 2],
            ],
        ];
    }

    #[Test]
    public function it_rejects_invalid_static_options(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search list options must contain scalar values, BackedEnum cases, or label/value arrays.');

        SearchList::new()->options([new \stdClass()]);
    }

    #[Test]
    public function it_rejects_a_non_enum_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"stdClass" is not a BackedEnum class.');

        SearchList::new()->options(\stdClass::class);
    }

    #[Test]
    public function it_rejects_an_empty_orthogonal_data_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search list orthogonal data type must not be empty.');

        SearchList::new()->orthogonal('  ');
    }

    #[Test]
    public function it_rejects_static_options_after_an_ajax_provider(): void
    {
        $searchList = SearchList::new()->ajaxOptionsProvider(
            static fn (DataTableRequest $request, ColumnInterface $column): array => [],
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Search list static options and an Ajax options provider are mutually exclusive.');

        $searchList->options(['draft']);
    }

    #[Test]
    public function it_rejects_an_ajax_provider_after_static_options(): void
    {
        $searchList = SearchList::new()->options(['draft']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Search list static options and an Ajax options provider are mutually exclusive.');

        $searchList->ajaxOptionsProvider(new class implements SearchListOptionsProviderInterface {
            public function provide(DataTableRequest $request, ColumnInterface $column): ?iterable
            {
                return [];
            }
        });
    }
}

enum SearchListStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return $this->name.' order';
    }
}

enum SearchListPriority: int
{
    case Low  = 1;
    case High = 2;

    public function label(): string
    {
        return $this->name.' priority';
    }
}
