<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Builds the row pipeline collaborators that are not the subject under test: the Twig
 * environment a TemplateColumnRenderer needs and an action column exposing a detail URL.
 *
 * The helpers are static so data providers can build the same fixtures.
 *
 * @internal
 */
trait BuildsRowStageContext
{
    /**
     * @param array<string, string> $templates template name => Twig source
     */
    private static function templateColumnRenderer(array $templates): TemplateColumnRenderer
    {
        return new TemplateColumnRenderer(new Environment(new ArrayLoader($templates)));
    }

    /**
     * @param callable(mixed): string $url
     */
    private static function detailActionColumn(callable $url): ActionColumn
    {
        return ActionColumn::fromActions(
            'actions',
            'Actions',
            (new Actions())->add(Action::detail()->linkToUrl($url)),
        );
    }
}
