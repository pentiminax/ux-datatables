<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\RequestInputBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RequestInputBag::class)]
final class RequestInputBagTest extends TestCase
{
    #[Test]
    public function it_resolves_the_query_bag_for_a_get_request(): void
    {
        $request = Request::create('/ajax', 'GET', ['draw' => '1']);

        $this->assertSame($request->query, RequestInputBag::resolve($request));
    }

    #[Test]
    public function it_resolves_the_request_bag_for_a_post_request(): void
    {
        $request = Request::create('/ajax', 'POST', ['draw' => '1']);

        $this->assertSame($request->request, RequestInputBag::resolve($request));
    }

    #[Test]
    public function it_ignores_query_parameters_on_a_post_request(): void
    {
        $request = Request::create('/ajax?draw=99', 'POST', ['draw' => '1']);

        $this->assertSame('1', RequestInputBag::resolve($request)->get('draw'));
    }

    #[Test]
    public function it_resolves_the_request_bag_for_a_put_request(): void
    {
        $request = Request::create('/ajax', 'PUT', ['draw' => '1']);

        $this->assertSame($request->request, RequestInputBag::resolve($request));
    }

    #[Test]
    public function it_resolves_the_request_bag_for_a_patch_request(): void
    {
        $request = Request::create('/ajax', 'PATCH', ['draw' => '1']);

        $this->assertSame($request->request, RequestInputBag::resolve($request));
    }

    /**
     * DataTables' own client-side ajax() helper moves DELETE's parameters onto the URL by
     * default (some servers reject a request body on DELETE), so unlike every other
     * non-GET method, a DELETE table's parameters land in the query string, not the body.
     */
    #[Test]
    public function it_resolves_the_query_bag_for_a_delete_request_by_default(): void
    {
        $request = Request::create('/ajax?draw=1', 'DELETE');

        $this->assertSame($request->query, RequestInputBag::resolve($request));
    }
}
