<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Symfony\Component\HttpFoundation\Request;

/**
 * Puts DataTables parameters captured by the browser back where a table reads them.
 *
 * A JSON mutation request carries them in its body, so neither bag holds them. The request bag
 * feeds handleRequest(); the query bag feeds a customizeQueryBuilder() that scopes itself on
 * forwarded query parameters, which would otherwise read null.
 *
 * @internal
 */
final class RequestQueryHydrator
{
    /**
     * @param array<string, mixed> $query
     */
    public static function hydrate(Request $request, array $query): void
    {
        foreach ($query as $key => $value) {
            $name = (string) $key;

            if (!$request->request->has($name)) {
                $request->request->set($name, $value);
            }

            if (!$request->query->has($name)) {
                $request->query->set($name, $value);
            }
        }
    }

    /**
     * The `query` field of a JSON body, narrowed to the names a table declared as forwarded.
     *
     * @param list<string> $names
     *
     * @return array<string, mixed>
     */
    public static function forwardedFromBody(Request $request, array $names): array
    {
        $body = json_decode($request->getContent(), true);

        if ([] === $names || !\is_array($body) || !\is_array($body['query'] ?? null)) {
            return [];
        }

        return array_intersect_key($body['query'], array_flip($names));
    }
}
