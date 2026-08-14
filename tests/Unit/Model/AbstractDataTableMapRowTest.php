<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableMapRowTest extends TestCase
{
    /**
     * @param array<int, object>   $columns
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('provideMappedRows')]
    public function it_maps_a_row(array $columns, mixed $row, array $expected, ?\Closure $detailActionUrl = null): void
    {
        $table = new MapRowTestTable($columns, $detailActionUrl, $this->urlGenerator());

        $this->assertSame($expected, $table->mapRowPublic($row));
    }

    /**
     * @param array<int, object>               $columns
     * @param array<int, mixed>                $data
     * @param array<int, array<string, mixed>> $expected
     */
    #[Test]
    #[DataProvider('provideInlineData')]
    public function it_maps_inline_data(array $columns, array $data, array $expected, ?\Closure $detailActionUrl = null): void
    {
        $table = new MapRowTestTable($columns, $detailActionUrl, $this->urlGenerator());

        $table->setData($data);

        $this->assertSame($expected, $table->getDataTable()->getOption('data'));
        $this->assertTrue($table->getDataTable()->areTemplateColumnsRendered());
    }

    /**
     * @return iterable<string, array{array<int, object>, mixed, array<string, mixed>, 3?: \Closure}>
     */
    public static function provideMappedRows(): iterable
    {
        yield 'array row is kept as is' => [
            [TextColumn::new('id')],
            ['id' => 10, 'title' => 'Heat'],
            ['id' => 10, 'title' => 'Heat'],
        ];

        yield 'json serializable row' => [
            [TextColumn::new('id')],
            new SerializableRow(),
            ['id' => 5],
        ];

        yield 'object row with a formatted date' => [
            [TextColumn::new('id'), TextColumn::new('title'), DateColumn::new('releasedAt')],
            new MovieRow(1, 'Heat', new \DateTimeImmutable('1995-12-15')),
            ['id' => 1, 'title' => 'Heat', 'releasedAt' => '1995-12-15'],
        ];

        yield 'nested object path' => [
            [
                TextColumn::new('title')->setData('meta.title'),
                DateColumn::new('releasedAt')->setData('meta.releasedAt'),
            ],
            new MovieWithMetaRow(new MetaRow('Alien', new \DateTimeImmutable('1979-05-25'))),
            ['meta.title' => 'Alien', 'meta.releasedAt' => '1979-05-25'],
        ];

        yield 'boolean property with an is-prefixed getter' => [
            [TextColumn::new('isEmailAuthEnabled')],
            new BooleanFlagRow(true),
            ['isEmailAuthEnabled' => true],
        ];

        yield 'object row with a resolved detail action url' => [
            [TextColumn::new('id'), TextColumn::new('title')],
            new MovieRow(7, 'Heat', new \DateTimeImmutable('1995-12-15')),
            [
                'id'                      => 7,
                'title'                   => 'Heat',
                '__ux_datatables_actions' => ['DETAIL' => ['url' => '/movies/7']],
            ],
            self::mixedDetailUrl(),
        ];

        yield 'array row with a resolved detail action url' => [
            [TextColumn::new('id'), TextColumn::new('title')],
            ['id' => 8, 'title' => 'Alien'],
            [
                'id'                      => 8,
                'title'                   => 'Alien',
                '__ux_datatables_actions' => ['DETAIL' => ['url' => '/movies/8']],
            ],
            self::mixedDetailUrl(),
        ];
    }

    /**
     * @return iterable<string, array{array<int, object>, array<int, mixed>, array<int, array<string, mixed>>, 3?: \Closure}>
     */
    public static function provideInlineData(): iterable
    {
        yield 'objects with a typed action closure' => [
            [TextColumn::new('id'), TextColumn::new('title')],
            [new MovieRow(11, 'Blade Runner', new \DateTimeImmutable('1982-06-25'))],
            [
                [
                    'id'                      => 11,
                    'title'                   => 'Blade Runner',
                    '__ux_datatables_actions' => ['DETAIL' => ['url' => '/movies/11']],
                ],
            ],
            static fn (MovieRow $row): string => '/movies/'.$row->getId(),
        ];

        yield 'arrays with an array action closure' => [
            [TextColumn::new('id'), TextColumn::new('title')],
            [['id' => 12, 'title' => 'Alien']],
            [
                [
                    'id'                      => 12,
                    'title'                   => 'Alien',
                    '__ux_datatables_actions' => ['DETAIL' => ['url' => '/movies/12']],
                ],
            ],
            static fn (array $row): string => '/movies/'.$row['id'],
        ];

        yield 'objects with a typed url route closure' => [
            [
                TextColumn::new('id'),
                UrlColumn::new('title')->linkToRoute(
                    'movie_show',
                    static fn (MovieRow $movie): array => ['id' => $movie->getId()]
                ),
            ],
            [new MovieRow(13, 'Arrival', new \DateTimeImmutable('2016-09-01'))],
            [
                [
                    'id'                   => 13,
                    'title'                => 'Arrival',
                    '__ux_datatables_urls' => ['title' => '/movie_show/13'],
                ],
            ],
        ];

        yield 'arrays with an array url closure' => [
            [
                TextColumn::new('id'),
                UrlColumn::new('title')->linkToUrl(static fn (array $row): string => '/movies/'.$row['id']),
            ],
            [['id' => 14, 'title' => 'Aliens']],
            [
                [
                    'id'                   => 14,
                    'title'                => 'Aliens',
                    '__ux_datatables_urls' => ['title' => '/movies/14'],
                ],
            ],
        ];
    }

    /**
     * Resolves the detail url from either an array or an object row.
     */
    private static function mixedDetailUrl(): \Closure
    {
        return static function (mixed $row): string {
            $id = \is_array($row) ? $row['id'] : $row->getId();

            return '/movies/'.$id;
        };
    }

    /**
     * Echoes the route and its parameters back so assertions cover both.
     */
    private function urlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static fn (string $route, array $parameters): string => \sprintf('/%s/%s', $route, implode('/', $parameters)));

        return $urlGenerator;
    }
}

final class MapRowTestTable extends AbstractDataTable
{
    /**
     * @param array<int, object> $columnsConfig
     */
    public function __construct(
        private readonly array $columnsConfig,
        private readonly ?\Closure $detailActionUrl = null,
        ?UrlGeneratorInterface $urlGenerator = null,
    ) {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            runtimeFactory: new DataTableRuntimeFactory(
                actionRowDataResolver: new ActionRowDataResolver(),
                urlColumnDataResolver: new UrlColumnDataResolver($urlGenerator),
            )
        ));
    }

    public function configureColumns(): iterable
    {
        return $this->columnsConfig;
    }

    public function configureActions(Actions $actions): Actions
    {
        if (null === $this->detailActionUrl) {
            return $actions;
        }

        return $actions->add(Action::detail()->linkToUrl($this->detailActionUrl));
    }

    public function mapRowPublic(mixed $row): array
    {
        return $this->createRowMapper()->map($row);
    }
}

final class SerializableRow implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['id' => 5];
    }
}

final class MovieRow
{
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly \DateTimeImmutable $releasedAt,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getReleasedAt(): \DateTimeImmutable
    {
        return $this->releasedAt;
    }
}

final class MetaRow
{
    public function __construct(
        private readonly string $title,
        private readonly \DateTimeImmutable $releasedAt,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getReleasedAt(): \DateTimeImmutable
    {
        return $this->releasedAt;
    }
}

final class MovieWithMetaRow
{
    public function __construct(private readonly MetaRow $meta)
    {
    }

    public function getMeta(): MetaRow
    {
        return $this->meta;
    }
}

final class BooleanFlagRow
{
    public function __construct(private readonly bool $isEmailAuthEnabled)
    {
    }

    public function isEmailAuthEnabled(): bool
    {
        return $this->isEmailAuthEnabled;
    }
}
