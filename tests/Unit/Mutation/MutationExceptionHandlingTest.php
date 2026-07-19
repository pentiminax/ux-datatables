<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Controller\AjaxDeleteController;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Dto\AjaxDeleteRequestDto;
use Pentiminax\UX\DataTables\Dto\AjaxEditRequestDto;
use Pentiminax\UX\DataTables\EventListener\MutationExceptionListener;
use Pentiminax\UX\DataTables\Exception\MutationException;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @internal
 */
final class MutationExceptionHandlingTest extends TestCase
{
    private const string TOKEN_SECRET = 'test-secret';

    #[Test]
    public function it_maps_a_delete_not_found_exception_to_a_json_404_response(): void
    {
        $mutator = $this->mutatorReturning(null);

        $response = $this->handleControllerException(
            fn () => (new AjaxDeleteController($mutator, new MutationTokenValidator($this->validCsrfTokenManager())))($this->validTokenRequest(), new AjaxDeleteRequestDto(
                entity: MutationExceptionHandlingFixture::class,
                id: 404,
            )),
        );

        $this->assertSame(404, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['success']);
        $this->assertSame('Entity not found.', $payload['message']);
    }

    #[Test]
    public function it_maps_a_not_writable_property_exception_to_a_json_400_response(): void
    {
        $entity = new MutationExceptionHandlingFixture();

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'enabled')->willReturn(false);

        $mutator = new EntityMutator(
            new EntityLocator($this->registryReturning($entity)),
            $accessor,
            new NullMercurePublisher(),
            new PermissionChecker(),
        );

        $response = $this->handleControllerException(
            fn () => (new AjaxEditController($mutator, new MutationTokenValidator($this->validCsrfTokenManager()), $this->contextResolver()))($this->validTokenRequest(), new AjaxEditRequestDto(
                field: 'enabled',
                id: 5,
                newValue: true,
                dataTable: $this->dataTableToken(),
            )),
        );

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['success']);
        $this->assertSame('Unable to write "enabled" on the entity.', $payload['message']);
    }

    #[Test]
    public function it_maps_an_invalid_boolean_mutation_context_to_a_json_400_response(): void
    {
        $mutator = $this->mutatorReturning(new MutationExceptionHandlingFixture());

        $response = $this->handleControllerException(
            fn () => (new AjaxEditController($mutator, new MutationTokenValidator($this->validCsrfTokenManager()), $this->contextResolver()))($this->validTokenRequest(), new AjaxEditRequestDto(
                field: 'enabled',
                id: 5,
                newValue: true,
                dataTable: 'invalid-token',
            )),
        );

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['success']);
        $this->assertSame('Invalid DataTable token.', $payload['message']);
    }

    /**
     * @param callable(): void $invokeController
     */
    private function handleControllerException(callable $invokeController): JsonResponse
    {
        try {
            $invokeController();
        } catch (MutationException $exception) {
            $event = new ExceptionEvent(
                $this->createMock(HttpKernelInterface::class),
                new Request(),
                HttpKernelInterface::MAIN_REQUEST,
                $exception,
            );

            (new MutationExceptionListener())($event);

            $response = $event->getResponse();
            $this->assertInstanceOf(JsonResponse::class, $response);

            return $response;
        }

        $this->fail('Expected a mutation exception from the controller.');
    }

    private function mutatorReturning(?object $entity): EntityMutator
    {
        return new EntityMutator(
            new EntityLocator($this->registryReturning($entity)),
            $this->createMock(PropertyAccessorInterface::class),
            new NullMercurePublisher(),
            new PermissionChecker(),
        );
    }

    private function registryReturning(?object $entity): ManagerRegistry
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($entity);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasField')->willReturnCallback(static fn (string $name): bool => 'enabled' === $name);
        $metadata->method('getTypeOfField')->willReturnCallback(static fn (string $name): ?string => 'enabled' === $name ? 'boolean' : null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(MutationExceptionHandlingFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->willReturn($metadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(MutationExceptionHandlingFixture::class)->willReturn($manager);

        return $registry;
    }

    private function validCsrfTokenManager(): CsrfTokenManagerInterface
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->method('isTokenValid')->willReturn(true);

        return $manager;
    }

    private function validTokenRequest(): Request
    {
        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        return $request;
    }

    private function contextResolver(): BooleanMutationContextResolver
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('mutation_exception_table')->willReturn(new MutationExceptionHandlingDataTableFixture());

        return new BooleanMutationContextResolver(new AjaxDataTableRegistry(
            $locator,
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [MutationExceptionHandlingDataTableFixture::class => 'mutation_exception_table'],
        ));
    }

    private function dataTableToken(): string
    {
        $token = $this->dataTableRegistry()->getBooleanMutationToken(MutationExceptionHandlingDataTableFixture::class);

        $this->assertNotNull($token);

        return $token;
    }

    private function dataTableRegistry(): AjaxDataTableRegistry
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('mutation_exception_table')->willReturn(new MutationExceptionHandlingDataTableFixture());

        return new AjaxDataTableRegistry(
            $locator,
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [MutationExceptionHandlingDataTableFixture::class => 'mutation_exception_table'],
        );
    }
}

final class MutationExceptionHandlingFixture
{
}

#[AsDataTable(entityClass: MutationExceptionHandlingFixture::class)]
final class MutationExceptionHandlingDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('enabled')->renderAsSwitch();
    }
}
