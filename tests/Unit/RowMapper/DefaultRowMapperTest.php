<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DefaultRowMapper::class)]
final class DefaultRowMapperTest extends TestCase
{
    /**
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('mappedRowProvider')]
    public function it_maps_configured_columns_without_normalizing_their_values(mixed $row, array $columns, array $expected): void
    {
        $result = (new DefaultRowMapper($columns))->map($row);

        $this->assertSame($expected, $result);
    }

    public static function mappedRowProvider(): iterable
    {
        $clientObject = new \stdClass();
        $date         = new \DateTimeImmutable('2024-01-15');

        $stringableClient = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable Corp';
            }
        };

        yield 'scalar property mapped to its column key' => [
            new DefaultRowMapperFixture(name: 'Alice'),
            [TextColumn::new('name', 'Name')],
            ['name' => 'Alice'],
        ];

        yield 'nested property path resolved via set field' => [
            new DefaultRowMapperFixture(client: new DefaultRowMapperFixture(name: 'Acme Corp')),
            [TextColumn::new('client', 'Client')->setField('client.name')],
            ['client' => 'Acme Corp'],
        ];

        yield 'non stringable object returned as is' => [
            new DefaultRowMapperFixture(client: $clientObject),
            [TextColumn::new('client', 'Client')],
            ['client' => $clientObject],
        ];

        yield 'stringable is already converted by the property reader' => [
            new DefaultRowMapperFixture(client: $stringableClient),
            [TextColumn::new('client', 'Client')],
            ['client' => 'Stringable Corp'],
        ];

        yield 'datetime returned raw, formatting belongs to the normalization stage' => [
            new DefaultRowMapperFixture(createdAt: $date),
            [DateColumn::new('createdAt', 'Created At')->setFormat('d/m/Y')],
            ['createdAt' => $date],
        ];

        yield 'array row passed through unchanged' => [
            ['name' => 'Bob', 'extra' => 'data'],
            [TextColumn::new('name', 'Name')],
            ['name' => 'Bob', 'extra' => 'data'],
        ];
    }

    #[Test]
    public function it_does_not_pre_insert_actions_key_for_action_columns(): void
    {
        $actions      = (new Actions())->add(Action::detail());
        $actionColumn = ActionColumn::fromActions('actions', 'Actions', $actions);
        $mapper       = new DefaultRowMapper([
            TextColumn::new('name', 'Name'),
            $actionColumn,
        ]);

        $result = $mapper->map(new DefaultRowMapperFixture(name: 'Alice'));

        $this->assertSame(['name' => 'Alice'], $result);
        $this->assertArrayNotHasKey(ActionRowDataResolver::ROW_ACTIONS_KEY, $result);
    }
}

final readonly class DefaultRowMapperFixture
{
    public function __construct(
        public string $name = 'Alice',
        public mixed $client = null,
        public ?\DateTimeImmutable $createdAt = null,
    ) {
    }
}
