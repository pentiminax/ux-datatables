<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Tests\Support\BuildsFilterQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    #[Test]
    public function it_translates_label_and_placeholder_keys(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnMap([
                ['user.name', [], null, null, 'Nom'],
                ['user.name.placeholder', [], null, null, 'Rechercher'],
            ]);

        $filter = TextFilter::new('name')
            ->label('user.name')
            ->placeholder('user.name.placeholder');

        $filter->translateLabels($translator);

        $this->assertSame([
            'name'        => 'name',
            'type'        => 'text',
            'label'       => 'Nom',
            'placeholder' => 'Rechercher',
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
    public function it_applies_a_condition(string $field, string $value, string $fieldType, array $expectedWhere, array $expectedParams): void
    {
        $this->assertFilterProduces(TextFilter::new($field), $value, $expectedWhere, $expectedParams, $fieldType);
    }

    /**
     * @return iterable<string, array{string, string, string, list<string>, array<string, mixed>}>
     */
    public static function provideConditions(): iterable
    {
        yield 'case insensitive like' => [
            'name',
            'John',
            'string',
            ["LOWER(e.name) LIKE :filter_name ESCAPE '!'"],
            ['filter_name' => '%john%'],
        ];

        yield 'value with like wildcards is escaped, not interpreted' => [
            'name',
            '50%_off',
            'string',
            ["LOWER(e.name) LIKE :filter_name ESCAPE '!'"],
            ['filter_name' => '%50!%!_off%'],
        ];

        yield 'uuid field' => ['id', '018f2c3e', 'uuid', [], []];

        yield 'blank value' => ['name', '   ', 'string', [], []];
    }
}
