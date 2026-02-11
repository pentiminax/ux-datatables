<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class BooleanToggleControllerTest extends TestCase
{
    public function testUpdatesBooleanFieldFromJsonPayload(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasField')->with('isEmailAuthEnabled')->willReturn(true);
        $metadata->method('getTypeOfField')->with('isEmailAuthEnabled')->willReturn('boolean');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with('799')->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->with(ToggleBooleanEntityFixture::class)->willReturn($metadata);
        $entityManager->method('getRepository')->with(ToggleBooleanEntityFixture::class)->willReturn($repository);
        $entityManager->expects($this->once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(ToggleBooleanEntityFixture::class)->willReturn($entityManager);

        $controller = new AjaxEditController($registry);
        $request    = Request::create(
            '/datatables/ajax/edit',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'entity' => ToggleBooleanEntityFixture::class,
                'id'     => '799',
                'field'  => 'isEmailAuthEnabled',
                'value'  => false,
            ], \JSON_THROW_ON_ERROR)
        );

        $response = $controller($request);
        $payload  = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($entity->isEmailAuthEnabled());
        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['value']);
    }

    public function testRejectsNonBooleanDoctrineField(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasField')->with('isEmailAuthEnabled')->willReturn(true);
        $metadata->method('getTypeOfField')->with('isEmailAuthEnabled')->willReturn('string');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with('799')->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->with(ToggleBooleanEntityFixture::class)->willReturn($metadata);
        $entityManager->method('getRepository')->with(ToggleBooleanEntityFixture::class)->willReturn($repository);
        $entityManager->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(ToggleBooleanEntityFixture::class)->willReturn($entityManager);

        $controller = new AjaxEditController($registry);
        $request    = Request::create(
            '/datatables/ajax/edit',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'entity' => ToggleBooleanEntityFixture::class,
                'id'     => '799',
                'field'  => 'isEmailAuthEnabled',
                'value'  => false,
            ], \JSON_THROW_ON_ERROR)
        );

        $response = $controller($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertTrue($entity->isEmailAuthEnabled());
    }

    public function testReturnsNotImplementedWhenDoctrineIsMissing(): void
    {
        $controller = new AjaxEditController(null);
        $request    = Request::create('/datatables/ajax/edit', 'PATCH');

        $response = $controller($request);

        $this->assertSame(501, $response->getStatusCode());
    }

    public function testSupportsEasyAdminLikeQueryParameters(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasField')->with('isEmailAuthEnabled')->willReturn(true);
        $metadata->method('getTypeOfField')->with('isEmailAuthEnabled')->willReturn('boolean');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with('799')->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->with(ToggleBooleanEntityFixture::class)->willReturn($metadata);
        $entityManager->method('getRepository')->with(ToggleBooleanEntityFixture::class)->willReturn($repository);
        $entityManager->expects($this->once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(ToggleBooleanEntityFixture::class)->willReturn($entityManager);

        $controller = new AjaxEditController($registry);
        $request    = Request::create(
            '/datatables/ajax/edit?entity='.urlencode(ToggleBooleanEntityFixture::class).'&fieldName=isEmailAuthEnabled&newValue=false',
            'PATCH'
        );

        $response = $controller($request, '799');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($entity->isEmailAuthEnabled());
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
