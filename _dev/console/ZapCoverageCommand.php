<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapCoverageCommand extends Command
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
            ->setName('ide:zap-coverage')
            ->setDescription('Diff a target\'s write_routes manifest against what the current ZAP session actually recorded, e.g. after a Phase 5 HUD walkthrough')
            ->addArgument('target', InputArgument::REQUIRED, 'Config name, matching contexts/<target>.zap-config.php')
        ;
    }

    /**
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $target = $input->getArgument('target');
        $configPath = __DIR__ . "/../environment/security/zaproxy/contexts/{$target}.zap-config.php";

        if (!is_file($configPath)) {
            $io->error("No config found at _dev/environment/security/zaproxy/contexts/{$target}.zap-config.php - run ide:zap-context {$target} first.");
            return Command::FAILURE;
        }

        $config = require $configPath;
        $host = parse_url($config['target'], PHP_URL_HOST) ?? $config['target'];

        $daemonExit = $this->getApplication()->find('ide:zap-daemon')->run(new ArrayInput([]), $output);

        if ($daemonExit !== Command::SUCCESS) {
            $io->error('Could not reach a ZAP daemon.');
            return Command::FAILURE;
        }

        $recorded = $this->recordedRequests($io, $host);
        $io->writeln(count($recorded) . " recorded request(s) for {$host} this session.");

        $recordedJson = escapeshellarg(json_encode($recorded));
        $diffJson = shell_exec("docker exec -w /opt/repos/stockman/deploy fpm php artisan security:coverage-diff --recorded={$recordedJson} 2>/dev/null");
        $diff = json_decode(trim((string) $diffJson), true);

        if ($diff === null) {
            $io->error('security:coverage-diff did not return valid JSON - is the Stockman container up?');
            return Command::FAILURE;
        }

        $io->section('Covered');
        $io->listing($diff['covered'] ?: ['(none)']);

        $io->section('Uncovered');
        $io->listing($diff['uncovered'] ?: ['(none)']);

        return Command::SUCCESS;
    }

    /**
     * Pulls every message ZAP has recorded this session for the given host,
     * extracting {method, url} from each - `core/view/messages` has no such
     * fields directly, only a raw `requestHeader` string whose first line is
     * the request line (e.g. "POST /api/x HTTP/1.1"), confirmed via a live
     * call rather than assumed from docs. Also confirmed live: a handful of
     * malformed/incomplete entries can appear (empty responseHeader, "HTTP/1.0
     * 0") - filtered out by requiring a real 3-digit status code.
     *
     * @return array<int, array{method: string, url: string}>
     */
    private function recordedRequests(SymfonyStyle $io, string $host): array
    {
        $response = $this->zapApi($io, 'core/view/messages', ['baseurl' => '', 'start' => '', 'count' => '']);
        $requests = [];

        foreach ($response['messages'] ?? [] as $message) {
            if (!preg_match('/^(\S+)\s+(\S+)\s+HTTP/', $message['requestHeader'] ?? '', $requestLine)) {
                continue;
            }

            if (!preg_match('/^HTTP\/\S+\s+\d{3}/', $message['responseHeader'] ?? '')) {
                continue; // no real response - e.g. a proxy handshake artifact
            }

            [, $method, $url] = $requestLine;

            if (parse_url($url, PHP_URL_HOST) !== $host) {
                continue;
            }

            $requests[] = ['method' => $method, 'url' => $url];
        }

        return $requests;
    }
}
