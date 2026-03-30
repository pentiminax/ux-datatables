<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableMapRowTest extends TestCase
{
    #[Test]
    public function it_maps_array_row_as_is(): void
    {
        $table = new MapRowTestTable([TextColumn::new('id')]);
        $row   = ['id' => 10, 'title' => 'Heat'];

        $this->assertSame($row, $table->mapRowPublic($row));
    }

    #[Test]
    public function it_maps_json_serializable_row(): void
    {
        $table = new MapRowTestTable([TextColumn::new('id')]);
        $row   = new SerializableRow();

        $this->assertSame(['id' => 5], $table->mapRowPublic($row));
    }

    #[Test]
    public function it_maps_object_row_and_formats_date(): void
    {
        $table = new MapRowTestTable([
            TextColumn::new('id'),
            TextColumn::new('title'),
            DateColumn::new('releasedAt'),
        ]);

        $row = new MovieRow(
            id: 1,
            title: 'Heat',
            releasedAt: new \DateTimeImmutable('1995-12-15')
        );

        $this->assertSame([
            'id'         => 1,
            'title'      => 'Heat',
            'releasedAt' => '1995-12-15',
        ], $table->mapRowPublic($row));
    }

    #[Test]
    public function it_maps_nested_object_path(): void
    {
        $table = new MapRowTestTable([
            TextColumn::new('title')->setData('meta.title'),
            DateColumn::new('releasedAt')->setData('meta.releasedAt'),
        ]);

        $row = new MovieWithMetaRow(
            new MetaRow('Alien', new \DateTimeImmutable('1979-05-25'))
        );

        $this->assertSame([
            'meta.title'      => 'Alien',
            'meta.releasedAt' => '1979-05-25',
        ], $table->mapRowPublic($row));
    }

    #[Test]
    public function it_maps_boolean_property_with_is_prefixed_getter(): void
    {
        $table = new MapRowTestTable([
            TextColumn::new('isEmailAuthEnabled'),
        ]);

        $row = new BooleanFlagRow(true);

        $this->assertSame([
            'isEmailAuthEnabled' => true,
        ], $table->mapRowPublic($row));
    }

    #[Test]
    public function it_adds_resolved_detail_action_url_for_object_rows(): void
    {
        $table = new DetailActionMapRowTestTable([
            TextColumn::new('id'),
            TextColumn::new('title'),
        ]);

        $row = new MovieRow(
            id: 7,
            title: 'Heat',
            releasedAt: new \DateTimeImmutable('1995-12-15')
        );

        $mappedRow = $table->renderRowPublic($row);

        $this->assertSame('/movies/7', $mappedRow['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_adds_resolved_detail_action_url_for_array_rows(): void
    {
        $table = new DetailActionMapRowTestTable([
            TextColumn::new('id'),
            TextColumn::new('title'),
        ]);

        $mappedRow = $table->renderRowPublic([
            'id'    => 8,
            'title' => 'Alien',
        ]);

        $this->assertSame('/movies/8', $mappedRow['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_sets_inline_data_from_objects_with_typed_action_closure(): void
    {
        $table = new TypedDetailActionSetDataTable([
            TextColumn::new('id'),
            TextColumn::new('title'),
        ]);

        $table->setData([
            new MovieRow(
                id: 11,
                title: 'Blade Runner',
                releasedAt: new \DateTimeImmutable('1982-06-25')
            ),
        ]);

        $data = $table->getDataTable()->getOption('data');

        $this->assertSame([
            [
                'id'                     => 11,
                'title'                  => 'Blade Runner',
                'actions'                => null,
                '__ux_datatables_actions' => [
                    'DETAIL' => ['url' => '/movies/11'],
                ],
            ],
        ], $data);
        $this->assertTrue($table->getDataTable()->areTemplateColumnsRendered());
    }

    #[Test]
    public function it_sets_inline_data_from_arrays_with_array_action_closure(): void
    {
        $table = new ArrayDetailActionSetDataTable([
            TextColumn::new('id'),
            TextColumn::new('title'),
        ]);

        $table->setData([
            ['id' => 12, 'title' => 'Alien'],
        ]);

        $this->assertSame([
            [
                'id'                     => 12,
                'title'                  => 'Alien',
                '__ux_datatables_actions' => [
                    'DETAIL' => ['url' => '/movies/12'],
                ],
            ],
        ], $table->getDataTable()->getOption('data'));
    }
}

final class MapRowTestTable extends AbstractDataTable
{
    public function __construct(private readonly array $columnsConfig)
    {
        parent::__construct();
    }

    public function configureColumns(): iterable
    {
        return $this->columnsConfig;
    }

    public function mapRowPublic(mixed $row): array
    {
        return $this->mapRow($row);
    }
}

final class DetailActionMapRowTestTable extends AbstractDataTable
{
    public function __construct(private readonly array $columnsConfig)
    {
        parent::__construct(
            runtimeFactory: new DataTableRuntimeFactory(
                actionRowDataResolver: new ActionRowDataResolver(),
            ),
        );
    }

    public function configureColumns(): iterable
    {
        return $this->columnsConfig;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail()->linkToUrl(static function (mixed $row): string {
                $id = \is_array($row) ? $row['id'] : $row->getId();

                return '/movies/'.$id;
            })
        );
    }

    public function renderRowPublic(mixed $row): array
    {
        return $this->createRowMapper()->map($row);
    }
}

final class TypedDetailActionSetDataTable extends AbstractDataTable
{
    public function __construct(private readonly array $columnsConfig)
    {
        parent::__construct(
            runtimeFactory: new DataTableRuntimeFactory(
                actionRowDataResolver: new ActionRowDataResolver(),
            ),
        );
    }

    public function configureColumns(): iterable
    {
        return $this->columnsConfig;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail()->linkToUrl(static fn (MovieRow $row): string => '/movies/'.$row->getId())
        );
    }
}

final class ArrayDetailActionSetDataTable extends AbstractDataTable
{
    public function __construct(private readonly array $columnsConfig)
    {
        parent::__construct(
            runtimeFactory: new DataTableRuntimeFactory(
                actionRowDataResolver: new ActionRowDataResolver(),
            ),
        );
    }

    public function configureColumns(): iterable
    {
        return $this->columnsConfig;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail()->linkToUrl(static fn (array $row): string => '/movies/'.$row['id'])
        );
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
