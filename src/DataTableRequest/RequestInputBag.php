<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataTableRequest;

use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * {@see \Pentiminax\UX\DataTables\Model\DataTable::ajax()}'s $type argument accepts any HTTP
 * method, and DataTables puts the request's parameters wherever its own client-side ajax()
 * helper puts them for that method -- not always the query string. Resolve whichever bag the
 * request actually used instead of assuming $request->query, so a table configured with a
 * body-carrying method doesn't silently parse an empty parameter set.
 *
 * GET always uses the query string. DELETE does too, by default: DataTables' client-side
 * queryParams() moves DELETE's parameters onto the URL unless the app explicitly sets
 * ajax.deleteBody = false, because some servers reject a request body on DELETE (see the
 * comment at datatables.net/js/dataTables.js's queryParams()). DataTable::ajax() has no PHP
 * option for deleteBody, so every DELETE table reachable through the bundle's own API uses
 * the query string. Every other method (POST, PUT, PATCH, ...) puts parameters in the body.
 */
final class RequestInputBag
{
    private const QUERY_STRING_METHODS = ['GET', 'DELETE'];

    public static function resolve(Request $request): InputBag
    {
        return \in_array($request->getMethod(), self::QUERY_STRING_METHODS, true)
            ? $request->query
            : $request->request;
    }

    /**
     * Copy scalar body parameters onto the query bag so documented
     * `$this->getHttpRequest()?->query->get()` reads them on POST.
     *
     * Auto-Ajax is GET: DataTables puts `ajax.data` — including values captured by
     * {@see \Pentiminax\UX\DataTables\Model\DataTable::forwardQueryParameters()} — on the
     * query string. The export endpoint is POST and carries the same payload in the body,
     * so a customizeQueryBuilder() that scopes by those parameters would otherwise see
     * null and stream the unscoped dataset.
     *
     * Existing query keys are left untouched (the table token lives there). Array values are
     * copied as-is, so a forwarded `tenantIds[]` stays readable through
     * `$this->getHttpRequest()?->query->all('tenantIds')`; dropping them would let a table
     * scoped by an array parameter export the unscoped dataset.
     */
    public static function exposeBodyParametersOnQuery(Request $request): void
    {
        if (\in_array($request->getMethod(), self::QUERY_STRING_METHODS, true)) {
            return;
        }

        foreach ($request->request->all() as $key => $value) {
            $name = (string) $key;
            if ($request->query->has($name)) {
                continue;
            }

            if (!\is_scalar($value) && !\is_array($value) && !$value instanceof \Stringable) {
                continue;
            }

            $request->query->set($name, $value instanceof \Stringable ? (string) $value : $value);
        }
    }
}
