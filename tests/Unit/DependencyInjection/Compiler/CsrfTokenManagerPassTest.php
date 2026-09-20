<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection\Compiler;

use Pentiminax\UX\DataTables\DependencyInjection\Compiler\CsrfTokenManagerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * @internal
 */
#[CoversClass(CsrfTokenManagerPass::class)]
final class CsrfTokenManagerPassTest extends TestCase
{
    #[Test]
    public function it_prefers_the_application_csrf_token_manager_when_one_is_configured(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(CsrfTokenManagerPass::MANAGER_ID, new Definition(CsrfTokenManager::class));
        $container->setDefinition('security.csrf.token_manager', new Definition(CsrfTokenManager::class));

        (new CsrfTokenManagerPass())->process($container);

        $this->assertFalse($container->hasDefinition(CsrfTokenManagerPass::MANAGER_ID));
        $this->assertTrue($container->hasAlias(CsrfTokenManagerPass::MANAGER_ID));
        $this->assertSame(
            'security.csrf.token_manager',
            (string) $container->getAlias(CsrfTokenManagerPass::MANAGER_ID),
        );
    }

    #[Test]
    public function it_keeps_the_bundle_default_manager_when_the_application_has_none(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(CsrfTokenManagerPass::MANAGER_ID, new Definition(CsrfTokenManager::class));

        (new CsrfTokenManagerPass())->process($container);

        $this->assertTrue($container->hasDefinition(CsrfTokenManagerPass::MANAGER_ID));
        $this->assertFalse($container->hasAlias(CsrfTokenManagerPass::MANAGER_ID));
    }
}
