<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Runtime\DataSourceCapability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataSourceCapability::class)]
#[CoversClass(DataSourceFeature::class)]
final class DataSourceCapabilityTest extends TestCase
{
    #[Test]
    public function it_resolves_the_doctrine_provider_for_both_identifier_capabilities(): void
    {
        $provider = new DoctrineDataProvider($this->createStub(EntityManagerInterface::class), \stdClass::class, $this->createStub(RowMapperInterface::class));

        $this->assertSame($provider, DataSourceCapability::rowScopeChecker($provider));
        $this->assertSame($provider, DataSourceCapability::identifierCollector($provider));

        foreach (DataSourceFeature::cases() as $feature) {
            $this->assertTrue($provider->supports($feature), $feature->value);
        }
    }

    #[Test]
    public function it_answers_nothing_for_a_missing_provider_or_one_without_the_identifier_interface(): void
    {
        $provider = new CapabilityProvider(static fn (): bool => true);

        $this->assertNull(DataSourceCapability::rowScopeChecker(null));
        $this->assertNull(DataSourceCapability::identifierCollector(null));
        $this->assertNull(DataSourceCapability::rowScopeChecker($provider));
        $this->assertNull(DataSourceCapability::identifierCollector($provider));
    }

    #[Test]
    public function it_uses_the_identifier_interface_of_a_provider_that_declares_the_feature(): void
    {
        $provider = new CapabilityIdentifierProvider(static fn (): bool => true);

        $this->assertSame($provider, DataSourceCapability::rowScopeChecker($provider));
        $this->assertSame($provider, DataSourceCapability::identifierCollector($provider));
    }

    #[Test]
    public function it_refuses_a_feature_the_provider_does_not_declare(): void
    {
        $provider = new CapabilityIdentifierProvider(static fn (DataSourceFeature $feature): bool => DataSourceFeature::RowScope === $feature);

        $this->assertSame($provider, DataSourceCapability::rowScopeChecker($provider));
        $this->assertNull(DataSourceCapability::identifierCollector($provider));
        $this->assertFalse(DataSourceCapability::supports($provider, DataSourceFeature::SearchBuilder));
    }

    #[Test]
    public function it_treats_an_unhandled_match_error_as_unsupported(): void
    {
        $provider = new CapabilityIdentifierProvider(static fn (DataSourceFeature $feature): bool => match ($feature) {
            DataSourceFeature::SearchBuilder => true,
        });

        $this->assertTrue(DataSourceCapability::supports($provider, DataSourceFeature::SearchBuilder));
        $this->assertFalse(DataSourceCapability::supports($provider, DataSourceFeature::ColumnControl));
        $this->assertNull(DataSourceCapability::rowScopeChecker($provider));
        $this->assertNull(DataSourceCapability::identifierCollector($provider));
    }

    #[Test]
    public function it_labels_the_row_scope_features(): void
    {
        $this->assertNotSame('', DataSourceFeature::RowScope->label());
        $this->assertNotSame('', DataSourceFeature::IdentifierCollection->label());
    }
}

/**
 * @internal
 */
class CapabilityProvider implements DataProviderInterface
{
    public function __construct(private readonly \Closure $supports)
    {
    }

    public function supports(DataSourceFeature $feature): bool
    {
        return ($this->supports)($feature);
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(0, 0, []);
    }

    public function iterateRows(DataTableRequest $request): iterable
    {
        return [];
    }
}

/**
 * @internal
 */
final class CapabilityIdentifierProvider extends CapabilityProvider implements IdentifierDataProviderInterface
{
    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array
    {
        return [];
    }

    public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array
    {
        return $ids;
    }
}
