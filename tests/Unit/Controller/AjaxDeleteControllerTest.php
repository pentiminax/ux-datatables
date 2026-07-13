<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Controller\AjaxDeleteController;
use Pentiminax\UX\DataTables\Dto\AjaxDeleteRequestDto;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @internal
 */
#[CoversClass(AjaxDeleteController::class)]
final class AjaxDeleteControllerTest extends TestCase
{
    #[Test]
    public function it_removes_the_entity_and_returns_success(): void
    {
        $entity = new DeletableEntityFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(12)->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(DeletableEntityFixture::class)->willReturn($repository);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(DeletableEntityFixture::class)->willReturn($manager);

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/server/deletable-entity-fixtures/{id}'], ['type' => 'delete', 'id' => 12]);

        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->method('resolveMercureConfig')
            ->with(DeletableEntityFixture::class)
            ->willReturn(new MercureConfig(
                topics: ['/server/deletable-entity-fixtures/{id}'],
                hubUrl: 'https://hub.example/.well-known/mercure',
            ));

        $mutator    = new EntityMutator(new EntityLocator($registry), $this->createMock(PropertyAccessorInterface::class), $publisher, mercureConfigResolver: $resolver);
        $controller = new AjaxDeleteController($mutator);

        $response = $controller(new AjaxDeleteRequestDto(
            entity: DeletableEntityFixture::class,
            id: 12,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['success']);
    }

    #[Test]
    public function it_lets_a_missing_entity_bubble_as_an_exception(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(404)->willReturn(null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $mutator    = new EntityMutator(new EntityLocator($registry), $this->createMock(PropertyAccessorInterface::class), new NullMercurePublisher());
        $controller = new AjaxDeleteController($mutator);

        $this->expectException(EntityNotFoundException::class);
        $controller(new AjaxDeleteRequestDto(entity: DeletableEntityFixture::class, id: 404));
    }
}

final class DeletableEntityFixture
{
}
