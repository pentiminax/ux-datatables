<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Pentiminax\UX\DataTables\Attribute\Column;
use Pentiminax\UX\DataTables\Attribute\DataTableColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Column::class)]
final class ColumnDeprecationTest extends TestCase
{
    #[Test]
    public function it_triggers_a_deprecation_when_instantiated(): void
    {
        $deprecations = [];

        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            new Column(title: 'Name');
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString(Column::class, $deprecations[0]);
        $this->assertStringContainsString(DataTableColumn::class, $deprecations[0]);
    }

    #[Test]
    public function it_translates_its_named_parameters_into_options(): void
    {
        $attribute = @new Column(title: 'Name', orderable: false, width: '80px');

        $this->assertSame('Name', $attribute->options['title']);
        $this->assertFalse($attribute->options['orderable']);
        $this->assertSame('80px', $attribute->options['width']);
    }

    #[Test]
    public function it_leaves_unset_parameters_out_of_the_options(): void
    {
        $attribute = @new Column();

        $this->assertArrayNotHasKey('title', $attribute->options);
        $this->assertArrayNotHasKey('width', $attribute->options);
        $this->assertArrayNotHasKey('renderAsBadges', $attribute->options);
    }

    #[Test]
    public function it_stays_readable_through_its_original_properties(): void
    {
        $attribute = @new Column(name: 'full_name', title: 'Name', position: 3);

        $this->assertSame('full_name', $attribute->name);
        $this->assertSame('Name', $attribute->title);
        $this->assertSame(3, $attribute->position);
        $this->assertInstanceOf(DataTableColumn::class, $attribute);
    }
}
