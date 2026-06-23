<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Filters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Filters::class)]
final class FiltersTest extends TestCase
{
    #[Test]
    public function it_starts_empty(): void
    {
        $filters = new Filters();

        $this->assertTrue($filters->isEmpty());
        $this->assertSame(0, $filters->count());
        $this->assertSame([], $filters->getFilters());
    }

    #[Test]
    public function it_adds_and_serializes_filters_in_order(): void
    {
        $filters = (new Filters())
            ->add(TextFilter::new('name'))
            ->add(ChoiceFilter::new('status')->options(['Draft' => 'draft']));

        $this->assertFalse($filters->isEmpty());
        $this->assertSame(2, $filters->count());
        $this->assertInstanceOf(TextFilter::class, $filters->get('name'));

        $serialized = $filters->jsonSerialize();
        $this->assertCount(2, $serialized);
        $this->assertSame('name', $serialized[0]['name']);
        $this->assertSame('select', $serialized[1]['type']);
    }

    #[Test]
    public function it_removes_a_filter_by_name(): void
    {
        $filters = (new Filters())->add(TextFilter::new('name'));

        $filters->remove('name');

        $this->assertTrue($filters->isEmpty());
        $this->assertNull($filters->get('name'));
    }
}
