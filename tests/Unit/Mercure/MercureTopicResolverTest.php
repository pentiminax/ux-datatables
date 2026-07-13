<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualMercure;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithMercureTopicsAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(MercureTopicResolver::class)]
final class MercureTopicResolverTest extends TestCase
{
    #[Test]
    public function it_prefers_the_manual_data_table_configuration_over_the_entity_fallback(): void
    {
        $dataTable = new TestDataTableWithManualMercure();

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(TestDataTableWithManualMercure::class)->willReturn(true);
        $dataTables->method('get')->with(TestDataTableWithManualMercure::class)->willReturn($dataTable);

        $entityFallback = $this->createMock(MercureConfigResolverInterface::class);
        $entityFallback->expects($this->never())->method('resolveMercureConfig');

        $resolver = new MercureTopicResolver($dataTables, $entityFallback);

        $this->assertSame(
            ['manual/topic'],
            $resolver->resolve(\stdClass::class, TestDataTableWithManualMercure::class)
        );
    }

    #[Test]
    public function it_falls_back_to_the_explicit_as_data_table_attribute_topics(): void
    {
        $dataTable = new TestDataTableWithMercureTopicsAttribute();

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(TestDataTableWithMercureTopicsAttribute::class)->willReturn(true);
        $dataTables->method('get')->with(TestDataTableWithMercureTopicsAttribute::class)->willReturn($dataTable);

        $entityFallback = $this->createMock(MercureConfigResolverInterface::class);
        $entityFallback->expects($this->never())->method('resolveMercureConfig');

        $resolver = new MercureTopicResolver($dataTables, $entityFallback);

        $this->assertSame(
            ['https://example.com/books'],
            $resolver->resolve(\stdClass::class, TestDataTableWithMercureTopicsAttribute::class)
        );
    }

    #[Test]
    public function it_falls_back_to_the_entity_based_resolver_when_the_data_table_class_is_unknown(): void
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->willReturn(false);

        $entityFallback = $this->createMock(MercureConfigResolverInterface::class);
        $entityFallback->expects($this->once())
            ->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn(new MercureConfig(topics: ['/entity/{id}'], hubUrl: 'https://hub.example/.well-known/mercure'));

        $resolver = new MercureTopicResolver($dataTables, $entityFallback);

        $this->assertSame(['/entity/{id}'], $resolver->resolve(\stdClass::class));
    }

    #[Test]
    public function it_returns_an_empty_array_when_nothing_can_be_resolved(): void
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->willReturn(false);

        $resolver = new MercureTopicResolver($dataTables);

        $this->assertSame([], $resolver->resolve(\stdClass::class));
    }
}
