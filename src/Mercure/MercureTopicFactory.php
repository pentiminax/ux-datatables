<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * Builds the default Mercure topic for a DataTable resource.
 *
 * Single source of truth for the fallback-topic convention shared by
 * {@see \Pentiminax\UX\DataTables\Model\DataTable::mercure()} (which passes the
 * table's short id) and {@see MercureConfigResolver} (which passes the entity's
 * short name). Both feed a short resource name through the same slug +
 * pluralization algorithm, so the topic a client subscribes to always matches
 * the one the server publishes to.
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
