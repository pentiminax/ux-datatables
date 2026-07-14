<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Controller\AjaxDeleteController;
use Pentiminax\UX\DataTables\Dto\AjaxDeleteRequestDto;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @internal
 */
#[CoversClass(AjaxDeleteController::class)]
final class AjaxDeleteControllerTest extends TestCase
{
    #[Test]
    public function it_removes_the_entity_and_returns_success(): void
    {
        $entity = new DeletableEntityFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(12)->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(DeletableEntityFixture::class)->willReturn($repository);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(DeletableEntityFixture::class)->willReturn($manager);

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/topic/12'], ['type' => 'delete', 'id' => 12]);

        $mutator = new EntityMutator(new EntityLocator($registry), $this->createMock(PropertyAccessorInterface::class), $publisher, new PermissionChecker());

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $controller = new AjaxDeleteController($mutator, new MutationTokenValidator($csrfTokenManager));

        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        $response = $controller($request, new AjaxDeleteRequestDto(
            entity: DeletableEntityFixture::class,
            id: 12,
            topics: ['/topic/12'],
        ));

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['success']);
    }

    #[Test]
    public function it_removes_the_entity_when_the_csrf_token_is_valid(): void
    {
        $entity = new DeletableEntityFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(12)->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(DeletableEntityFixture::class)->willReturn($repository);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(DeletableEntityFixture::class)->willReturn($manager);

        $mutator = new EntityMutator(new EntityLocator($registry), $this->createMock(PropertyAccessorInterface::class), new NullMercurePublisher(), new PermissionChecker());

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')
            ->with(new CsrfToken(MutationTokenValidator::TOKEN_ID, 'valid-token'))
            ->willReturn(true);

        $controller = new AjaxDeleteController($mutator, new MutationTokenValidator($csrfTokenManager));

        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        $response = $controller($request, new AjaxDeleteRequestDto(entity: DeletableEntityFixture::class, id: 12));

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_the_request_and_does_not_delete_when_the_csrf_token_is_invalid(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $mutator = new EntityMutator(new EntityLocator($registry), $this->createMock(PropertyAccessorInterface::class), new NullMercurePublisher(), new PermissionChecker());

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(false);

        $controller = new AjaxDeleteController($mutator, new MutationTokenValidator($csrfTokenManager));

        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'wrong-token');

        $this->expectException(InvalidCsrfTokenException::class);
        $controller($request, new AjaxDeleteRequestDto(entity: DeletableEntityFixture::class, id: 12));
    }

    #[Test]
    public function it_rejects_the_request_and_does_not_delete_when_the_token_header_is_missing(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $mutator = new EntityMutator(new EntityLocator($registry), $this->createMock(PropertyAccessorInterface::class), new NullMercurePublisher(), new PermissionChecker());

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $controller = new AjaxDeleteController($mutator, new MutationTokenValidator($csrfTokenManager));

        $this->expectException(InvalidCsrfTokenException::class);
        $controller(new Request(), new AjaxDeleteRequestDto(entity: DeletableEntityFixture::class, id: 12));
    }

    #[Test]
    public function it_lets_a_missing_entity_bubble_as_an_exception(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(404)->willReturn(null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $mutator = new EntityMutator(new EntityLocator($registry), $this->createMock(PropertyAccessorInterface::class), new NullMercurePublisher(), new PermissionChecker());

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $controller = new AjaxDeleteController($mutator, new MutationTokenValidator($csrfTokenManager));

        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        $this->expectException(EntityNotFoundException::class);
        $controller($request, new AjaxDeleteRequestDto(entity: DeletableEntityFixture::class, id: 404));
    }
}

final class DeletableEntityFixture
{
}
