<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Validates the CSRF token protecting the delete and boolean-toggle mutation endpoints.
 *
 * The token travels in the `X-CSRF-Token` request header so the JSON body DTOs stay
 * untouched. When no CSRF token manager is wired (CSRF protection disabled), validation
 * is skipped so the bundle keeps working out of the box; when a manager IS present the
 * token is enforced.
 */
final class MutationTokenValidator
{
    public const TOKEN_ID = 'ux_datatables_mutation';

    public const HEADER = 'X-CSRF-Token';

    public function __construct(
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ) {
    }

    public function validate(Request $request): void
    {
        if (null === $this->csrfTokenManager) {
            return;
        }

        $value = $request->headers->get(self::HEADER);

        if (null === $value || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $value))) {
            throw new InvalidCsrfTokenException();
        }
    }
}
