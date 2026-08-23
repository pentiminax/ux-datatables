<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormSubmitController;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @internal
 */
#[CoversClass(AjaxEditFormSubmitController::class)]
final class AjaxEditFormSubmitControllerTest extends TestCase
{
    private const string TOKEN_SECRET = 'test-secret';

    #[Test]
    #[TestWith([true, null], 'valid form flushes and reports success')]
    #[TestWith([false, '<form>invalid</form>'], 'invalid form returns the rendered body')]
    public function it_flushes_a_valid_form_and_renders_an_invalid_one(bool $valid, ?string $invalidHtml): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new AjaxEditFormSubmitControllerFixture());
        $entityManager->expects($valid ? $this->once() : $this->never())->method('flush');

        $form = $this->createFormMock($valid);

        if ($valid) {
            $form->expects($this->never())->method('createView');
        }

        [$formFactory, $renderer, $templateResolver] = $this->createFormCollaborators($form, $invalidHtml);

        $controller = $this->controller(new EditFormService(
            new EntityLocator($this->createRegistry($entityManager)),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            dataTables: $this->registeredDataTables(),
            permissionChecker: $this->permissionCheckerGranting(true),
        ));

        $response = $controller($this->validTokenRequest(), $this->payload());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($valid, $payload['success']);

        if (null !== $invalidHtml) {
            $this->assertSame($invalidHtml, $payload['html']);
        }
    }

    #[Test]
    public function it_returns_forbidden_when_edit_is_not_granted(): void
    {
        $entity     = new AjaxEditFormSubmitControllerFixture();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('find')->with(42)->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->never())->method('getClassMetadata');
        $entityManager->expects($this->never())->method('flush');

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('renderBody');
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->with('EDIT', $entity)->willReturn(false);

        $controller = $this->controller(new EditFormService(
            new EntityLocator($this->createRegistry($entityManager)),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            permissionChecker: new PermissionChecker($authorizationChecker),
        ));

        $response = $controller($this->validTokenRequest(), $this->payload());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('You are not allowed to perform this action.', $payload['message']);
    }

    /**
     * The DTO does not carry a topics field, so the client cannot influence the publish
     * target: only the server-resolved topic is ever used, and nothing is published when
     * the server resolves no configuration.
     *
     * @param list<string>|null $serverTopics
     */
    #[Test]
    #[TestWith([['/server/topic/42']], 'publishes the server resolved topics')]
    #[TestWith([null], 'publishes nothing without a resolved config')]
    public function it_publishes_only_to_the_server_resolved_topics(?array $serverTopics): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new AjaxEditFormSubmitControllerFixture());
        $entityManager->expects($this->once())->method('flush');

        $hub = $this->createMock(HubInterface::class);

        if (null === $serverTopics) {
            $hub->expects($this->never())->method('publish');
        } else {
            $hub->expects($this->once())
                ->method('publish')
                ->with($this->callback(static function (Update $update) use ($serverTopics) {
                    return $serverTopics             === $update->getTopics()
                        && '{"type":"edit","id":42}' === $update->getData();
                }))
                ->willReturn('urn:uuid:published');
        }

        [$formFactory, $renderer, $templateResolver] = $this->createFormCollaborators($this->createFormMock(true));

        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->method('resolveMercureConfig')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn(null === $serverTopics ? null : new MercureConfig(
                topics: $serverTopics,
                hubUrl: 'https://hub.example/.well-known/mercure',
            ));

        $controller = $this->controller(new EditFormService(
            new EntityLocator($this->createRegistry($entityManager)),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new MercureUpdatePublisher($hub),
            $resolver,
            $this->registeredDataTables(),
            $this->permissionCheckerGranting(true),
        ));

        $response = $controller($this->validTokenRequest(), $this->payload());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertTrue($payload['success']);
    }

    /**
     * The submit route writes and flushes, so it must be CSRF-protected exactly like
     * the delete and toggle routes: without the header, nothing is ever looked up.
     */
    #[Test]
    public function it_rejects_a_request_without_a_csrf_token_before_submitting_the_form(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('renderBody');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $controller = $this->controller(
            new EditFormService(
                new EntityLocator($registry),
                new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
                $renderer,
                $this->createMock(EditModalTemplateResolverInterface::class),
                new NullMercurePublisher(),
                permissionChecker: $this->permissionCheckerGranting(true),
            ),
            $csrfTokenManager,
        );

        try {
            $controller(new Request(), $this->payload());
            $this->fail('Expected an InvalidCsrfTokenException.');
        } catch (InvalidCsrfTokenException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function controller(EditFormService $service, ?CsrfTokenManagerInterface $csrfTokenManager = null): AjaxEditFormSubmitController
    {
        if (null === $csrfTokenManager) {
            $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
            $csrfTokenManager->method('isTokenValid')->willReturn(true);
        }

        return new AjaxEditFormSubmitController(
            $service,
            new MutationTokenValidator($csrfTokenManager),
            $this->tableRegistry(),
        );
    }

    private function permissionCheckerGranting(bool $granted): PermissionChecker
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        return new PermissionChecker($authorizationChecker);
    }

    private function tableRegistry(): AjaxDataTableRegistry
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('submit_table')->willReturn(new AjaxEditFormSubmitControllerDataTable());

        return new AjaxDataTableRegistry(
            $locator,
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [AjaxEditFormSubmitControllerDataTable::class => 'submit_table'],
        );
    }

    private function validTokenRequest(): Request
    {
        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, 'valid-token');

        return $request;
    }

    private function payload(): AjaxEditFormRequestDto
    {
        $token = $this->tableRegistry()->getActionToken(AjaxEditFormSubmitControllerDataTable::class);

        $this->assertNotNull($token);

        return new AjaxEditFormRequestDto(
            dataTable: $token,
            id: 42,
            formData: ['name' => 'Alice'],
        );
    }

    private function registeredDataTables(): ContainerInterface
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(AjaxEditFormSubmitControllerDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(AjaxEditFormSubmitControllerDataTable::class)->willReturn(new AjaxEditFormSubmitControllerDataTable());

        return $dataTables;
    }

    private function createRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn($entityManager);

        return $registry;
    }

    private function createEntityManagerWithEntity(object $entity): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($entity);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn($classMetadata);

        return $entityManager;
    }

    /**
     * @param string|null $invalidHtml the body rendered for an invalid form, or null when the form is expected to be valid
     *
     * @return array{FormFactoryInterface, EditModalRenderer, EditModalTemplateResolverInterface}
     */
    private function createFormCollaborators(FormInterface $form, ?string $invalidHtml = null): array
    {
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder->expects($this->once())
            ->method('add')
            ->with('name', $this->isType('string'), $this->isType('array'))
            ->willReturnSelf();
        $formBuilder->expects($this->once())
            ->method('getForm')
            ->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())
            ->method('createBuilder')
            ->with($this->isType('string'), $this->isType('object'))
            ->willReturn($formBuilder);

        $renderer         = $this->createMock(EditModalRenderer::class);
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->once())
            ->method('resolveColumns')
            ->with(AjaxEditFormSubmitControllerDataTable::class)
            ->willReturn([TextColumn::new('name', 'Name')]);

        if (null === $invalidHtml) {
            $renderer->expects($this->never())->method('render');
            $renderer->expects($this->never())->method('renderBody');
            $templateResolver->expects($this->never())->method('resolveChromeTemplate');
            $templateResolver->expects($this->never())->method('resolveBodyTemplate');

            return [$formFactory, $renderer, $templateResolver];
        }

        $renderer->expects($this->once())->method('renderBody')->with($this->isType('object'))->willReturn($invalidHtml);
        $templateResolver->expects($this->once())
            ->method('resolveChromeTemplate')
            ->with(AjaxEditFormSubmitControllerDataTable::class)
            ->willReturn('modal.html.twig');
        $templateResolver->expects($this->once())
            ->method('resolveBodyTemplate')
            ->willReturn('body.html.twig');

        return [$formFactory, $renderer, $templateResolver];
    }

    private function createFormMock(bool $valid): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('submit')
            ->with(['name' => 'Alice']);
        $form->expects($this->once())
            ->method('isValid')
            ->willReturn($valid);

        return $form;
    }
}

final class AjaxEditFormSubmitControllerFixture
{
}

#[AsDataTable(entityClass: AjaxEditFormSubmitControllerFixture::class)]
final class AjaxEditFormSubmitControllerDataTable extends AbstractDataTable
{
    public function __construct()
    {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault());
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
