<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\State\ProviderInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformItemResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApiPlatformItemResolver::class)]
final class ApiPlatformItemResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_a_row_by_iri_through_the_iri_converter(): void
    {
        $user = new ItemResolverUserFixture(7);

        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->expects($this->once())
            ->method('getResourceFromIri')
            ->with('/api/users/7', ['fetch_data' => true])
            ->willReturn($user);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->never())->method('provide');

        $resolver = $this->createResolver($iriConverter, $provider, new Get());

        $this->assertSame($user, $resolver->resolve(ItemResolverUserFixture::class, ['@id' => '/api/users/7', 'id' => 7]));
    }

    #[Test]
    public function it_resolves_a_row_by_id_through_the_get_operation_provider(): void
    {
        $user      = new ItemResolverUserFixture(7);
        $operation = new Get();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('provide')
            ->with($operation, ['id' => 7])
            ->willReturn($user);

        $resolver = $this->createResolver($this->neverCalledIriConverter(), $provider, $operation);

        $this->assertSame($user, $resolver->resolve(ItemResolverUserFixture::class, ['id' => 7]));
    }

    #[Test]
    public function it_returns_null_when_the_item_is_not_found(): void
    {
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willThrowException(new ItemNotFoundException());

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn(null);

        $resolver = $this->createResolver($iriConverter, $provider, new Get());

        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['@id' => '/api/users/7']));
        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['id' => 7]));
    }

    #[Test]
    public function it_returns_null_when_the_operation_security_denies_the_item(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn(new ItemResolverUserFixture(7));

        $accessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $accessChecker->expects($this->once())
            ->method('isGranted')
            ->with(ItemResolverUserFixture::class, 'is_granted("VIEW", object)')
            ->willReturn(false);

        $resolver = $this->createResolver(
            $this->neverCalledIriConverter(),
            $provider,
            new Get(security: 'is_granted("VIEW", object)'),
            $accessChecker,
        );

        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['id' => 7]));
    }

    #[Test]
    public function it_returns_null_when_security_is_configured_but_no_access_checker_is_available(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn(new ItemResolverUserFixture(7));

        $resolver = $this->createResolver(
            $this->neverCalledIriConverter(),
            $provider,
            new Get(security: 'is_granted("VIEW", object)'),
        );

        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['id' => 7]));
    }

    #[Test]
    public function it_returns_null_when_the_resolved_object_is_not_an_instance_of_the_resource_class(): void
    {
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willReturn(new \stdClass());

        $resolver = $this->createResolver($iriConverter, $this->createMock(ProviderInterface::class), new Get());

        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['@id' => '/api/invoices/7']));
    }

    #[Test]
    public function it_returns_null_when_the_resource_has_no_get_operation(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->never())->method('provide');

        $resolver = $this->createResolver($this->neverCalledIriConverter(), $provider, null);

        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['id' => 7]));
    }

    private function createResolver(
        IriConverterInterface $iriConverter,
        ProviderInterface $provider,
        ?Operation $getOperation,
        ?ResourceAccessCheckerInterface $accessChecker = null,
    ): ApiPlatformItemResolver {
        $operations = null === $getOperation ? [] : ['_api_users_get' => $getOperation];

        $resourceMetadataFactory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataFactory->method('create')
            ->with(ItemResolverUserFixture::class)
            ->willReturn(new ResourceMetadataCollection(ItemResolverUserFixture::class, [
                (new ApiResource())->withOperations(new Operations($operations)),
            ]));

        return new ApiPlatformItemResolver($iriConverter, $resourceMetadataFactory, $provider, $accessChecker);
    }

    private function neverCalledIriConverter(): IriConverterInterface
    {
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->expects($this->never())->method('getResourceFromIri');

        return $iriConverter;
    }
}

final class ItemResolverUserFixture
{
    public function __construct(private readonly int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
