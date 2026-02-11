<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Dto\AjaxEditRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class AjaxEditControllerTest extends TestCase
{
    public function testUpdatesBooleanFieldFromJsonPayload(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(799)->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(ToggleBooleanEntityFixture::class)->willReturn($repository);
        $entityManager->expects($this->once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(ToggleBooleanEntityFixture::class)->willReturn($entityManager);

        $propertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $propertyAccessor->method('isWritable')->with($entity, 'isEmailAuthEnabled')->willReturn(true);
        $propertyAccessor->expects($this->once())->method('setValue')->with($entity, 'isEmailAuthEnabled', false);

        $controller = new AjaxEditController($registry, $propertyAccessor);
        $response   = $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: false,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('0', (string) $response->getContent());
    }

    public function testRejectsNonWritableField(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(799)->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(ToggleBooleanEntityFixture::class)->willReturn($repository);
        $entityManager->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(ToggleBooleanEntityFixture::class)->willReturn($entityManager);

        $propertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $propertyAccessor->method('isWritable')->with($entity, 'isEmailAuthEnabled')->willReturn(false);
        $propertyAccessor->expects($this->never())->method('setValue');

        $controller = new AjaxEditController($registry, $propertyAccessor);
        $response   = $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: false,
        ));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertTrue(isset($payload['success']) && false === $payload['success']);
        $this->assertSame('Unable to write "isEmailAuthEnabled" on the entity.', $payload['message']);
        $this->assertTrue($entity->isEmailAuthEnabled());
    }

    public function testReturnsOneWhenUpdatingBooleanFieldToTrue(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(799)->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(ToggleBooleanEntityFixture::class)->willReturn($repository);
        $entityManager->expects($this->once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(ToggleBooleanEntityFixture::class)->willReturn($entityManager);

        $propertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $propertyAccessor->method('isWritable')->with($entity, 'isEmailAuthEnabled')->willReturn(true);
        $propertyAccessor->expects($this->once())->method('setValue')->with($entity, 'isEmailAuthEnabled', true);

        $controller = new AjaxEditController($registry, $propertyAccessor);
        $response   = $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: true,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1', (string) $response->getContent());
    }

    public function testReturnsNotFoundWhenEntityDoesNotExist(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(799)->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(ToggleBooleanEntityFixture::class)->willReturn($repository);
        $entityManager->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(ToggleBooleanEntityFixture::class)->willReturn($entityManager);

        $propertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $propertyAccessor->expects($this->never())->method('isWritable');
        $propertyAccessor->expects($this->never())->method('setValue');

        $controller = new AjaxEditController($registry, $propertyAccessor);
        $response   = $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: false,
        ));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertTrue(isset($payload['success']) && false === $payload['success']);
        $this->assertSame('Entity not found.', $payload['message']);
    }
}

final class ToggleBooleanEntityFixture
{
    private bool $isEmailAuthEnabled = true;

    public function setIsEmailAuthEnabled(bool $value): void
    {
        $this->isEmailAuthEnabled = $value;
    }

    public function isEmailAuthEnabled(): bool
    {
        return $this->isEmailAuthEnabled;
    }
}
