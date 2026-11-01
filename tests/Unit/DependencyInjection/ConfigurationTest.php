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
        yield 'non-integer page length' => [['options' => ['pageLength' => 'many']], 'pageLength'];
    }

    #[Test]
    public function it_deprecates_a_page_length_outside_the_menu_and_keeps_the_options(): void
    {
        $deprecations = $this->collectDeprecations(fn () => $this->process(['options' => ['pageLength' => 20, 'lengthMenu' => [10, 25, 50]]]), $result);
        $options      = $result['options'];

        self::assertSame(20, $options['pageLength']);
        self::assertSame([10, 25, 50], $options['lengthMenu']);
        self::assertCount(1, $deprecations);
        self::assertStringContainsString('throws in 2.0', $deprecations[0]);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('deprecatedConfigs')]
    public function it_deprecates_unknown_extension_values_and_keeps_them(array $config, string $value): void
    {
        $deprecations = $this->collectDeprecations(fn () => $this->process($config), $result);

        self::assertCount(1, $deprecations);
        self::assertStringContainsString($value, $deprecations[0]);
        self::assertSame($config['extensions'], array_intersect_key($result['extensions'], $config['extensions']));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function deprecatedConfigs(): iterable
    {
        yield 'unknown select style' => [['extensions' => ['select' => ['style' => 'bogus']]], "'bogus'"];
        yield 'unknown button' => [['extensions' => ['buttons' => ['csv', 'bogus']]], "'bogus'"];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('silentConfigs')]
    public function it_does_not_deprecate_consistent_configuration(array $config): void
    {
        self::assertSame([], $this->collectDeprecations(fn () => $this->process($config)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function silentConfigs(): iterable
    {
        yield 'valid config' => [['options' => ['pageLength' => 25, 'lengthMenu' => [10, 25]], 'extensions' => ['buttons' => ['csv'], 'select' => ['style' => 'multi']]]];
        yield 'empty length menu' => [['options' => ['pageLength' => 30, 'lengthMenu' => []]]];
        yield 'empty config' => [[]];
    }

    #[Test]
    public function it_does_not_deprecate_env_placeholders_in_extensions(): void
    {
        $bag    = (new ContainerBuilder())->getParameterBag();
        $style  = $bag->get('env(SELECT_STYLE)');
        $button = $bag->get('env(BUTTON)');
        $config = ['extensions' => ['select' => ['style' => $style], 'buttons' => ['csv', $button]]];

        self::assertSame([], $this->collectDeprecations(fn () => $this->process($config)));
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
     * @param array<string, mixed>|null $result
     *
     * @return list<string>
     */
    private function collectDeprecations(callable $run, ?array &$result = null): array
    {
        $deprecations = [];

        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $result = $run();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
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
