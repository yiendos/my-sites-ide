<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Plugins\Discover;

class PluginEnvCommand extends Command
{
    /**
     * A line setting a variable, commented out or not - e.g. "#ZAP_CPUS=4"
     */
    private const KEY = '/^\s*#?\s*([A-Z][A-Z0-9_]*)=/';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:plugin-env')
            ->setDescription("Copy a plugin's configuration options into the root .env, commented out")
            ->addArgument('package', InputArgument::REQUIRED, 'The plugin package, e.g. yiendos/my-sites-ide-security-zaproxy')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be appended without changing .env')
        ;
    }

    /**
     * Appends each variable from the plugin's env-example that .env doesn't
     * already mention - set or commented out - along with the comment that
     * describes it. Values are always appended commented out, and existing
     * lines are never touched, so the plugin's own .env defaults still apply
     * until the user uncomments something.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $package = $input->getArgument('package');
        $plugins = Discover::load();

        if (!isset($plugins[$package])) {
            $io->error("{$package} is not an installed plugin - see ide:plugin-list.");
            return Command::FAILURE;
        }

        $example = $plugins[$package]['env-example'];

        if ($example === null || !file_exists(Ide::path($example))) {
            $io->writeln("{$package} has no configuration options to copy.");
            return Command::SUCCESS;
        }

        $envPath = Ide::path('.env');
        $current = (string) file_get_contents($envPath);

        preg_match_all(self::KEY . 'm', $current, $matches);
        $known = array_flip($matches[1]);
        $blocks = [];

        foreach ($this->blocks((string) file_get_contents(Ide::path($example))) as $block) {
            $missing = array_diff_key($block['keys'], $known);

            if ($missing !== []) {
                $commented = array_map(fn (string $line): string => str_starts_with(ltrim($line), '#') ? $line : "#{$line}", $missing);
                $blocks[] = implode("\n", [...$block['comments'], ...$commented]);
            }
        }

        if ($blocks === []) {
            $io->writeln(".env already mentions every option {$package} offers - nothing to add.");
            return Command::SUCCESS;
        }

        $append = "\n# [{$package}] - added by ide:plugin-env, uncomment to override the plugin's defaults\n"
            . implode("\n\n", $blocks) . "\n";

        if ($input->getOption('dry-run')) {
            $output->writeln($append);
            return Command::SUCCESS;
        }

        file_put_contents($envPath, rtrim($current, "\n") . "\n" . $append);

        $io->success('Appended ' . count($blocks) . " block(s) of {$package} options to .env, commented out.");

        return Command::SUCCESS;
    }

    /**
     * Splits an env-example into blocks: a run of comment lines followed by
     * the variable lines it describes
     *
     * @param string $example
     * @return array<int, array{comments: array<int, string>, keys: array<string, string>}>
     */
    private function blocks(string $example): array
    {
        $blocks = [];
        $block = ['comments' => [], 'keys' => []];

        foreach (explode("\n", $example) as $line) {
            if (preg_match(self::KEY, $line, $key)) {
                $block['keys'][$key[1]] = $line;
                continue;
            }

            if ($block['keys'] !== []) {
                $blocks[] = $block;
                $block = ['comments' => [], 'keys' => []];
            }

            if (trim($line) !== '') {
                $block['comments'][] = $line;
            }
        }

        $blocks[] = $block;

        return $blocks;
    }
}
