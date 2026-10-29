<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\NumberRangeFilter;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountProduct;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use Pentiminax\UX\DataTables\Tests\Support\BuildsFilterQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(NumberRangeFilter::class)]
final class NumberRangeFilterTest extends TestCase
{
    use BuildsEntityManager;
    use BuildsFilterQueryBuilder;

    #[Test]
    public function it_serializes_its_definition(): void
    {
        $filter = NumberRangeFilter::new('price')->label('Price')->minPlaceholder('From')->maxPlaceholder('To');

        $this->assertSame([
            'name'           => 'price',
            'type'           => 'numberRange',
            'label'          => 'Price',
            'minPlaceholder' => 'From',
            'maxPlaceholder' => 'To',
        ], $filter->jsonSerialize());
    }

    #[Test]
    public function it_omits_the_placeholders_it_was_not_given(): void
    {
        $this->assertSame(
            ['name' => 'price', 'type' => 'numberRange', 'label' => 'Price'],
            NumberRangeFilter::new('price')->jsonSerialize(),
        );
    }

    #[Test]
    public function it_translates_explicit_and_default_placeholders(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null): string => \sprintf('%s@%s', $id, $domain ?? 'default'),
        );

        $filter = NumberRangeFilter::new('price')->minPlaceholder('range.from');
        $filter->translateLabels($translator);

        $data = $filter->jsonSerialize();
        $this->assertSame('range.from@default', $data['minPlaceholder']);
        $this->assertSame('filter.number.max@DataTables', $data['maxPlaceholder']);
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string>         $expectedWhere
     * @param array<string, mixed> $expectedParams
     */
    #[Test]
    #[DataProvider('provideBounds')]
    public function it_applies_the_provided_bounds(array $value, array $expectedWhere, array $expectedParams): void
    {
        $this->assertFilterProduces(NumberRangeFilter::new('price'), $value, $expectedWhere, $expectedParams, 'integer');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, array<string, mixed>}>
     */
    public static function provideBounds(): iterable
    {
        yield 'both bounds' => [
            ['min' => '10', 'max' => '99.5'],
            ['e.price >= :filter_price_min', 'e.price <= :filter_price_max'],
            ['filter_price_min' => 10, 'filter_price_max' => 99.5],
        ];

        yield 'min only' => [['min' => '10', 'max' => ''], ['e.price >= :filter_price_min'], ['filter_price_min' => 10]];
        yield 'max only' => [['max' => '-3'], ['e.price <= :filter_price_max'], ['filter_price_max' => -3]];
        yield 'zero is a bound' => [['min' => '0'], ['e.price >= :filter_price_min'], ['filter_price_min' => 0]];
        yield 'padded decimal' => [['min' => ' .5 '], ['e.price >= :filter_price_min'], ['filter_price_min' => 0.5]];
        yield 'native numbers' => [['min' => 1, 'max' => 2.5], ['e.price >= :filter_price_min', 'e.price <= :filter_price_max'], ['filter_price_min' => 1, 'filter_price_max' => 2.5]];
        yield 'inverted bounds stay as given' => [['min' => '9', 'max' => '1'], ['e.price >= :filter_price_min', 'e.price <= :filter_price_max'], ['filter_price_min' => 9, 'filter_price_max' => 1]];
    }

    /**
     * @param array<string, mixed>|string|null $value
     */
    #[Test]
    #[DataProvider('provideIgnoredValues')]
    public function it_ignores_empty_and_malformed_values(mixed $value): void
    {
        $this->assertFilterProduces(NumberRangeFilter::new('price'), $value, [], []);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideIgnoredValues(): iterable
    {
        yield 'scalar' => ['10'];
        yield 'null' => [null];
        yield 'empty array' => [[]];
        yield 'empty bounds' => [['min' => '', 'max' => '  ']];
        yield 'text' => [['min' => 'abc', 'max' => '1; DROP TABLE x']];
        yield 'exponent' => [['min' => '1e3']];
        yield 'hexadecimal' => [['min' => '0x1A']];
        yield 'infinity' => [['min' => 'INF', 'max' => 'NAN']];
        yield 'overflowing digits' => [['min' => str_repeat('9', 400)]];
        yield 'nested array' => [['min' => ['1'], 'max' => ['2']]];
        yield 'boolean and null' => [['min' => true, 'max' => null]];
    }

    #[Test]
    public function it_keeps_the_valid_bound_when_the_other_is_malformed(): void
    {
        $this->assertFilterProduces(
            NumberRangeFilter::new('price'),
            ['min' => 'abc', 'max' => '5'],
            ['e.price <= :filter_price_max'],
            ['filter_price_max' => 5],
        );
    }

    #[Test]
    public function it_resolves_a_relation_path_through_the_field_option(): void
    {
        $this->assertFilterProduces(
            NumberRangeFilter::new('rating')->field('product.rating'),
            ['min' => '3'],
            ['e.product.rating >= :filter_product_rating_min'],
            ['filter_product_rating_min' => 3],
        );
    }

    #[Test]
    public function it_does_nothing_on_a_bare_association(): void
    {
        $qb = $this->createAssociationFieldQueryBuilder();

        NumberRangeFilter::new('customer')->apply($qb, ['min' => '1'], 'e');

        $this->assertSame([], $this->capturedWhere);
    }

    #[Test]
    public function it_lets_a_query_callback_replace_the_condition(): void
    {
        $called = false;
        $filter = NumberRangeFilter::new('price')->query(static function () use (&$called): void {
            $called = true;
        });

        $filter->apply($this->createScalarFieldQueryBuilder(), ['min' => '1'], 'e');

        $this->assertTrue($called);
        $this->assertSame([], $this->capturedWhere);
    }

    /**
     * @param list<int>             $expectedIds
     * @param array<string, string> $value
     */
    #[Test]
    #[DataProvider('provideDatabaseCases')]
    public function it_filters_integer_float_and_decimal_columns_on_sqlite(string $field, array $value, array $expectedIds): void
    {
        $em = $this->createEntityManager(CountProduct::class);
        $em->persist(new CountProduct(1, 0, 0.5, '9.99'));
        $em->persist(new CountProduct(2, 5, 2.5, '10.00'));
        $em->persist(new CountProduct(3, 10, 2.75, '10.01'));
        $em->flush();
        $em->clear();

        $qb = $em->createQueryBuilder()->select('e')->from(CountProduct::class, 'e')->orderBy('e.id');
        NumberRangeFilter::new($field)->apply($qb, $value, 'e');

        $ids = array_map(static fn (CountProduct $p): int => $p->id, $qb->getQuery()->getResult());

        $this->assertSame($expectedIds, $ids);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, list<int>}>
     */
    public static function provideDatabaseCases(): iterable
    {
        yield 'integer inclusive bounds' => ['stock', ['min' => '5', 'max' => '10'], [2, 3]];
        yield 'integer lower bound' => ['stock', ['min' => '6'], [3]];
        yield 'integer upper bound' => ['stock', ['max' => '4'], [1]];
        yield 'integer with a fractional bound' => ['stock', ['min' => '4.5', 'max' => '9.5'], [2]];
        yield 'float' => ['weight', ['min' => '0.6', 'max' => '2.5'], [2]];
        yield 'float inclusive' => ['weight', ['min' => '2.5', 'max' => '2.75'], [2, 3]];
        yield 'decimal' => ['price', ['min' => '10', 'max' => '10.00'], [2]];
        yield 'negative upper bound' => ['stock', ['max' => '-1'], []];
        yield 'malformed bound is ignored' => ['stock', ['min' => 'abc', 'max' => '5'], [1, 2]];
        yield 'both malformed returns everything' => ['stock', ['min' => '1 OR 1=1', 'max' => 'x'], [1, 2, 3]];
    }
}
