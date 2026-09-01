<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapHudFixCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:zap-hud-fix')
            ->setDescription('Diagnose and fix a HUD session stuck in a browser "Session ended" / "New session" loop')
        ;
    }

    /**
     * Confirmed live (not assumed): this loop happens when a ZAP process
     * from an earlier browser session is still running inside the container
     * and never released - Webswing's own sessions never expire on
     * inactivity, so closing a browser tab doesn't stop it. Every new launch
     * attempt then fails with "The home directory is already in use", which
     * Webswing reports to the browser as an immediate "Session ended" - the
     * real error is only ever visible in the container's own
     * webswing.out log, never `docker logs` (which shows one startup line).
     *
     * Evidence-first: confirms the lock error actually appears in the log
     * and shows exactly which process(es) it found before touching
     * anything, rather than killing ZAP processes on a guess.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $container = trim((string) shell_exec('docker ps -q --filter name=zaproxy-run'));

        if ($container === '') {
            $io->writeln('No HUD (webswing) container is currently running - nothing to fix. Start one with `ide:zap-hud`.');
            return Command::SUCCESS;
        }

        $containerName = trim((string) shell_exec("docker ps --filter id={$container} --format '{{.Names}}'"));

        // Only the last ~30 lines (roughly one failed-launch cycle, confirmed
        // against real log output) - a wider window risks matching a stale
        // error from an attempt that's since been superseded by a healthy
        // session, which would otherwise wrongly flag a perfectly good
        // in-progress session as broken.
        $log = (string) shell_exec("docker exec {$container} tail -n 30 /zap/webswing/webswing.out 2>&1");
        $lockErrorSeen = str_contains($log, 'The home directory is already in use');

        if (!$lockErrorSeen) {
            $io->writeln("Container {$containerName} looks healthy - no \"home directory already in use\" error in its recent log. If the browser is still showing \"Session ended\", the cause is probably something else (check the container's own webswing.out log directly, or a proxy/network issue). Not touching the running ZAP process - a healthy session always has one, and this only offers to kill it when there's actual evidence something's wrong.");
            return Command::SUCCESS;
        }

        $io->writeln("Confirmed: {$containerName}'s log shows \"The home directory is already in use\" - a stale ZAP process is blocking every new session from starting.");

        $strayPids = $this->findStrayZapProcesses($container);

        if ($strayPids === []) {
            $io->warning("The lock error is in the log, but no ZAP process was found to kill - a full container restart may be needed instead: docker restart {$containerName}");
            return Command::SUCCESS;
        }

        $io->section('Stray ZAP process(es) found');
        foreach ($strayPids as $stray) {
            $io->writeln("  PID {$stray['pid']}, started {$stray['started']}");
        }

        $question = count($strayPids) === 1
            ? 'Kill this process so a fresh HUD session can start?'
            : 'Kill these processes so a fresh HUD session can start?';

        if (!$io->confirm($question, true)) {
            return Command::SUCCESS;
        }

        $pidList = implode(' ', array_column($strayPids, 'pid'));
        shell_exec("docker exec {$container} kill {$pidList} 2>&1");

        $io->success('Killed. Refresh the HUD in your browser and it should start a fresh session (you\'ll need to re-run ide:zap-context afterward - a fresh ZAP process has no context loaded).');

        return Command::SUCCESS;
    }

    /**
     * The webswing-jetty-launcher process (webswing's own server) is
     * expected and never the problem - only main.Main (the actual ZAP/Swing
     * app it launches per session) can hold the home-directory lock.
     * Column layout (PID at index 1, START at index 8) confirmed against
     * real `ps aux` output from inside this exact container, not assumed
     * from a generic ps man page.
     *
     * @return array<int, array{pid: string, started: string}>
     */
    private function findStrayZapProcesses(string $container): array
    {
        $psOutput = (string) shell_exec("docker exec {$container} ps aux 2>&1");
        $stray = [];

        foreach (explode("\n", $psOutput) as $line) {
            if (!str_contains($line, 'main.Main') || str_contains($line, 'webswing-jetty-launcher')) {
                continue;
            }

            $columns = preg_split('/\s+/', trim($line));

            if (isset($columns[1])) {
                $stray[] = ['pid' => $columns[1], 'started' => $columns[8] ?? '?'];
            }
        }

        return $stray;
    }
}
