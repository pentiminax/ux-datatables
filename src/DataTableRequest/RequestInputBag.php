<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataTableRequest;

use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * DataTables sends its Ajax parameters in the query string by default, but
 * {@see \Pentiminax\UX\DataTables\Model\DataTable::ajax()}'s $type argument lets an app
 * configure a POST request instead -- DataTables then puts the same parameters in the
 * request body. Resolve whichever bag the request actually used instead of assuming
 * $request->query, so POST tables don't silently parse an empty parameter set.
 */
final class RequestInputBag
{
    public static function resolve(Request $request): InputBag
    {
        return $request->isMethod('POST') ? $request->request : $request->query;
    }
}
