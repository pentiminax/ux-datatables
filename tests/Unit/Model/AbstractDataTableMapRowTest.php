<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\TestCase;

final class AbstractDataTableMapRowTest extends TestCase
{
    public function testMapsArrayRowAsIs(): void
    {
        $table = new MapRowTestTable([TextColumn::new('id')]);
        $row   = ['id' => 10, 'title' => 'Heat'];

        $this->assertSame($row, $table->mapRowPublic($row));
    }

    public function testMapsJsonSerializableRow(): void
    {
        $table = new MapRowTestTable([TextColumn::new('id')]);
        $row   = new SerializableRow();

        $this->assertSame(['id' => 5], $table->mapRowPublic($row));
    }

    public function testMapsObjectRowAndFormatsDate(): void
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

    public function testMapsNestedObjectPath(): void
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

    public function testMapsBooleanPropertyWithIsPrefixedGetter(): void
    {
        $table = new MapRowTestTable([
            TextColumn::new('isEmailAuthEnabled'),
        ]);

        $row = new BooleanFlagRow(true);

        $this->assertSame([
            'isEmailAuthEnabled' => true,
        ], $table->mapRowPublic($row));
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
