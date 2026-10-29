<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection;

use Pentiminax\UX\DataTables\PentiminaxDataTablesBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(PentiminaxDataTablesBundle::class)]
final class ConfigurationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('invalidConfigs')]
    public function it_rejects_invalid_configuration(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->process($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'unknown select style' => [['extensions' => ['select' => ['style' => 'bogus']]], 'bogus'];
        yield 'unknown button' => [['extensions' => ['buttons' => ['bogus']]], 'bogus'];
        yield 'page length outside the menu' => [['options' => ['pageLength' => 30, 'lengthMenu' => [10, 25, 50]]], '"pageLength" must be one of'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('validOptions')]
    public function it_accepts_consistent_options(array $options): void
    {
        self::assertSame($options['pageLength'], $this->process(['options' => $options])['options']['pageLength']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function validOptions(): iterable
    {
        yield 'page length in the menu' => [['pageLength' => 25, 'lengthMenu' => [10, 25, 50]]];
        yield 'show all' => [['pageLength' => -1, 'lengthMenu' => [10, -1]]];
        yield 'no menu' => [['pageLength' => 30]];
    }

    /**
     * Placeholders are only resolved after the tree is processed, so a bare Processor cannot
     * reach them; the validation predicate is exercised directly.
     */
    #[Test]
    public function it_skips_the_page_length_check_for_env_placeholders(): void
    {
        $check = new \ReflectionMethod(PentiminaxDataTablesBundle::class, 'pageLengthMissingFromMenu');

        self::assertFalse($check->invoke(null, ['pageLength' => '%env(int:PAGE_LENGTH)%', 'lengthMenu' => [10, 25]]));
        self::assertFalse($check->invoke(null, ['pageLength' => 30, 'lengthMenu' => '%env(json:LENGTH_MENU)%']));
        self::assertTrue($check->invoke(null, ['pageLength' => 30, 'lengthMenu' => [10, 25]]));
    }

    #[Test]
    public function it_keeps_enum_values_as_strings(): void
    {
        $config = $this->process(['extensions' => ['select' => ['style' => 'multi+shift'], 'buttons' => ['csv']]]);

        self::assertSame('multi+shift', $config['extensions']['select']['style']);
        self::assertSame(['csv'], $config['extensions']['buttons']);
    }

    #[Test]
    public function it_documents_every_node(): void
    {
        $undocumented = [];
        $this->collectUndocumented($this->tree(), '', $undocumented);

        self::assertSame([], $undocumented);
    }

    /**
     * The table-purpose check on Ajax routes must stay unconditional: no node may exist that
     * could be mistaken for a switch for it.
     */
    #[Test]
    public function it_exposes_no_switch_for_the_security_checks(): void
    {
        $names = [];
        $this->collectNames($this->tree(), $names);

        self::assertSame([], preg_grep('/purpose|csrf|token|security/i', $names));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->process($this->tree(), [$config]);
    }

    private function tree(): NodeInterface
    {
        $extension = (new PentiminaxDataTablesBundle())->getContainerExtension();

        return $extension->getConfiguration([], new ContainerBuilder())->getConfigTreeBuilder()->buildTree();
    }

    /**
     * @param list<string> $undocumented
     */
    private function collectUndocumented(NodeInterface $node, string $path, array &$undocumented): void
    {
        foreach ($node instanceof ArrayNode ? $node->getChildren() : [] as $name => $child) {
            if (null === $child->getInfo() || '' === $child->getInfo()) {
                $undocumented[] = $path.$name;
            }

            $this->collectUndocumented($child, $path.$name.'.', $undocumented);
        }
    }

    /**
     * @param list<string> $names
     */
    private function collectNames(NodeInterface $node, array &$names): void
    {
        foreach ($node instanceof ArrayNode ? $node->getChildren() : [] as $name => $child) {
            $names[] = (string) $name;
            $this->collectNames($child, $names);
        }
    }
}
