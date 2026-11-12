<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\FeatureAwareDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierCollectingDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\ScopedIdentifierProviderInterface;
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
    public function it_resolves_the_doctrine_provider_through_the_merged_interface(): void
    {
        $provider = new DoctrineDataProvider($this->createStub(EntityManagerInterface::class), \stdClass::class, $this->createStub(RowMapperInterface::class));

        $this->assertSame($provider, DataSourceCapability::rowScopeChecker($provider));
        $this->assertSame($provider, DataSourceCapability::identifierCollector($provider));

        foreach (DataSourceFeature::cases() as $feature) {
            $this->assertTrue($provider->supports($feature), $feature->value);
        }
    }

    #[Test]
    public function it_falls_back_to_the_deprecated_interfaces(): void
    {
        $provider = new LegacyIdentifierProvider();

        $this->assertSame($provider, DataSourceCapability::rowScopeChecker($provider));
        $this->assertSame($provider, DataSourceCapability::identifierCollector($provider));
    }

    #[Test]
    public function it_answers_nothing_for_a_provider_without_identifier_interfaces_or_a_missing_provider(): void
    {
        $provider = $this->createStub(DataProviderInterface::class);

        $this->assertNull(DataSourceCapability::rowScopeChecker($provider));
        $this->assertNull(DataSourceCapability::identifierCollector($provider));
        $this->assertNull(DataSourceCapability::rowScopeChecker(null));
        $this->assertNull(DataSourceCapability::identifierCollector(null));
    }

    #[Test]
    public function it_uses_the_merged_interface_of_a_provider_that_declares_every_feature(): void
    {
        $provider = new MergedIdentifierProvider(null);

        $this->assertSame($provider, DataSourceCapability::rowScopeChecker($provider));
        $this->assertSame($provider, DataSourceCapability::identifierCollector($provider));
    }

    #[Test]
    public function it_refuses_a_feature_the_provider_does_not_declare(): void
    {
        $provider = new MergedIdentifierProvider(static fn (DataSourceFeature $feature): bool => DataSourceFeature::IdentifierCollection === $feature);

        $this->assertNull(DataSourceCapability::rowScopeChecker($provider));
        $this->assertSame($provider, DataSourceCapability::identifierCollector($provider));
    }

    #[Test]
    public function it_treats_an_exhaustive_match_that_does_not_know_the_new_cases_as_unsupported(): void
    {
        $provider = new MergedIdentifierProvider(static fn (DataSourceFeature $feature): bool => match ($feature) {
            DataSourceFeature::SearchBuilder, DataSourceFeature::ColumnControl, DataSourceFeature::ConfiguredFilters, DataSourceFeature::Summaries => true,
        });

        $this->assertNull(DataSourceCapability::rowScopeChecker($provider));
        $this->assertNull(DataSourceCapability::identifierCollector($provider));
    }

    #[Test]
    public function it_labels_the_row_scope_features(): void
    {
        $this->assertSame('Row scope checks', DataSourceFeature::RowScope->label());
        $this->assertSame('Identifier collection', DataSourceFeature::IdentifierCollection->label());
    }

    #[Test]
    public function it_trusts_a_merged_interface_provider_that_is_not_feature_aware(): void
    {
        $provider = new UnawareIdentifierProvider();

        $this->assertSame($provider, DataSourceCapability::rowScopeChecker($provider));
        $this->assertSame($provider, DataSourceCapability::identifierCollector($provider));
    }

    #[Test]
    public function it_keeps_the_scope_closed_through_the_legacy_interfaces_when_supports_cannot_answer(): void
    {
        $throwing = new EverythingProvider(static fn (DataSourceFeature $feature): bool => match ($feature) {
            DataSourceFeature::SearchBuilder, DataSourceFeature::ColumnControl, DataSourceFeature::ConfiguredFilters, DataSourceFeature::Summaries => true,
        });
        $refusing = new EverythingProvider(static fn (): bool => false);

        foreach ([$throwing, $refusing] as $provider) {
            $this->assertSame($provider, DataSourceCapability::rowScopeChecker($provider));
            $this->assertSame($provider, DataSourceCapability::identifierCollector($provider));
        }
    }
}

/**
 * @internal
 */
final class LegacyIdentifierProvider implements DataProviderInterface, IdentifierCollectingDataProviderInterface, ScopedIdentifierProviderInterface
{
    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(0, 0, []);
    }

    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array
    {
        return [];
    }

    public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array
    {
        return $ids;
    }
}

/**
 * @internal
 */
final class MergedIdentifierProvider implements DataProviderInterface, IdentifierDataProviderInterface, FeatureAwareDataProviderInterface
{
    /**
     * @param (\Closure(DataSourceFeature): bool)|null $supports
     */
    public function __construct(private readonly ?\Closure $supports)
    {
    }

    public function supports(DataSourceFeature $feature): bool
    {
        return null === $this->supports || ($this->supports)($feature);
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(0, 0, []);
    }

    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array
    {
        return [];
    }

    public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array
    {
        return $ids;
    }
}

/**
 * @internal
 */
final class UnawareIdentifierProvider implements DataProviderInterface, IdentifierDataProviderInterface
{
    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(0, 0, []);
    }

    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array
    {
        return [];
    }

    public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array
    {
        return $ids;
    }
}

/**
 * @internal
 */
final class EverythingProvider implements DataProviderInterface, FeatureAwareDataProviderInterface, IdentifierCollectingDataProviderInterface, IdentifierDataProviderInterface, ScopedIdentifierProviderInterface
{
    /**
     * @param \Closure(DataSourceFeature): bool $supports
     */
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

    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array
    {
        return [];
    }

    public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array
    {
        return $ids;
    }
}
