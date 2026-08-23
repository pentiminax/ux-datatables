<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Tests\Support\BuildsFilterQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TextFilter::class)]
final class TextFilterTest extends TestCase
{
    use BuildsFilterQueryBuilder;

    #[Test]
    public function it_serializes_its_definition(): void
    {
        $filter = TextFilter::new('name')->label('Nom')->placeholder('Search');

        $this->assertSame([
            'name'        => 'name',
            'type'        => 'text',
            'label'       => 'Nom',
            'placeholder' => 'Search',
        ], $filter->jsonSerialize());
    }

    /**
     * The uuid case matters because PostgreSQL rejects both LOWER(uuid) and
     * uuid LIKE, so a native uuid column must not produce a text condition.
     *
     * @param list<string>         $expectedWhere
     * @param array<string, mixed> $expectedParams
     */
    #[Test]
    #[DataProvider('provideConditions')]
    public function it_applies_a_condition(string $field, string $value, ?string $fieldType, array $expectedWhere, array $expectedParams): void
    {
        $this->assertFilterProduces(TextFilter::new($field), $value, $expectedWhere, $expectedParams, $fieldType);
    }

    /**
     * @return iterable<string, array{string, string, string|null, list<string>, array<string, mixed>}>
     */
    public static function provideConditions(): iterable
    {
        yield 'case insensitive like' => [
            'name',
            'John',
            null,
            ['UX_DATATABLES_SEARCH(e.name, :filter_name) = 1'],
            ['filter_name' => '%john%'],
        ];

        yield 'value with like wildcards is escaped, not interpreted' => [
            'name',
            '50%_off',
            null,
            ['UX_DATATABLES_SEARCH(e.name, :filter_name) = 1'],
            ['filter_name' => '%50!%!_off%'],
        ];

        yield 'uuid field' => ['id', '018f2c3e', 'uuid', [], []];

        yield 'blank value' => ['name', '   ', null, [], []];
    }
}
