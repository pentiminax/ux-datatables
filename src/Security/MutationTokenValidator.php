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
 *
 * The token travels in the `X-CSRF-Token` request header so the JSON body DTOs stay
 * untouched. The guard fails closed: a request carrying no token, or an invalid one, is
 * rejected. A token manager is always injected (the bundle provides a session-backed
 * default when the application has none), so the mutation endpoints stay protected while
 * still working out of the box.
 */
final class MutationTokenValidator
{
    public const TOKEN_ID = 'ux_datatables_mutation';

    public const HEADER = 'X-CSRF-Token';

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
            // Session-backed managers throw when no session is active. Treat it as a
            // failed check so the guard still fails closed with a clean JSON 403.
            throw new InvalidCsrfTokenException();
        }

        if (!$isValid) {
            throw new InvalidCsrfTokenException();
        }
    }
}
