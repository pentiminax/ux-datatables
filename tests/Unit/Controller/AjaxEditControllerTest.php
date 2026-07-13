<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Dto\AjaxEditRequestDto;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @internal
 */
#[CoversClass(AjaxEditController::class)]
final class AjaxEditControllerTest extends TestCase
{
    #[Test]
    public function it_returns_zero_when_updating_boolean_field_to_false(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'isEmailAuthEnabled')->willReturn(true);
        $accessor->expects($this->once())->method('setValue')->with($entity, 'isEmailAuthEnabled', false);

        $controller = $this->controller($entity, 799, $accessor, expectFlush: true);

        $response = $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: false,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('0', (string) $response->getContent());
    }

    #[Test]
    public function it_returns_one_when_updating_boolean_field_to_true(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'isEmailAuthEnabled')->willReturn(true);
        $accessor->expects($this->once())->method('setValue')->with($entity, 'isEmailAuthEnabled', true);

        $controller = $this->controller($entity, 799, $accessor, expectFlush: true);

        $response = $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: true,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1', (string) $response->getContent());
    }

    #[Test]
    public function it_lets_a_not_writable_field_bubble_as_an_exception(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'isEmailAuthEnabled')->willReturn(false);
        $accessor->expects($this->never())->method('setValue');

        $controller = $this->controller($entity, 799, $accessor, expectFlush: false);

        $this->expectException(PropertyNotWritableException::class);
        $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: false,
        ));
    }

    #[Test]
    public function it_rejects_a_field_that_is_not_a_mapped_boolean(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects($this->never())->method('setValue');

        $controller = $this->controller($entity, 799, $accessor, expectFlush: false);

        $this->expectException(FieldNotToggleableException::class);
        $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'admin',
            id: 799,
            newValue: true,
        ));
    }

    #[Test]
    public function it_lets_a_missing_entity_bubble_as_an_exception(): void
    {
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects($this->never())->method('isWritable');

        $controller = $this->controller(null, 799, $accessor, expectFlush: false);

        $this->expectException(EntityNotFoundException::class);
        $controller(new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: false,
        ));
    }

    private function controller(
        ?object $entity,
        int|string $id,
        PropertyAccessorInterface $accessor,
        bool $expectFlush,
    ): AjaxEditController {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with($id)->willReturn($entity);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasField')->willReturnCallback(static fn (string $name): bool => 'isEmailAuthEnabled' === $name);
        $metadata->method('getTypeOfField')->willReturnCallback(static fn (string $name): ?string => 'isEmailAuthEnabled' === $name ? 'boolean' : null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(ToggleBooleanEntityFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->willReturn($metadata);
        $manager->expects($expectFlush ? $this->once() : $this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(ToggleBooleanEntityFixture::class)->willReturn($manager);

        $mutator = new EntityMutator(new EntityLocator($registry), $accessor, new NullMercurePublisher());

        return new AjaxEditController($mutator);
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
