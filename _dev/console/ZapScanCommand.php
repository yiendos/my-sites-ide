<?php

namespace Yiendos\MySitesIde;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\InteractsWithZapApi;

class ZapScanCommand extends Command
{
    use InteractsWithZapApi;

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
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        // The bootstrap only loads the root .env, so pull in zaproxy/.env here as the
        // default source for its own config - safeLoad() + Immutable won't overwrite
        // whatever the root .env already set, so root still wins on override.
        Dotenv::createImmutable(__DIR__ . '/../environment/security/zaproxy')->safeLoad();

        $target = $input->getArgument('target');
        $full = $input->getOption('full');
        $context = $input->getOption('context');
        $user = $input->getOption('user');

        if ($user !== null && $context === null) {
            $io->error('--user requires --context');
            return Command::FAILURE;
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

        if ($context !== null) {
            return $this->runApiDrivenScan($io, $output, $target, $context, $user, $siteName, $full);
        }

        return $this->runWrapperScriptScan($output, $target, $siteName, $full);
    }

    /**
     * The plain unauthenticated path - shells out to ZAP's own
     * zap-baseline.py/zap-full-scan.py wrapper scripts, which boot a fresh
     * throwaway ZAP instance per invocation and handle everything (spider,
     * scan, report, PASS/WARN/FAIL summary) internally. Fine for a single
     * target with no login - but only ever accepts one `-t`, so it's not
     * usable once seed_urls need spidering too (see runApiDrivenScan).
     */
    private function runWrapperScriptScan(OutputInterface $output, string $target, string $siteName, bool $full): int
    {
        $script = $full ? 'zap-full-scan.py' : 'zap-baseline.py';
        $report = "{$siteName}/report-" . date('Y-m-d_His') . '.html';

        $command = "docker compose run --rm zaproxy $script -t $target -r $report";

        $output->writeLn(["", $command]);
        passthru($command);
        $output->writeLn(["", "Report written to _dev/environment/security/zaproxy/reports/$report"]);

        return Command::SUCCESS;
    }

    /**
     * The authenticated path - drives ZAP directly via its API against the
     * reused ide:zap-daemon session, rather than the wrapper scripts (which
     * boot a fresh separate ZAP instance per run and only accept one `-t`,
     * so seed_urls could never reach them). Spiders the target plus every
     * seed_urls entry from the site's own <context>.zap-config.php, so pages
     * the crawler can't reach by link-following alone (JS-only routes, links
     * inside a collapsed nav dropdown) still get visited and scanned.
     */
    private function runApiDrivenScan(
        SymfonyStyle $io,
        OutputInterface $output,
        string $target,
        string $context,
        ?string $user,
        string $siteName,
        bool $full
    ): int {
        $contextFile = "{$context}.context";
        $hostContextFile = __DIR__ . "/../environment/security/zaproxy/reports/{$contextFile}";

        if (!is_file($hostContextFile)) {
            $io->error("No context file at reports/{$contextFile} - run `ide:zap-context {$context}` first");
            return Command::FAILURE;
        }

        $daemonExit = $this->getApplication()->find('ide:zap-daemon')->run(new ArrayInput([]), $output);

        if ($daemonExit !== Command::SUCCESS) {
            $io->error('Could not reach a ZAP daemon.');
            return Command::FAILURE;
        }

        $configPath = __DIR__ . "/../environment/security/zaproxy/contexts/{$context}.zap-config.php";
        $seedUrls = is_file($configPath) ? ((require $configPath)['seed_urls'] ?? []) : [];
        $seeds = array_values(array_unique(array_merge([$target], $seedUrls)));

        // A fresh session (rather than reusing whatever ide:zap-context or a
        // previous scan left behind) means contextId/userId are only ever
        // known by re-importing and re-querying them here, not assumed.
        $this->zapApi($io, 'core/action/newSession', ['name' => '', 'overwrite' => 'true']);
        $imported = $this->zapApi($io, 'context/action/importContext', ['contextFile' => "/zap/wrk/{$contextFile}"]);
        $contextId = $imported['contextId'] ?? null;

        if ($contextId === null) {
            $io->error('Failed to import context.');
            return Command::FAILURE;
        }

        $userId = $this->findUserId($io, $contextId, $user);

        if ($userId === null) {
            $io->error($user !== null
                ? "No user named '{$user}' found in context '{$context}'."
                : "Context '{$context}' has more than one user - pass --user to pick one.");
            return Command::FAILURE;
        }

        $io->writeln(count($seeds) . ' seed URL(s):');
        foreach ($seeds as $seed) {
            $io->writeln("  {$seed}");
        }

        $io->newLine();

        foreach ($seeds as $seed) {
            $io->writeln("Spidering {$seed}...");
            $scan = $this->zapApi($io, 'spider/action/scanAsUser', [
                'contextId' => $contextId,
                'userId' => $userId,
                'url' => $seed,
                'recurse' => 'true',
            ]);

            if (isset($scan['scanAsUser'])) {
                $this->pollUntilComplete($io, 'spider/view/status', ['scanId' => $scan['scanAsUser']], 180);
            } else {
                $io->warning("Spider did not return a scan id for {$seed} - not waited on.");
            }
        }

        $io->newLine();
        $io->writeln('Waiting for passive scan to finish...');
        $this->pollUntil(
            $io,
            'pscan/view/recordsToScan',
            [],
            fn ($r) => ($r['recordsToScan'] ?? '1') === '0',
            60,
            fn ($r) => ($r['recordsToScan'] ?? '?') . ' record(s) remaining'
        );

        if ($full) {
            $io->newLine();
            $io->writeln('Running active scan...');
            $ascan = $this->zapApi($io, 'ascan/action/scanAsUser', [
                'contextId' => $contextId,
                'userId' => $userId,
                'url' => $target,
                'recurse' => 'true',
            ]);

            if (isset($ascan['scanAsUser'])) {
                // Confirmed live against a real full scan (after capping DOM XSS
                // to LOW): spider+passive+active together spanned ~18 minutes -
                // the old 900s (15min) hardcoded timeout gave up on the active
                // scan alone at 83%, generating a report before the scan (which
                // keeps running server-side regardless) had actually finished.
                // 1800s as the default gives real headroom without waiting
                // forever on a genuinely hung scan.
                $ascanTimeout = (int) (getenv('ZAP_ASCAN_TIMEOUT_SECONDS') ?: 1800);
                $scanId = $ascan['scanAsUser'];

                // Bypasses pollUntilComplete (used as-is for spider, above) since
                // this is the only wait that needs a prefix showing which rule is
                // actually running - "40%" alone doesn't say what's taking so long.
                $this->pollUntil(
                    $io,
                    'ascan/view/status',
                    ['scanId' => $scanId],
                    fn ($r) => ($r['status'] ?? '0') === '100',
                    $ascanTimeout,
                    fn ($r) => ($r['status'] ?? '?') . '%',
                    fn () => $this->currentAscanPlugin($io, $scanId)
                );
            } else {
                $io->warning('Active scan did not return a scan id - not waited on.');
            }
        }

        $io->newLine();
        $reportName = "report-" . date('Y-m-d_His');
        $generated = $this->zapApi($io, 'reports/action/generate', [
            'title' => "ZAP scan - {$context}",
            // "-plus" embeds the actual captured request/response for every
            // alert instance, not just the description/solution text the
            // plain template gives - confirmed live, real response bodies
            // are genuinely present. Real cost: ~60x larger files (14.6MB
            // vs ~240KB for the same 1030-alert session, tested directly).
            'template' => 'traditional-html-plus',
            'reportDir' => "/zap/wrk/{$siteName}",
            'reportFileName' => $reportName,
        ]);

        if (!isset($generated['generate'])) {
            $io->error('Report generation failed.');
            return Command::FAILURE;
        }

        $io->success("Report written to _dev/environment/security/zaproxy/reports/{$siteName}/{$reportName}.html");

        return Command::SUCCESS;
    }

    /**
     * `users/view/usersList` returns duplicate-looking entries for the same
     * user in this ZAP version (verified: the underlying context file has
     * only one <user> line) - dedupe by id. Picks by name when given,
     * otherwise only auto-picks if the context has exactly one user.
     */
    private function findUserId(SymfonyStyle $io, string $contextId, ?string $user): ?string
    {
        $usersList = $this->zapApi($io, 'users/view/usersList', ['contextId' => $contextId]);
        $users = [];

        foreach ($usersList['usersList'] ?? [] as $entry) {
            $users[$entry['id']] = $entry; // dedupes by id
        }

        if ($user !== null) {
            foreach ($users as $entry) {
                if ($entry['name'] === $user) {
                    return $entry['id'];
                }
            }

            return null;
        }

        return count($users) === 1 ? array_key_first($users) : null;
    }

    /**
     * Poll a ZAP `view` endpoint until its `status` field reaches 100, or
     * the timeout elapses - used for spider/active-scan progress, both of
     * which report progress this way.
     */
    private function pollUntilComplete(SymfonyStyle $io, string $path, array $params, int $timeoutSeconds): void
    {
        $this->pollUntil(
            $io,
            $path,
            $params,
            fn ($r) => ($r['status'] ?? '0') === '100',
            $timeoutSeconds,
            fn ($r) => ($r['status'] ?? '?') . '%'
        );
    }

    /**
     * A silent multi-minute wait (active scan alone can run 30+ minutes) is
     * indistinguishable from a hang from the outside - a real one was
     * mistaken for a stall mid-session because of exactly this. Prints an
     * elapsed-time line (plus whatever $describe reports, e.g. a percentage
     * or a remaining-record count) every ~15s rather than nothing at all.
     *
     * @param callable(array<string, mixed>): bool $isDone
     * @param callable(array<string, mixed>): string $describe
     * @param callable(): string $prefix
     */
    private function pollUntil(SymfonyStyle $io, string $path, array $params, callable $isDone, int $timeoutSeconds, ?callable $describe = null, ?callable $prefix = null): void
    {
        $waited = 0;

        while (true) {
            $result = $this->zapApi($io, $path, $params);

            if ($isDone($result)) {
                return;
            }

            if ($waited >= $timeoutSeconds) {
                $io->warning("Timed out waiting on {$path} after " . $this->formatDuration($timeoutSeconds) . '.');
                return;
            }

            if ($waited > 0 && $waited % 15 === 0) {
                $status = $describe ? $describe($result) : '';
                $prefixText = $prefix ? $prefix() : '';
                $io->writeln("  {$prefixText}...still waiting (" . $this->formatDuration($waited) . ' elapsed' . ($status !== '' ? ", {$status}" : '') . ')');
            }

            sleep(3);
            $waited += 3;
        }
    }

    /**
     * "40%" alone doesn't say what's actually taking so long - a stuck rule
     * looks identical to a merely slow one from the outside (this is exactly
     * how the DOM XSS bottleneck went unnoticed until someone dug into
     * scanProgress by hand). ascan/view/scanProgress has no single "current
     * rule" field, only a full per-rule breakdown, and no literal "Running"
     * status either - confirmed live (not assumed) that an in-progress rule's
     * status is a bare percentage string like "45%", distinct from
     * "Complete", "Pending", or an OAST-skip message. Returns '' (not found,
     * or between rules) rather than a stale/wrong name.
     */
    private function currentAscanPlugin(SymfonyStyle $io, string $scanId): string
    {
        $progress = $this->zapApi($io, 'ascan/view/scanProgress', ['scanId' => $scanId]);
        $hostProcesses = $progress['scanProgress'][1]['HostProcess'] ?? [];

        foreach ($hostProcesses as $entry) {
            $plugin = $entry['Plugin'] ?? [];

            if (preg_match('/^\d+%$/', $plugin[3] ?? '')) {
                return $plugin[0];
            }
        }

        return '';
    }

    /**
     * A full active scan can run for tens of minutes - "885s elapsed" takes a
     * moment to parse mid-scroll, "14m 45s" doesn't.
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        return sprintf('%dm %ds', intdiv($seconds, 60), $seconds % 60);
    }
}
