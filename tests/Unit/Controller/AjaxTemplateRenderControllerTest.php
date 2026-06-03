<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
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
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
    public function it_renders_template_columns_from_api_rows(): void
    {
        $registry = $this->createRegistry(new TemplateRenderDataTableFixture());
        $token    = $registry->getToken(TemplateRenderDataTableFixture::class);

        $controller = new AjaxTemplateRenderController(
            $registry,
            new DataTableRuntimeFactory(
                templateColumnRenderer: new TemplateColumnRenderer(new Environment(new ArrayLoader([
                    'user.html.twig' => '<span>{{ row.email }}:{{ data }}</span>',
                ]))),
            ),
        );

        $response = $controller(new Request(content: json_encode([
            'table' => $token,
            'rows'  => [
                [
                    'avatar' => 'https://example.test/avatar.png',
                    'email'  => 'user@example.com',
                ],
            ],
        ], \JSON_THROW_ON_ERROR)));

        $this->assertSame([
            'data' => [
                [
                    'avatar' => '<span>user@example.com:https://example.test/avatar.png</span>',
                    'email'  => 'user@example.com',
                ],
            ],
        ], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function it_resolves_detail_actions_from_rehydrated_api_rows(): void
    {
        $table    = new TemplateRenderActionDataTableFixture();
        $registry = $this->createRegistry($table);
        $token    = $registry->getToken(TemplateRenderActionDataTableFixture::class);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(7)
            ->willReturn(new TemplateRenderUserFixture(7));

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->once())
            ->method('getRepository')
            ->with(TemplateRenderUserFixture::class)
            ->willReturn($repository);

        $controller = new AjaxTemplateRenderController(
            $registry,
            new DataTableRuntimeFactory(actionRowDataResolver: new ActionRowDataResolver()),
            $doctrine,
        );

        $response = $controller(new Request(content: json_encode([
            'table' => $token,
            'rows'  => [
                [
                    'id'    => 7,
                    'email' => 'user@example.com',
                ],
            ],
        ], \JSON_THROW_ON_ERROR)));

        $this->assertSame('/users/7', json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data'][0]['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_resolves_url_columns_from_rehydrated_api_rows(): void
    {
        $table    = new TemplateRenderUrlDataTableFixture();
        $registry = $this->createRegistry($table);
        $token    = $registry->getToken(TemplateRenderUrlDataTableFixture::class);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('find')
            ->with(7)
            ->willReturn(new TemplateRenderUserFixture(7));

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getRepository')
            ->with(TemplateRenderUserFixture::class)
            ->willReturn($repository);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_user_show', ['id' => 7])
            ->willReturn('/users/7');

        $controller = new AjaxTemplateRenderController(
            $registry,
            new DataTableRuntimeFactory(urlColumnDataResolver: new UrlColumnDataResolver($urlGenerator)),
            $doctrine,
        );

        $response = $controller(new Request(content: json_encode([
            'table' => $token,
            'rows'  => [
                [
                    'id'      => 7,
                    'profile' => 'Show',
                ],
            ],
        ], \JSON_THROW_ON_ERROR)));

        $this->assertSame('/users/7', json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data'][0]['__ux_datatables_urls']['profile']);
    }

    #[Test]
    public function it_throws_404_when_table_token_is_unknown(): void
    {
        $controller = new AjaxTemplateRenderController(
            $this->createRegistry(),
            new DataTableRuntimeFactory(),
        );

        $this->expectException(NotFoundHttpException::class);

        $controller(new Request(content: '{"table":"unknown","rows":[]}'));
    }

    #[Test]
    public function it_throws_400_when_rows_are_missing(): void
    {
        $registry = $this->createRegistry(new TemplateRenderDataTableFixture());
        $token    = $registry->getToken(TemplateRenderDataTableFixture::class);

        $controller = new AjaxTemplateRenderController(
            $registry,
            new DataTableRuntimeFactory(),
        );

        $this->expectException(BadRequestHttpException::class);

        $controller(new Request(content: json_encode([
            'table' => $token,
        ], \JSON_THROW_ON_ERROR)));
    }

    private function createRegistry(?AbstractDataTable $table = null): AjaxDataTableRegistry
    {
        $services = [];
        $map      = [];

        if (null !== $table) {
            $services['app.template_render_datatable'] = $table;
            $map[$table::class]                        = 'app.template_render_datatable';
        }

        return new AjaxDataTableRegistry(
            new class($services) implements ContainerInterface {
                public function __construct(private readonly array $services)
                {
                }

                public function get(string $id): mixed
                {
                    return $this->services[$id];
                }

                public function has(string $id): bool
                {
                    return isset($this->services[$id]);
                }
            },
            new AjaxDataTableTokenManager('test-secret'),
            $map,
        );
    }
}

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
final class TemplateRenderActionDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('email', 'Email');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail(label: '', className: 'detail')
                ->linkToUrl(static fn (TemplateRenderUserFixture $user): string => '/users/'.$user->getId())
        );
    }
}

#[AsDataTable(entityClass: TemplateRenderUserFixture::class, apiPlatform: true)]
final class TemplateRenderUrlDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield UrlColumn::new('profile', 'Profile')
            ->linkToRoute(
                'app_user_show',
                static fn (TemplateRenderUserFixture $user): array => ['id' => $user->getId()]
            );
    }
}

final class TemplateRenderUserFixture
{
    public function __construct(private readonly int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
