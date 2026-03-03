<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(Search::class)]
final class SearchTest extends TestCase
{
    #[Test]
    public function it_parses_from_request(): void
    {
        $request = new Request(
            query: [
                'search' => [
                    'value' => 'test',
                    'regex' => false,
                ],
            ]
        );

        $search = Search::fromRequest($request);

        $this->assertEquals('test', $search->value);
        $this->assertFalse($search->regex);
    }
}
