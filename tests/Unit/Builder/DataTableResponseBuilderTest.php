<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Builder;

use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 */
#[CoversClass(DataTableResponseBuilder::class)]
final class DataTableResponseBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_response_with_default_values(): void
    {
        $builder  = new DataTableResponseBuilder();
        $response = $builder->buildResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1, $data['draw']);
        $this->assertEquals(0, $data['recordsTotal']);
        $this->assertEquals(0, $data['recordsFiltered']);
        $this->assertEquals([], $data['data']);
    }

    #[Test]
    public function it_builds_response_with_data(): void
    {
        $builder = new DataTableResponseBuilder();
        $data    = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ];
        $response = $builder->buildResponse(2, $data);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $jsonData = json_decode($response->getContent(), true);

        $this->assertEquals(2, $jsonData['draw']);
        $this->assertEquals(2, $jsonData['recordsTotal']);
        $this->assertEquals(2, $jsonData['recordsFiltered']);
        $this->assertEquals($data, $jsonData['data']);
    }

    #[Test]
    public function it_builds_response_with_custom_record_counts(): void
    {
        $builder  = new DataTableResponseBuilder();
        $data     = [['id' => 1, 'name' => 'Item 1']];
        $response = $builder->buildResponse(1, $data, 10, 5);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $jsonData = json_decode($response->getContent(), true);

        $this->assertEquals(1, $jsonData['draw']);
        $this->assertEquals(10, $jsonData['recordsTotal']);
        $this->assertEquals(5, $jsonData['recordsFiltered']);
        $this->assertEquals($data, $jsonData['data']);
    }
}
