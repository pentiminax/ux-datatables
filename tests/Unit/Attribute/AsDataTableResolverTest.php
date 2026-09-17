<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTableResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AsDataTableResolver::class)]
final class AsDataTableResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_the_attribute_of_a_data_table(): void
    {
        $attribute = (new AsDataTableResolver())->resolve(AnnotatedDataTable::class);

        $this->assertInstanceOf(AsDataTable::class, $attribute);
        $this->assertSame('product:list', $attribute->serializationGroups[0]);
    }

    #[Test]
    public function it_returns_null_without_the_attribute(): void
    {
        $this->assertNull((new AsDataTableResolver())->resolve(PlainDataTable::class));
    }

    #[Test]
    public function it_does_not_inherit_the_attribute_from_a_parent_class(): void
    {
        $resolver = new AsDataTableResolver();

        $this->assertNotNull($resolver->resolve(AnnotatedDataTable::class));
        $this->assertNull($resolver->resolve(AnnotatedDataTableChild::class));
    }

    #[Test]
    public function it_returns_null_for_an_unknown_class(): void
    {
        $this->assertNull((new AsDataTableResolver())->resolve('App\\DataTables\\DoesNotExist'));
    }

    #[Test]
    public function it_caches_the_resolved_attribute(): void
    {
        $resolver = new AsDataTableResolver();

        $this->assertSame($resolver->resolve(AnnotatedDataTable::class), $resolver->resolve(AnnotatedDataTable::class));
        $this->assertSame($resolver->resolve(PlainDataTable::class), $resolver->resolve(PlainDataTable::class));
    }
}

#[AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'])]
class AnnotatedDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}

final class AnnotatedDataTableChild extends AnnotatedDataTable
{
}

final class PlainDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}
