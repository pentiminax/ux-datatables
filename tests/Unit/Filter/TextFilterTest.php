<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\TextFilter;
use PHPUnit\Framework\Attributes\CoversClass;
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

    #[Test]
    public function it_applies_a_case_insensitive_like(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        TextFilter::new('name')->apply($qb, 'John', 'e');

        $this->assertSame(['LOWER(e.name) LIKE :filter_name'], $this->capturedWhere);
        $this->assertSame(['filter_name' => '%john%'], $this->capturedParams);
    }

    /**
     * PostgreSQL rejects both LOWER(uuid) and uuid LIKE, so a native uuid column
     * must not produce a text condition at all.
     */
    #[Test]
    public function it_is_a_no_op_for_a_uuid_field(): void
    {
        $qb = $this->createScalarFieldQueryBuilder('uuid');

        TextFilter::new('id')->apply($qb, '018f2c3e', 'e');

        $this->assertSame([], $this->capturedWhere);
        $this->assertSame([], $this->capturedParams);
    }

    #[Test]
    public function it_is_a_no_op_for_blank_values(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        TextFilter::new('name')->apply($qb, '   ', 'e');

        $this->assertSame([], $this->capturedWhere);
        $this->assertSame([], $this->capturedParams);
    }
}
