<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Validates the CSRF token protecting the delete and boolean-toggle mutation endpoints.
 */
final class MutationTokenValidator
{
    public const string TOKEN_ID = 'ux_datatables_mutation';

    public const string HEADER = 'X-CSRF-Token';

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function validate(Request $request): void
    {
        $value = $request->headers->get(self::HEADER);

        if (null === $value) {
            throw new InvalidCsrfTokenException();
        }

        try {
            $isValid = $this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $value));
        } catch (SessionNotFoundException) {
            throw new InvalidCsrfTokenException();
        }

        if (!$isValid) {
            throw new InvalidCsrfTokenException();
        }
    }
}
