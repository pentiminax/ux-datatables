<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Security;

use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @internal
 */
#[CoversClass(MutationTokenValidator::class)]
final class MutationTokenValidatorTest extends TestCase
{
    #[Test]
    public function it_rejects_a_request_when_no_csrf_token_manager_is_configured(): void
    {
        $validator = new MutationTokenValidator(null);

        $this->expectException(InvalidCsrfTokenException::class);
        $validator->validate(new Request());
    }

    #[Test]
    public function it_accepts_a_request_carrying_a_valid_token(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken(MutationTokenValidator::TOKEN_ID, 'good'))
            ->willReturn(true);

        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'good');

        (new MutationTokenValidator($manager))->validate($request);
    }

    #[Test]
    public function it_rejects_a_request_without_a_token_header(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects($this->never())->method('isTokenValid');

        $this->expectException(InvalidCsrfTokenException::class);
        (new MutationTokenValidator($manager))->validate(new Request());
    }

    #[Test]
    public function it_rejects_a_request_carrying_an_invalid_token(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->method('isTokenValid')->willReturn(false);

        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'bad');

        $this->expectException(InvalidCsrfTokenException::class);
        (new MutationTokenValidator($manager))->validate($request);
    }
}
