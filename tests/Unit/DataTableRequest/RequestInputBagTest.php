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

    #[Test]
    public function it_copies_scalar_body_parameters_onto_the_query_bag_for_post(): void
    {
        $request = Request::create('/datatables/ajax/export?table=token', 'POST', [
            'draw'      => '1',
            'pending'   => '1',
            'columns'   => [['data' => 'email']],
            'exportKey' => 'csv',
        ]);

        RequestInputBag::exposeBodyParametersOnQuery($request);

        $this->assertSame('token', $request->query->get('table'));
        $this->assertSame('1', $request->query->get('pending'));
        $this->assertSame('1', $request->query->get('draw'));
        $this->assertFalse($request->query->has('columns'));
    }

    #[Test]
    public function it_does_not_overwrite_existing_query_parameters(): void
    {
        $request = Request::create('/datatables/ajax/export?table=from-query', 'POST', [
            'table' => 'from-body',
        ]);

        RequestInputBag::exposeBodyParametersOnQuery($request);

        $this->assertSame('from-query', $request->query->get('table'));
    }

    #[Test]
    public function it_leaves_the_query_bag_unchanged_on_get(): void
    {
        $request = Request::create('/datatables/ajax/data?table=token&pending=1', 'GET');
        $request->request->set('pending', 'from-body');

        RequestInputBag::exposeBodyParametersOnQuery($request);

        $this->assertSame('1', $request->query->get('pending'));
    }
}
