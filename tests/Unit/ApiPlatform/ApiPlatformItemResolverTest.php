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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

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

    /**
     * The IRI names the operation that serves it, which is not necessarily the first Get: only
     * that operation's security applies, so an admin-only sibling neither leaks nor denies.
     */
    #[Test]
    public function it_evaluates_the_security_of_the_operation_matched_by_the_iri(): void
    {
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willReturn(new ItemResolverUserFixture(7));

        $accessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $accessChecker->expects($this->once())
            ->method('isGranted')
            ->with(ItemResolverUserFixture::class, 'is_granted("ROLE_ADMIN")')
            ->willReturn(false);

        $resolver = $this->createResolver(
            $iriConverter,
            $this->createMock(ProviderInterface::class),
            [new Get(), new Get(security: 'is_granted("ROLE_ADMIN")')],
            $accessChecker,
            router: $this->routerMatching('/api/admin/users/7', '_api_users_get1'),
        );

        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['@id' => '/api/admin/users/7']));
    }

    #[Test]
    public function it_ignores_the_security_of_operations_the_iri_does_not_match(): void
    {
        $user = new ItemResolverUserFixture(7);

        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willReturn($user);

        $accessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $accessChecker->expects($this->never())->method('isGranted');

        $resolver = $this->createResolver(
            $iriConverter,
            $this->createMock(ProviderInterface::class),
            [new Get(), new Get(security: 'is_granted("ROLE_ADMIN")')],
            $accessChecker,
            router: $this->routerMatching('/api/users/7', '_api_users_get0'),
        );

        $this->assertSame($user, $resolver->resolve(ItemResolverUserFixture::class, ['@id' => '/api/users/7']));
    }

    #[Test]
    public function it_returns_null_when_the_iri_matches_no_route_or_another_resource(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturnCallback(static fn (string $iri): array => match ($iri) {
            '/api/invoices/7' => ['_api_resource_class' => \stdClass::class, '_api_operation_name' => '_api_invoices_get'],
            default           => throw new ResourceNotFoundException(),
        });

        $resolver = $this->createResolver(
            $this->neverCalledIriConverter(),
            $this->createMock(ProviderInterface::class),
            new Get(),
            router: $router,
        );

        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['@id' => '/nowhere/7']));
        $this->assertNull($resolver->resolve(ItemResolverUserFixture::class, ['@id' => '/api/invoices/7']));
    }

    #[Test]
    public function it_passes_the_current_request_to_the_security_expression(): void
    {
        $user    = new ItemResolverUserFixture(7);
        $request = Request::create('/datatables/ajax/templates');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn($user);

        $accessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $accessChecker->expects($this->once())
            ->method('isGranted')
            ->with(ItemResolverUserFixture::class, 'request.isSecure()', ['object' => $user, 'previous_object' => null, 'request' => $request])
            ->willReturn(true);

        $resolver = $this->createResolver(
            $this->neverCalledIriConverter(),
            $provider,
            new Get(security: 'request.isSecure()'),
            $accessChecker,
            $requestStack,
        );

        $this->assertSame($user, $resolver->resolve(ItemResolverUserFixture::class, ['id' => 7]));
    }

    /**
     * @param Operation|list<Operation>|null $getOperation
     */
    private function createResolver(
        IriConverterInterface $iriConverter,
        ProviderInterface $provider,
        Operation|array|null $getOperation,
        ?ResourceAccessCheckerInterface $accessChecker = null,
        ?RequestStack $requestStack = null,
        ?RouterInterface $router = null,
    ): ApiPlatformItemResolver {
        $getOperations = match (true) {
            null === $getOperation             => [],
            $getOperation instanceof Operation => [$getOperation],
            default                            => $getOperation,
        };

        $operations = [];
        foreach ($getOperations as $index => $operation) {
            $operations['_api_users_get'.$index] = $operation;
        }

        $resourceMetadataFactory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataFactory->method('create')
            ->with(ItemResolverUserFixture::class)
            ->willReturn(new ResourceMetadataCollection(ItemResolverUserFixture::class, [
                (new ApiResource())->withOperations(new Operations($operations)),
            ]));

        return new ApiPlatformItemResolver(
            $iriConverter,
            $resourceMetadataFactory,
            $provider,
            $router ?? $this->routerMatching(null, '_api_users_get0'),
            $accessChecker,
            $requestStack,
        );
    }

    /**
     * @param string|null $iri the only IRI the router matches, null for any IRI
     */
    private function routerMatching(?string $iri, string $operationName): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturnCallback(static function (string $matched) use ($iri, $operationName): array {
            if (null !== $iri && $matched !== $iri) {
                throw new ResourceNotFoundException();
            }

            return ['_api_resource_class' => ItemResolverUserFixture::class, '_api_operation_name' => $operationName, 'id' => '7'];
        });

        return $router;
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
