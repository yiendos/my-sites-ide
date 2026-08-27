<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapScanCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:zap-scan')
            ->setDescription('Run an OWASP ZAP scan against a local site')
            ->addArgument('target', InputArgument::REQUIRED, 'The hostname or URL to scan, e.g. https://nginx')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Run a full active scan instead of a passive baseline scan')
        ;
    }
    /**
     * Invoke vs execute because you cannot Dependancy Inject the requirements because of the command concrete cast
     * Same same, same but different ensure that we execute the user input
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output,InputInterface $input, SymfonyStyle $io): int
    {
        $target = $input->getArgument('target');
        $full = $input->getOption('full');

        $script = $full ? 'zap-full-scan.py' : 'zap-baseline.py';
        $report = 'report-' . date('Y-m-d_His') . '.html';

        $output->writeLn([
            "",
            "docker compose run --rm zaproxy $script -t $target -r $report"
        ]);

        passthru("docker compose run --rm zaproxy $script -t $target -r $report");

        $output->writeLn([
            "",
            "Report written to _dev/environment/security/zaproxy/reports/$report"
        ]);

        return Command::SUCCESS;
    }
}
