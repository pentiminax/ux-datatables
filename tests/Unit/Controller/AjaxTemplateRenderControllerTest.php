<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformItemResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Controller\AjaxTemplateRenderController;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(AjaxTemplateRenderController::class)]
final class AjaxTemplateRenderControllerTest extends TestCase
{
    #[Test]
    public function it_loads_and_renders_the_api_platform_collection_with_one_subrequest(): void
    {
        $table    = new TemplateRenderDataTableFixture();
        $registry = $this->createRegistry($table);

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(
                templateColumnRenderer: new TemplateColumnRenderer(new Environment(new ArrayLoader([
                    'user.html.twig' => '<span>{{ row.email }}:{{ data }}</span>',
                ]))),
            ),
            [new TemplateRenderUserFixture(7)],
            [[
                '@id'    => '/api/users/7',
                'id'     => 7,
                'avatar' => 'https://example.test/avatar.png',
                'email'  => 'user@example.com',
            ]],
            $this->createItemResolver(true),
        );

        $response = $controller($this->createRequest(
            $registry->getToken($table::class),
            draw: 6,
            query: 'page=1&itemsPerPage=25',
        ));

        $this->assertSame([
            'draw'            => 6,
            'recordsTotal'    => 1,
            'recordsFiltered' => 1,
            'data'            => [[
                '@id'    => '/api/users/7',
                'id'     => 7,
                'avatar' => '<span>user@example.com:https://example.test/avatar.png</span>',
                'email'  => 'user@example.com',
            ]],
        ], $this->decode($response));
    }

    #[Test]
    public function it_resolves_actions_and_urls_from_loaded_entities(): void
    {
        $table    = new TemplateRenderActionsAndUrlsDataTableFixture();
        $registry = $this->createRegistry($table);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_user_show', ['id' => 7])
            ->willReturn('/profiles/7');

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(
                actionRowDataResolver: new ActionRowDataResolver(),
                urlColumnDataResolver: new UrlColumnDataResolver($urlGenerator),
            ),
            [new TemplateRenderUserFixture(7)],
            [['@id' => '/api/users/7', 'id' => 7, 'profile' => 'Show']],
            $this->createItemResolver(true),
        );

        $data = $this->decode($controller($this->createRequest($registry->getToken($table::class))))['data'][0];

        $this->assertSame('/users/7', $data['__ux_datatables_actions']['DETAIL']['url']);
        $this->assertSame('/profiles/7', $data['__ux_datatables_urls']['profile']);
    }

    #[Test]
    public function it_leaves_rows_unrendered_when_item_security_denies_access(): void
    {
        $table    = new TemplateRenderDataTableFixture();
        $registry = $this->createRegistry($table);
        $row      = [
            '@id'    => '/api/users/7',
            'id'     => 7,
            'avatar' => 'https://example.test/avatar.png',
            'email'  => 'user@example.com',
        ];

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(templateColumnRenderer: new TemplateColumnRenderer($twig)),
            [new TemplateRenderUserFixture(7)],
            [$row],
            $this->createItemResolver(false),
        );

        $this->assertSame([$row], $this->decode(
            $controller($this->createRequest($registry->getToken($table::class)))
        )['data']);
    }

    #[Test]
    public function it_leaves_rows_unrendered_without_an_item_security_resolver(): void
    {
        $table    = new TemplateRenderDataTableFixture();
        $registry = $this->createRegistry($table);
        $row      = ['@id' => '/api/users/7', 'id' => 7, 'avatar' => 'avatar.png'];

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(),
            [new TemplateRenderUserFixture(7)],
            [$row],
        );

        $this->assertSame([$row], $this->decode(
            $controller($this->createRequest($registry->getToken($table::class)))
        )['data']);
    }

    #[Test]
    #[TestWith([50])]
    #[TestWith([200])]
    public function it_uses_one_kernel_call_regardless_of_page_size(int $pageSize): void
    {
        $table          = new TemplateRenderDataTableFixture();
        $registry       = $this->createRegistry($table);
        $sourceRows     = [];
        $serializedRows = [];

        for ($id = 1; $id <= $pageSize; ++$id) {
            $sourceRows[]     = new TemplateRenderUserFixture($id);
            $serializedRows[] = ['@id' => '/api/users/'.$id, 'id' => $id];
        }

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(),
            $sourceRows,
            $serializedRows,
            maxRows: 250,
        );

        $response = $controller($this->createRequest(
            $registry->getToken($table::class),
            query: 'page=1&itemsPerPage='.$pageSize,
        ));

        $this->assertCount($pageSize, $this->decode($response)['data']);
    }

    #[Test]
    public function it_caps_page_length_and_preserves_raw_filter_names(): void
    {
        $table    = new TemplateRenderDataTableFixture();
        $registry = $this->createRegistry($table);

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(),
            [],
            [],
            maxRows: 20,
            inspectRequest: static function (Request $request): void {
                $query = (string) $request->getQueryString();

                self::assertStringContainsString('author.firstName=John', $query);
                self::assertStringContainsString('itemsPerPage=20', $query);
                self::assertStringNotContainsString('itemsPerPage=999', $query);
                self::assertStringContainsString('order%5Bemail%5D=asc', $query);
            },
        );

        $controller($this->createRequest(
            $registry->getToken($table::class),
            query: 'author.firstName=John&itemsPerPage=200&itemsPerPage=999&order%5Bemail%5D=asc',
        ));
    }

    #[Test]
    public function it_propagates_a_collection_security_refusal(): void
    {
        $table    = new TemplateRenderDataTableFixture();
        $registry = $this->createRegistry($table);

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(),
            [],
            [],
            status: 403,
        );

        try {
            $controller($this->createRequest($registry->getToken($table::class)));
            self::fail('A denied collection must not be rendered.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    #[Test]
    public function it_throws_404_when_table_token_is_unknown(): void
    {
        $controller = $this->createUnusedController($this->createRegistry());

        $this->expectException(NotFoundHttpException::class);

        $controller($this->createRequest('unknown'));
    }

    #[Test]
    public function it_throws_400_when_the_collection_query_is_missing(): void
    {
        $table      = new TemplateRenderDataTableFixture();
        $registry   = $this->createRegistry($table);
        $controller = $this->createUnusedController($registry);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('No collection query provided.');

        $controller(new Request(content: json_encode([
            'table' => $registry->getToken($table::class),
            'draw'  => 1,
        ], \JSON_THROW_ON_ERROR)));
    }

    /**
     * @param list<mixed>                   $sourceRows
     * @param list<array<array-key, mixed>> $serializedRows
     */
    private function createController(
        AjaxDataTableRegistry $registry,
        DataTableRuntimeFactory $runtimeFactory,
        array $sourceRows,
        array $serializedRows,
        ?ApiPlatformItemResolver $itemResolver = null,
        int $maxRows = 1000,
        int $status = 200,
        ?\Closure $inspectRequest = null,
    ): AjaxTemplateRenderController {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->expects($this->once())
            ->method('handle')
            ->with($this->callback(static function (Request $request) use ($sourceRows, $inspectRequest): bool {
                self::assertSame('/api/users', $request->getPathInfo());
                $inspectRequest?->__invoke($request);
                $request->attributes->set('data', new \ArrayIterator($sourceRows));

                return true;
            }), HttpKernelInterface::SUB_REQUEST)
            ->willReturn(new JsonResponse([
                'hydra:member'     => $serializedRows,
                'hydra:totalItems' => \count($serializedRows),
            ], $status));

        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolver::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn('/api/users');

        return new AjaxTemplateRenderController(
            $registry,
            $runtimeFactory,
            $kernel,
            new RequestStack(),
            $urlResolver,
            $itemResolver,
            $maxRows,
        );
    }

    private function createUnusedController(AjaxDataTableRegistry $registry): AjaxTemplateRenderController
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->expects($this->never())->method('handle');

        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolver::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn('/api/users');

        return new AjaxTemplateRenderController(
            $registry,
            new DataTableRuntimeFactory(),
            $kernel,
            new RequestStack(),
            $urlResolver,
            null,
            1000,
        );
    }

    private function createItemResolver(bool $granted): ApiPlatformItemResolver
    {
        $resolver = $this->createMock(ApiPlatformItemResolver::class);
        $resolver->expects($this->never())->method('resolve');
        $resolver->method('isGranted')->willReturn($granted);

        return $resolver;
    }

    private function createRegistry(?AbstractDataTable $table = null): AjaxDataTableRegistry
    {
        $services = null === $table ? [] : ['app.template_render_datatable' => static fn (): AbstractDataTable => $table];
        $map      = null === $table ? [] : [$table::class => 'app.template_render_datatable'];

        return new AjaxDataTableRegistry(
            new ServiceLocator($services),
            new AjaxDataTableTokenManager('test-secret'),
            $map,
        );
    }

    private function createRequest(?string $token, int $draw = 1, string $query = 'page=1&itemsPerPage=10'): Request
    {
        return new Request(content: json_encode([
            'table' => $token,
            'draw'  => $draw,
            'query' => $query,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}

#[AsDataTable(entityClass: TemplateRenderUserFixture::class, apiPlatform: true)]
final class TemplateRenderDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TemplateColumn::new('avatar', 'Avatar')
            ->setTemplate('user.html.twig');

        yield TextColumn::new('email', 'Email');
    }
}

#[AsDataTable(entityClass: TemplateRenderUserFixture::class, apiPlatform: true)]
final class TemplateRenderActionsAndUrlsDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield UrlColumn::new('profile', 'Profile')
            ->linkToRoute(
                'app_user_show',
                static fn (TemplateRenderUserFixture $user): array => ['id' => $user->getId()]
            );
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail(label: '', className: 'detail')
                ->linkToUrl(static fn (TemplateRenderUserFixture $user): string => '/users/'.$user->getId())
        );
    }
}

final class TemplateRenderUserFixture
{
    public function __construct(
        private readonly int $id,
        private readonly string $email = 'user@example.com',
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
