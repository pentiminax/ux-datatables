<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Security;

use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @internal
 */
#[CoversClass(AuthorizationChecker::class)]
final class AuthorizationCheckerTest extends TestCase
{
    #[Test]
    public function grants_missing_permissions_without_delegating(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->expects($this->never())->method('isGranted');

        $checker = new AuthorizationChecker($inner);

        $this->assertTrue($checker->isGranted(null));
        $this->assertTrue($checker->isGranted(''));
    }

    #[Test]
    public function grants_everything_when_no_checker_is_provided(): void
    {
        $checker = new AuthorizationChecker();

        $this->assertTrue($checker->isGranted('ROLE_ADMIN'));
        $this->assertTrue($checker->isGranted('EDIT', new \stdClass()));
    }

    #[Test]
    public function delegates_to_inner_checker_with_attribute_subject_and_decision(): void
    {
        $subject        = new \stdClass();
        $accessDecision = new AccessDecision();
        $inner          = $this->createMock(AuthorizationCheckerInterface::class);
        $inner
            ->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', $subject, $accessDecision)
            ->willReturn(true);

        $this->assertTrue((new AuthorizationChecker($inner))->isGranted('EDIT', $subject, $accessDecision));
    }

    #[Test]
    public function delegates_expression_attributes_without_casting_them(): void
    {
        $expression = new Expression('"ROLE_ADMIN" in role_names');
        $inner      = $this->createMock(AuthorizationCheckerInterface::class);
        $inner
            ->expects($this->once())
            ->method('isGranted')
            ->with($expression, null, null)
            ->willReturn(true);

        $this->assertTrue((new AuthorizationChecker($inner))->isGranted($expression));
    }

    #[Test]
    public function returns_false_when_no_credentials_are_available(): void
    {
        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willThrowException(new AuthenticationCredentialsNotFoundException());

        $this->assertFalse((new AuthorizationChecker($inner))->isGranted('ROLE_ADMIN'));
    }

    #[Test]
    public function propagates_non_authentication_exceptions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decision manager failed.');

        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willThrowException(new \RuntimeException('Decision manager failed.'));

        (new AuthorizationChecker($inner))->isGranted('ROLE_ADMIN');
    }
}
