<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Plugins\Discover;

class PluginSearchCommand extends Command
{
    private const SEARCH = 'https://packagist.org/search.json';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:plugin-search')
            ->setDescription('Search Packagist for my-sites-ide plugins')
            ->addArgument('query', InputArgument::OPTIONAL, 'Narrow the search, e.g. nginx', '')
        ;
    }

    /**
     * Packagist filters by Composer package type, so every result is a plugin
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $url = self::SEARCH . '?' . http_build_query([
            'q' => $input->getArgument('query'),
            'type' => Discover::TYPE,
            'per_page' => 100,
        ]);

        $response = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10]]));
        $results = json_decode((string) $response, true)['results'] ?? null;

        if ($results === null) {
            $io->error('Could not reach Packagist - check your connection and try again.');
            return Command::FAILURE;
        }

        if ($results === []) {
            $io->writeln('No plugins found.');
            return Command::SUCCESS;
        }

        $installed = Discover::load();
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                $result['name'],
                isset($installed[$result['name']]) ? 'yes' : '',
                $result['description'] ?? '',
                $result['downloads'] ?? 0,
            ];
        }

        $io->table(['Package', 'Installed', 'Description', 'Downloads'], $rows);
        $io->writeln('Install one by adding it to the require section of composer.local.json, then running composer update.');

        return Command::SUCCESS;
    }
}
