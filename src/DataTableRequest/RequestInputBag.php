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
}
