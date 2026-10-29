<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Contracts\ExtensionInterface;
use Pentiminax\UX\DataTables\Enum\SearchBuilderLogic;

/**
 * The DataTables SearchBuilder extension: a UI for composing grouped search conditions.
 *
 * Options are emitted only when they differ from the upstream default.
 */
class SearchBuilderExtension implements ExtensionInterface
{
    /**
     * @param string|list<int>                   $columns     '*' for every column, or the column indexes the builder offers
     * @param array<string, array<string, null>> $conditions  conditions to disable per column type, e.g.
     *                                                        ['num' => ['!=' => null]]
     * @param int|null                           $depthLimit  maximum group nesting depth, or null for no limit
     * @param bool                               $enterSearch only search when the user presses Enter
     * @param bool                               $greyscale   render the builder in greyscale
     * @param bool                               $liveSearch  search while the user types
     * @param SearchBuilderLogic                 $logic       logic joining the top-level criteria
     * @param array<string, mixed>|null          $preDefined  criteria applied on load, in the shape of
     *                                                        `searchBuilder.getDetails()` ('criteria' and 'logic')
     */
    public function __construct(
        private readonly string|array $columns = '*',
        private readonly array $conditions = [],
        private readonly ?int $depthLimit = null,
        private readonly bool $enterSearch = false,
        private readonly bool $greyscale = false,
        private readonly bool $liveSearch = true,
        private readonly SearchBuilderLogic $logic = SearchBuilderLogic::And,
        private readonly ?array $preDefined = null,
    ) {
    }

    public function getKey(): string
    {
        return 'searchBuilder';
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'columns'     => $this->columns,
            'conditions'  => [] !== $this->conditions ? $this->conditions : null,
            'depthLimit'  => $this->depthLimit,
            'enterSearch' => $this->enterSearch ?: null,
            'greyscale'   => $this->greyscale ?: null,
            'liveSearch'  => $this->liveSearch ? null : false,
            'logic'       => SearchBuilderLogic::And === $this->logic ? null : $this->logic->value,
            'preDefined'  => $this->preDefined,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
