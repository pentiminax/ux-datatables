<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
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
use Pentiminax\UX\DataTables\Rehydration\RowIdentifierExtractor;
use Pentiminax\UX\DataTables\Rehydration\SourceRowResolver;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(
                templateColumnRenderer: new TemplateColumnRenderer(new Environment(new ArrayLoader([
                    'user.html.twig' => '<span>{{ row.email }}:{{ data }}</span>',
                ]))),
            ),
        );

        $response = $controller($this->createRequest($registry->getToken(TemplateRenderDataTableFixture::class), [
            'avatar' => 'https://example.test/avatar.png',
            'email'  => 'user@example.com',
        ]));

        $this->assertSame([
            [
                'avatar' => '<span>user@example.com:https://example.test/avatar.png</span>',
                'email'  => 'user@example.com',
            ],
        ], $this->decodeData($response));
    }

    #[Test]
    public function it_resolves_detail_actions_from_rehydrated_api_rows(): void
    {
        $registry = $this->createRegistry(new TemplateRenderActionDataTableFixture());

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(actionRowDataResolver: new ActionRowDataResolver()),
            $this->createDoctrine(new TemplateRenderUserFixture(7)),
        );

        $response = $controller($this->createRequest($registry->getToken(TemplateRenderActionDataTableFixture::class), [
            'id'    => 7,
            'email' => 'user@example.com',
        ]));

        $this->assertSame('/users/7', $this->decodeData($response)[0]['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_resolves_url_columns_from_rehydrated_api_rows(): void
    {
        $registry = $this->createRegistry(new TemplateRenderUrlDataTableFixture());

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_user_show', ['id' => 7])
            ->willReturn('/users/7');

        $controller = $this->createController(
            $registry,
            new DataTableRuntimeFactory(urlColumnDataResolver: new UrlColumnDataResolver($urlGenerator)),
            $this->createDoctrine(new TemplateRenderUserFixture(7)),
        );

        $response = $controller($this->createRequest($registry->getToken(TemplateRenderUrlDataTableFixture::class), [
            'id'      => 7,
            'profile' => 'Show',
        ]));

        $this->assertSame('/users/7', $this->decodeData($response)[0]['__ux_datatables_urls']['profile']);
    }

    #[Test]
    public function it_throws_404_when_table_token_is_unknown(): void
    {
        $controller = $this->createController($this->createRegistry(), new DataTableRuntimeFactory());

        $this->expectException(NotFoundHttpException::class);

        $controller(new Request(content: '{"table":"unknown","rows":[]}'));
    }

    #[Test]
    public function it_throws_400_when_rows_are_missing(): void
    {
        $registry   = $this->createRegistry(new TemplateRenderDataTableFixture());
        $controller = $this->createController($registry, new DataTableRuntimeFactory());

        $this->expectException(BadRequestHttpException::class);

        $controller(new Request(content: json_encode([
            'table' => $registry->getToken(TemplateRenderDataTableFixture::class),
        ], \JSON_THROW_ON_ERROR)));
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

    private function createController(
        AjaxDataTableRegistry $registry,
        DataTableRuntimeFactory $runtimeFactory,
        ?ManagerRegistry $doctrine = null,
    ): AjaxTemplateRenderController {
        return new AjaxTemplateRenderController(
            $registry,
            $runtimeFactory,
            new SourceRowResolver(new RowIdentifierExtractor(), $doctrine),
        );
    }

    /**
     * Doctrine wiring rehydrating $user exactly once from the identifiers of the posted rows.
     */
    private function createDoctrine(TemplateRenderUserFixture $user): ManagerRegistry
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['id' => [$user->getId()]])
            ->willReturn([$user]);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('getIdentifierValues')->with($user)->willReturn(['id' => $user->getId()]);

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getRepository')->with(TemplateRenderUserFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->with(TemplateRenderUserFixture::class)->willReturn($metadata);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->with(TemplateRenderUserFixture::class)
            ->willReturn($manager);

        return $doctrine;
    }

    /**
     * @param array<string, mixed> ...$rows
     */
    private function createRequest(?string $token, array ...$rows): Request
    {
        return new Request(content: json_encode([
            'table' => $token,
            'rows'  => $rows,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeData(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data'];
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
