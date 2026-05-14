<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Security;

use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @internal
 */
#[CoversClass(PermissionChecker::class)]
final class PermissionCheckerTest extends TestCase
{
    #[Test]
    public function grants_everything_when_no_checker_is_provided(): void
    {
        $checker = new PermissionChecker();

        $this->assertTrue($checker->isGranted('ROLE_ADMIN'));
        $this->assertTrue($checker->isGranted('EDIT', new \stdClass()));
    }

    #[Test]
    public function delegates_to_inner_checker_with_attribute_and_subject(): void
    {
        $subject = new \stdClass();
        $inner   = $this->createMock(AuthorizationCheckerInterface::class);
        $inner
            ->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', $subject)
            ->willReturn(true);

        $this->assertTrue((new PermissionChecker($inner))->isGranted('EDIT', $subject));
    }

    #[Test]
    public function returns_false_when_no_credentials_are_available(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willThrowException(new AuthenticationCredentialsNotFoundException());

        $this->assertFalse((new PermissionChecker($inner))->isGranted('ROLE_ADMIN'));
    }

    #[Test]
    public function propagates_denial_from_inner_checker(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willReturn(false);

        $this->assertFalse((new PermissionChecker($inner))->isGranted('ROLE_ADMIN'));
    }
}
