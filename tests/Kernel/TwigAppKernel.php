<?php

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Pentiminax\UX\DataTables\DataTablesBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;

class TwigAppKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new StimulusBundle(), new DataTablesBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', ['secret' => '$ecret', 'test' => true, 'http_method_override' => false]);
            $container->loadFromExtension('twig', ['default_path' => __DIR__.'/templates', 'strict_variables' => true, 'exception_controller' => null]);

            $container->setAlias('test.datatables.builder', 'datatables.builder')->setPublic(true);
            $container->setAlias('test.datatables.twig_extension', 'datatables.twig_extension')->setPublic(true);
        });
    }
}
