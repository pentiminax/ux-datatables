<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Pentiminax\UX\DataTables\Attribute\MemberAttributeReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MemberAttributeReader::class)]
final class MemberAttributeReaderTest extends TestCase
{
    private MemberAttributeReader $reader;

    protected function setUp(): void
    {
        $this->reader = new MemberAttributeReader();
    }

    #[Test]
    public function it_returns_nothing_when_no_member_carries_the_attribute(): void
    {
        $this->assertSame([], $this->reader->readProperties(UnannotatedFixture::class, Marker::class));
    }

    #[Test]
    public function it_reads_private_properties_declared_by_a_parent(): void
    {
        $targets = $this->reader->readProperties(ChildFixture::class, Marker::class);

        $this->assertSame(['own', 'parentPrivate', 'parentProtected'], array_map(static fn ($target) => $target->name, $targets));
    }

    /**
     * A parent mixing private and non-private members must keep its own source order, because
     * columns that tie on `position` are ordered by declaration.
     */
    #[Test]
    public function it_keeps_a_parent_own_declaration_order(): void
    {
        $targets = $this->reader->readProperties(MixedVisibilityChildFixture::class, Marker::class);

        $this->assertSame(['child', 'parentFirst', 'parentSecond', 'parentThird'], array_map(static fn ($target) => $target->name, $targets));
    }

    #[Test]
    public function it_keeps_the_child_declaration_when_a_name_is_redeclared(): void
    {
        $targets = $this->reader->readProperties(RedeclaringChildFixture::class, Marker::class);

        $this->assertCount(1, $targets);
        $this->assertSame('child', $targets[0]->attribute->label);
    }

    #[Test]
    public function it_numbers_targets_in_declaration_order(): void
    {
        $targets = $this->reader->readProperties(ChildFixture::class, Marker::class);

        $this->assertSame([0, 1, 2], array_map(static fn ($target) => $target->declarationOrder, $targets));
    }

    #[Test]
    public function it_exposes_the_declared_type_and_leaves_it_null_when_absent(): void
    {
        $targets = $this->reader->readProperties(TypedFixture::class, Marker::class);

        $this->assertSame('string', $targets[0]->type?->getName());
        $this->assertNull($targets[1]->type);
    }

    #[Test]
    public function it_matches_subclasses_of_the_requested_attribute(): void
    {
        $targets = $this->reader->readProperties(SubclassedAttributeFixture::class, Marker::class);

        $this->assertCount(1, $targets);
        $this->assertInstanceOf(ExtendedMarker::class, $targets[0]->attribute);
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Marker
{
    public function __construct(public readonly string $label = '')
    {
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ExtendedMarker extends Marker
{
}

final class UnannotatedFixture
{
    public string $plain = '';
}

abstract class ParentFixture
{
    #[Marker]
    private string $parentPrivate = '';

    #[Marker]
    protected string $parentProtected = '';
}

final class ChildFixture extends ParentFixture
{
    #[Marker]
    public string $own = '';
}

abstract class RedeclaringParentFixture
{
    #[Marker(label: 'parent')]
    protected string $shared = '';
}

final class RedeclaringChildFixture extends RedeclaringParentFixture
{
    #[Marker(label: 'child')]
    protected string $shared = '';
}

final class TypedFixture
{
    #[Marker]
    public string $typed = '';

    #[Marker]
    public $untyped;
}

final class SubclassedAttributeFixture
{
    #[ExtendedMarker]
    public string $value = '';
}

abstract class MixedVisibilityParentFixture
{
    #[Marker]
    private string $parentFirst = '';

    #[Marker]
    protected string $parentSecond = '';

    #[Marker]
    private string $parentThird = '';
}

final class MixedVisibilityChildFixture extends MixedVisibilityParentFixture
{
    #[Marker]
    public string $child = '';
}
