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
            ->addOption('context', null, InputOption::VALUE_REQUIRED, 'Name matching a context exported by ide:zap-context (reports/<context>.context), for an authenticated scan')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'ZAP user name (as set in the context) to authenticate as - requires --context')
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
        $context = $input->getOption('context');
        $user = $input->getOption('user');

        if ($user !== null && $context === null) {
            $io->error('--user requires --context');
            return Command::FAILURE;
        }

        $authArgs = '';

        if ($context !== null) {
            $contextFile = __DIR__ . "/../environment/security/zaproxy/reports/{$context}.context";

            if (!is_file($contextFile)) {
                $io->error("No context file at reports/{$context}.context - run `ide:zap-context {$context}` first");
                return Command::FAILURE;
            }

            $authArgs = ' -n ' . escapeshellarg("{$context}.context");

            if ($user !== null) {
                $authArgs .= ' -U ' . escapeshellarg($user);
            }
        }

        // Group reports by site so `reports/` doesn't become one flat pile across
        // every target ever scanned. Prefer the context name (matches the naming
        // already used for <context>.context / <context>.zap-config.php); fall
        // back to the target's hostname for an unauthenticated one-off scan.
        $siteName = $context ?? preg_replace('/[^a-zA-Z0-9.\-]/', '_', parse_url($target, PHP_URL_HOST) ?: $target);
        $siteDir = __DIR__ . "/../environment/security/zaproxy/reports/{$siteName}";

        if (!is_dir($siteDir) && !mkdir($siteDir, 0755, true) && !is_dir($siteDir)) {
            $io->error("Failed to create reports/{$siteName}");
            return Command::FAILURE;
        }

        $script = $full ? 'zap-full-scan.py' : 'zap-baseline.py';
        $report = "{$siteName}/report-" . date('Y-m-d_His') . '.html';

        $command = "docker compose run --rm zaproxy $script -t $target -r $report$authArgs";

        $output->writeLn([
            "",
            $command
        ]);

        passthru($command);

        $output->writeLn([
            "",
            "Report written to _dev/environment/security/zaproxy/reports/$report"
        ]);

        return Command::SUCCESS;
    }
}
