<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Command;

use Pentiminax\UX\DataTables\Install\ExtensionDetector;
use Pentiminax\UX\DataTables\Install\InstallStyle;
use Pentiminax\UX\DataTables\Install\PackageCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\ServiceProviderInterface;

#[AsCommand(name: 'ux-datatables:install', description: 'Registers the DataTables packages your application loads and switches the bundle to registered package loading')]
final class InstallCommand extends Command
{
    private const PACKAGE         = '@pentiminax/ux-datatables';
    private const BOOTSTRAP_FILES = ['stimulus_bootstrap.js', 'bootstrap.js'];
    private const IMPORT_LINE     = "import './datatables.js';";
    private const NONE_CHOICE     = 'none (core only)';

    public function __construct(
        private readonly string $projectDir,
        private readonly ?ServiceProviderInterface $tables = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('extensions', InputArgument::IS_ARRAY, 'Extensions to install: '.implode(', ', PackageCatalog::selectable()))
            ->addOption('style', null, InputOption::VALUE_REQUIRED, 'Look: tailwind, dt or bs5', InstallStyle::Tailwind->value)
            ->addOption('all', null, InputOption::VALUE_NONE, 'Install every extension')
            ->addOption('no-detect', null, InputOption::VALUE_NONE, 'Do not scan your DataTable classes for the extensions they use')
            ->addOption('prune', null, InputOption::VALUE_NONE, 'AssetMapper only: remove the DataTables JavaScript entries the application does not register')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $style = InstallStyle::tryFrom((string) $input->getOption('style'));

        if (null === $style) {
            $io->error('Unknown style "'.$input->getOption('style').'". Valid styles: '.implode(', ', array_column(InstallStyle::cases(), 'value')).'.');

            return Command::INVALID;
        }

        $extensions = $this->selectExtensions($input, $io);

        if (null === $extensions) {
            return Command::INVALID;
        }

        $skipped    = [];
        $extensions = $this->withDetected($extensions, $input, $io, $skipped);

        if (null === $extensions) {
            $io->writeln('Nothing was written.');

            return Command::SUCCESS;
        }

        $controllersFile = $this->projectDir.'/assets/controllers.json';

        if (!is_file($controllersFile)) {
            $io->error('assets/controllers.json was not found. Install symfony/stimulus-bundle first.');

            return Command::FAILURE;
        }

        $packages = PackageCatalog::registered($extensions, $style);

        // controllers.json goes first: it is the only file that can be unreadable, so nothing is half-written.
        $this->updateControllers($controllersFile, $style);
        $this->write($this->projectDir.'/assets/datatables.js', PackageCatalog::render($packages, $style));
        $written = ['assets/controllers.json', 'assets/datatables.js'];

        $bootstrapImport = $this->addBootstrapImport();
        if (null !== $bootstrapImport) {
            $written[] = $bootstrapImport;
        }

        $config = $this->writePackageLoadingConfig();
        if (null === $config) {
            $io->warning('Could not edit the "data_tables" config automatically. Set "package_loading: registered" under "data_tables:" yourself.');
        } else {
            $written[] = $config;
        }

        $io->section('Files');
        $io->listing($written);

        if (null === $bootstrapImport && !$this->hasBootstrapImport()) {
            $io->warning('Neither assets/stimulus_bootstrap.js nor assets/bootstrap.js exists. Add this line before startStimulusApp(): '.self::IMPORT_LINE);
        }

        $specifiers  = $this->specifiers($packages, $style);
        $assetMapper = is_file($this->projectDir.'/importmap.php') && $this->getApplication()?->has('importmap:require');
        $failed      = false;

        if ($assetMapper) {
            $failed = !$this->requireImportmapPackages($specifiers, $style, $io, $output);
            if ($input->getOption('prune')) {
                $failed = !$this->pruneImportmap($specifiers, $io, $output) || $failed;
            }
        } else {
            $io->section('Packages');
            $io->writeln('Install the packages with your package manager, then rebuild the assets:');
            $io->writeln('  npm install '.implode(' ', $this->npmPackages($specifiers)));

            if ($input->getOption('prune')) {
                $io->note('--prune only applies to AssetMapper.');
            }
        }

        if ($failed) {
            $io->error('An importmap command failed, see its output above.');

            return Command::FAILURE;
        }

        if ([] !== $skipped) {
            $io->warning(\sprintf(
                'The files were written without the extensions of these tables: %s. Run the command again with their extensions as arguments, for example: ux-datatables:install buttons select.',
                implode(', ', $skipped),
            ));

            return Command::FAILURE;
        }

        $io->success('DataTables is set up with registered package loading ('.$style->value.' style).');
        $io->writeln('Next steps: clear the cache and reload a page that renders a table.');

        if ($assetMapper && !$input->getOption('prune')) {
            $io->writeln('Run again with --prune to remove the DataTables entries from importmap.php that your application does not register.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $selected
     * @param list<string> $skipped  receives the ids of the tables that could not be read
     *
     * @return list<string>|null null when the user declined the detected list and gave no extension
     */
    private function withDetected(array $selected, InputInterface $input, SymfonyStyle $io, array &$skipped): ?array
    {
        if ($input->getOption('all') || $input->getOption('no-detect') || null === $this->tables) {
            return $selected;
        }

        $detection = (new ExtensionDetector($this->tables))->detect();

        $skipped = array_keys($detection->failures);

        foreach ($detection->failures as $class => $message) {
            $io->warning(\sprintf('Could not read the extensions of "%s", skipping it: %s', $class, $message));
        }

        if ([] === $detection->extensions) {
            return $selected;
        }

        $io->section('Detected in your DataTable classes');
        $io->listing($detection->extensions);

        if ($input->isInteractive() && !$io->confirm('Install these extensions?')) {
            if ([] === $selected) {
                return null;
            }

            $io->writeln('Detected extensions skipped, installing only the ones you passed.');

            return $selected;
        }

        return array_values(array_unique([...$selected, ...$detection->extensions]));
    }

    /**
     * @return list<string>|null null when an extension name is unknown
     */
    private function selectExtensions(InputInterface $input, SymfonyStyle $io): ?array
    {
        $valid = PackageCatalog::selectable();

        if ($input->getOption('all')) {
            return $valid;
        }

        $requested = $input->getArgument('extensions');

        if ([] === $requested && $input->isInteractive()) {
            $question  = (new ChoiceQuestion('Extensions to install (comma-separated)', [self::NONE_CHOICE, ...$valid], 0))->setMultiselect(true);
            $requested = array_values(array_diff($io->askQuestion($question), [self::NONE_CHOICE]));
        }

        $byLowerName = array_combine(array_map('strtolower', $valid), $valid);
        $selected    = [];

        foreach ($requested as $name) {
            $canonical = $byLowerName[strtolower($name)] ?? null;

            if (null === $canonical) {
                $io->error(\sprintf('Unknown extension "%s". Valid extensions: %s.', $name, implode(', ', $valid)));

                return null;
            }

            $selected[] = $canonical;
        }

        return array_values(array_unique($selected));
    }

    private function hasBootstrapImport(): bool
    {
        foreach (self::BOOTSTRAP_FILES as $file) {
            $path = $this->projectDir.'/assets/'.$file;

            if (is_file($path) && 1 === preg_match('/import\s+[\'"]\.\/datatables\.js[\'"]/', (string) file_get_contents($path))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string|null the file changed, null when nothing was written
     */
    private function addBootstrapImport(): ?string
    {
        if ($this->hasBootstrapImport()) {
            return null;
        }

        foreach (self::BOOTSTRAP_FILES as $file) {
            $path = $this->projectDir.'/assets/'.$file;

            if (is_file($path)) {
                $this->write($path, self::IMPORT_LINE."\n".file_get_contents($path));

                return 'assets/'.$file;
            }
        }

        return null;
    }

    private function updateControllers(string $file, InstallStyle $style): void
    {
        $data = json_decode((string) file_get_contents($file), false, 512, \JSON_THROW_ON_ERROR);

        if (!$data instanceof \stdClass) {
            throw new \RuntimeException('The assets/controllers.json file must contain a JSON object.');
        }

        // The StimulusBundle recipe ships "controllers": [], which decodes to an array.
        $data->controllers = (object) ($data->controllers ?? []);
        $bundle            = $data->controllers->{self::PACKAGE} ??= new \stdClass();

        if (isset($bundle->datatable) && $bundle->datatable instanceof \stdClass) {
            $bundle->datatable->enabled = false;
        }

        $core          = $bundle->datatable_core ??= new \stdClass();
        $core->enabled = true;
        $core->fetch                    ??= 'eager';
        $autoimport = $core->autoimport ??= new \stdClass();

        foreach ($style->autoimport() as $path => $enabled) {
            $autoimport->{$path} = $enabled;
        }

        $this->write($file, json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * Line-based on purpose: symfony/yaml would drop the application's comments and formatting.
     *
     * @return string|null the file changed, null when the root key is written in a form this edit does not handle
     */
    private function writePackageLoadingConfig(): ?string
    {
        $dir     = $this->projectDir.'/config/packages';
        $default = $dir.'/data_tables.yaml';
        $files   = array_unique([$default, ...(glob($dir.'/*.yaml') ?: [])]);
        $target  = $default;

        foreach ($files as $file) {
            if (is_file($file) && 1 === preg_match('/^data_tables:/m', (string) file_get_contents($file))) {
                $target = $file;
                break;
            }
        }

        $relative = 'config/packages/'.basename($target);
        $source   = is_file($target) ? (string) file_get_contents($target) : '';
        $lines    = '' === $source ? [] : explode("\n", $source);
        $root     = null;

        foreach ($lines as $index => $line) {
            if (1 === preg_match('/^data_tables:/', $line)) {
                $root = $index;
                break;
            }
        }

        if (null === $root) {
            $prefix = '' === $source || str_ends_with($source, "\n") ? $source : $source."\n";
            $this->write($target, $prefix."data_tables:\n    package_loading: registered\n");

            return $relative;
        }

        if (1 !== preg_match('/^data_tables:\s*(?:(?:~|null|\{\})\s*)?(?:#.*)?$/', $lines[$root])) {
            return null;
        }

        $indent = null;
        $found  = null;

        for ($i = $root + 1; $i < \count($lines); ++$i) {
            if (1 === preg_match('/^\S/', $lines[$i])) {
                break;
            }

            if (1 !== preg_match('/^(\s+)(\S.*)$/', $lines[$i], $match) || str_starts_with($match[2], '#')) {
                continue;
            }

            $indent ??= $match[1];

            if (null === $found && str_starts_with($match[2], 'package_loading:') && $match[1] === $indent) {
                $found = $i;
            }
        }

        if (null !== $found) {
            $lines[$found] = preg_replace('/^(\s+package_loading:).*$/', '$1 registered', $lines[$found]);
        } else {
            $lines[$root] = 'data_tables:';
            array_splice($lines, $root + 1, 0, ($indent ?? '    ').'package_loading: registered');
        }

        $this->write($target, implode("\n", $lines));

        return $relative;
    }

    /**
     * @param list<string> $packages
     *
     * @return list<string>
     */
    private function specifiers(array $packages, InstallStyle $style): array
    {
        $specifiers = [];

        foreach ($packages as $package) {
            array_push($specifiers, ...PackageCatalog::specifiers($package, $style));
        }

        return array_values(array_unique($specifiers));
    }

    /**
     * @param list<string> $specifiers
     */
    private function requireImportmapPackages(array $specifiers, InstallStyle $style, SymfonyStyle $io, OutputInterface $output): bool
    {
        $mapped = array_keys($this->importmap());
        $wanted = $specifiers;

        // StimulusBundle resolves the bundle's own autoimport stylesheets without an importmap entry.
        foreach ($style->autoimport() as $key => $enabled) {
            if ($enabled && !str_starts_with($key, self::PACKAGE.'/')) {
                $wanted[] = $key;
            }
        }

        $io->section('Importmap');
        $ok = true;

        if (!\in_array(self::PACKAGE.'/dist/core.js', $mapped, true)) {
            $ok = $this->runImportmap('require', ['packages' => [self::PACKAGE.'/dist/core.js'], '--path' => './vendor/pentiminax/ux-datatables/assets/dist/core.js'], $output);
        }

        $versions = $this->versions();
        $packages = array_map(
            fn (string $specifier): string => $specifier.'@'.$this->versionOf($specifier, $versions),
            array_values(array_diff(array_unique($wanted), $mapped)),
        );

        if ([] !== $packages) {
            $ok = $this->runImportmap('require', ['packages' => $packages], $output) && $ok;
        }

        return $ok;
    }

    /**
     * @param list<string> $specifiers
     */
    private function pruneImportmap(array $specifiers, SymfonyStyle $io, OutputInterface $output): bool
    {
        $keep                   = array_flip($specifiers);
        $keep['datatables.net'] = true;

        foreach ($specifiers as $specifier) {
            // Each extension is built on its unsuffixed package, which AssetMapper pins as a dependency.
            $keep[preg_replace('/-(?:dt|bs5)$/', '', $specifier)] = true;
        }

        $remove = [];

        foreach ($this->importmap() as $key => $entry) {
            $isCss = 'css' === ($entry['type'] ?? null) || str_ends_with($key, '.css');

            // bootstrap and lucide are general-purpose: the application may import them itself.
            if (!$isCss && 1 === preg_match('#^(?:datatables\.net(?:-|$)|jszip$|pdfmake(?:/|$))#', $key) && !isset($keep[$key])) {
                $remove[] = $key;
            }
        }

        if ([] === $remove) {
            $io->writeln('Nothing to prune.');

            return true;
        }

        return $this->runImportmap('remove', ['packages' => $remove], $output);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function runImportmap(string $command, array $arguments, OutputInterface $output): bool
    {
        $input = new ArrayInput($arguments);
        $input->setInteractive(false);

        return Command::SUCCESS === $this->getApplication()->find('importmap:'.$command)->run($input, $output);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function importmap(): array
    {
        $file = $this->projectDir.'/importmap.php';
        $map  = is_file($file) ? (static fn () => include $file)() : [];

        return \is_array($map) ? $map : [];
    }

    /**
     * @param list<string> $specifiers
     *
     * @return list<string>
     */
    private function npmPackages(array $specifiers): array
    {
        $versions = $this->versions();
        $names    = array_unique(array_map($this->packageName(...), $specifiers));

        return array_values(array_map(fn (string $name): string => $name.'@'.$this->versionOf($name, $versions), $names));
    }

    private function packageName(string $specifier): string
    {
        return explode('/', $specifier)[0];
    }

    /**
     * @param array<string, string> $versions
     */
    private function versionOf(string $specifier, array $versions): string
    {
        $name = $this->packageName($specifier);

        if (!isset($versions[$name])) {
            throw new \RuntimeException(\sprintf('The bundle does not declare a version for "%s".', $name));
        }

        return ltrim($versions[$name], '^');
    }

    /**
     * @return array<string, string>
     */
    private function versions(): array
    {
        $manifest = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/assets/package.json'), true, 512, \JSON_THROW_ON_ERROR);

        return ($manifest['peerDependencies'] ?? []) + ($manifest['symfony']['importmap'] ?? []);
    }

    private function write(string $path, string $contents): void
    {
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0o775, true);
        }

        if (false === file_put_contents($path, $contents)) {
            throw new \RuntimeException(\sprintf('Unable to write "%s".', $path));
        }
    }
}
