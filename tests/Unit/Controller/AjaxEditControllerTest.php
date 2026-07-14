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
use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
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

        $response = $controller($this->validTokenRequest(), new AjaxEditRequestDto(
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

        $response = $controller($this->validTokenRequest(), new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: true,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1', (string) $response->getContent());
    }

    #[Test]
    public function it_updates_the_field_when_the_csrf_token_is_valid(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'isEmailAuthEnabled')->willReturn(true);
        $accessor->expects($this->once())->method('setValue')->with($entity, 'isEmailAuthEnabled', true);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')
            ->with(new CsrfToken(MutationTokenValidator::TOKEN_ID, 'valid-token'))
            ->willReturn(true);

        $controller = $this->controller($entity, 799, $accessor, expectFlush: true, csrfTokenManager: $csrfTokenManager);

        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        $response = $controller($request, new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: true,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1', (string) $response->getContent());
    }

    #[Test]
    public function it_returns_one_when_updating_boolean_field_to_true_with_a_string_id(): void
    {
        $entity = new ToggleBooleanEntityFixture();

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'isEmailAuthEnabled')->willReturn(true);
        $accessor->expects($this->once())->method('setValue')->with($entity, 'isEmailAuthEnabled', true);

        $controller = $this->controller($entity, '018f2c3e-1234-7abc-9def-0123456789ab', $accessor, expectFlush: true);

        $response = $controller($this->validTokenRequest(), new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: '018f2c3e-1234-7abc-9def-0123456789ab',
            newValue: true,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1', (string) $response->getContent());
    }

    #[Test]
    public function it_rejects_the_request_and_does_not_update_when_the_csrf_token_is_invalid(): void
    {
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects($this->never())->method('setValue');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(false);

        $controller = $this->controller(null, 799, $accessor, expectFlush: false, csrfTokenManager: $csrfTokenManager);

        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'wrong-token');

        $this->expectException(InvalidCsrfTokenException::class);
        $controller($request, new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: true,
        ));
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
        $controller($this->validTokenRequest(), new AjaxEditRequestDto(
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
        $controller($this->validTokenRequest(), new AjaxEditRequestDto(
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
        $controller($this->validTokenRequest(), new AjaxEditRequestDto(
            entity: ToggleBooleanEntityFixture::class,
            field: 'isEmailAuthEnabled',
            id: 799,
            newValue: false,
        ));
    }

    #[Test]
    public function it_rejects_the_request_and_does_not_update_when_the_token_header_is_missing(): void
    {
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects($this->never())->method('setValue');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $controller = $this->controller(new ToggleBooleanEntityFixture(), 799, $accessor, expectFlush: false, csrfTokenManager: $csrfTokenManager);

        $this->expectException(InvalidCsrfTokenException::class);
        $controller(new Request(), new AjaxEditRequestDto(
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
        ?CsrfTokenManagerInterface $csrfTokenManager = null,
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

        $mutator = new EntityMutator(new EntityLocator($registry), $accessor, new NullMercurePublisher(), new PermissionChecker());

        if (null === $csrfTokenManager) {
            $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
            $csrfTokenManager->method('isTokenValid')->willReturn(true);
        }

        return new AjaxEditController($mutator, new MutationTokenValidator($csrfTokenManager));
    }

    private function validTokenRequest(): Request
    {
        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        return $request;
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
