<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * Builds the default Mercure topic for a DataTable resource.
 */
final class MercureTopicFactory
{
    /**
     * @param string $shortName Short resource name (e.g. "Product", "OrderLine")
     *
     * @return string Topic of the form "/datatables/{plural-slug}/{id}"
     */
    public static function fallbackTopic(string $shortName): string
    {
        $slug = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName));

        return '/datatables/'.(new EnglishInflector())->pluralize($slug)[0].'/{id}';
    }
}
