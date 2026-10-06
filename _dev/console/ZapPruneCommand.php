<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapPruneCommand extends Command
{
    /**
     * Where ZAP writes sessions in its home dir: sessions/ for the ones the
     * HUD auto-creates, session/ for ones saved under a name (confirmed live
     * via core/view/sessionLocation). The home dir is .ZAP_D on weekly builds
     * and .ZAP on stable ones, so it's globbed rather than pinned.
     */
    private const SESSION_DIRS = '/home/zap/.ZAP*/session /home/zap/.ZAP*/sessions';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:zap-prune')
            ->setDescription('Reclaim zap-home volume space by deleting old ZAP sessions (never the one in use)')
            ->addOption('keep', null, InputOption::VALUE_REQUIRED, 'Keep this many of the newest sessions', '0')
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Only delete sessions last written more than this many days ago')
            ->addOption('logs', null, InputOption::VALUE_NONE, "Also delete ZAP's rotated logs (zap.log.1, zap.log.2, ...)")
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be deleted, without deleting anything')
        ;
    }

    /**
     * Every HUD browser session auto-creates a ZAP session in the zap-home
     * named volume, and nothing ever removes them - confirmed live at 3.4GB
     * across 7 sessions after a day of scanning (one 75k-request scan alone
     * was 2GB). The volume isn't visible from the host, so this does the
     * listing and deleting from inside a container.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $keep = (int) $input->getOption('keep');
        $olderThan = $input->getOption('older-than');
        $container = $this->runningZapContainer();
        $inUse = $container !== null ? $this->sessionInUse($container) : null;

        $sessions = $this->listSessions($container);

        if ($sessions === []) {
            $io->writeln('No ZAP sessions found in the zap-home volume.');
        }

        // Newest first, so --keep skips the most recent ones
        usort($sessions, fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        $rows = [];
        $toDelete = [];
        $kept = 0;

        foreach ($sessions as $session) {
            $reason = match (true) {
                "{$session['dir']}/{$session['name']}" === $inUse => 'in use',
                $kept < $keep => 'kept (--keep)',
                $olderThan !== null && $session['modified'] > time() - ((int) $olderThan * 86400) => 'kept (--older-than)',
                default => null,
            };

            if ($reason === 'kept (--keep)') {
                $kept++;
            }

            if ($reason === null) {
                $toDelete[] = $session;
            }

            $rows[] = [
                $session['name'],
                basename($session['dir']),
                date('Y-m-d H:i', $session['modified']),
                $this->humanSize($session['kb']),
                $reason ?? '<fg=red>delete</>',
            ];
        }

        if ($rows !== []) {
            $io->table(['Session', 'Folder', 'Last written', 'Size', 'Action'], $rows);
        }

        $logs = $input->getOption('logs') ? $this->listRotatedLogs($container) : [];

        foreach ($logs as $log) {
            $io->writeln("  log: {$log['path']} ({$this->humanSize($log['kb'])}) <fg=red>delete</>");
        }

        $freedKb = array_sum(array_column($toDelete, 'kb')) + array_sum(array_column($logs, 'kb'));

        if ($toDelete === [] && $logs === []) {
            $io->success('Nothing to delete.');
            return Command::SUCCESS;
        }

        $summary = count($toDelete) . ' session(s)' . ($logs !== [] ? ' and ' . count($logs) . ' log(s)' : '') . ', freeing ' . $this->humanSize($freedKb);

        if ($input->getOption('dry-run')) {
            $io->note("Dry run - would delete {$summary}.");
            return Command::SUCCESS;
        }

        if (!$io->confirm("Delete {$summary}? This can't be undone.", false)) {
            return Command::SUCCESS;
        }

        $paths = [];

        foreach ($toDelete as $session) {
            // Name and dir both come from our own listing and are validated
            // there, so the trailing glob (.data, .log, .tmp/ etc.) is safe
            // to leave unquoted for the container's shell to expand.
            $paths[] = escapeshellarg("{$session['dir']}/{$session['name']}.session");
            $paths[] = escapeshellarg("{$session['dir']}/{$session['name']}.session.") . '*';
        }

        foreach ($logs as $log) {
            $paths[] = escapeshellarg($log['path']);
        }

        $this->runInVolume($container, 'rm -rf ' . implode(' ', $paths));

        $io->success("Deleted {$summary}. zap-home is now " . trim($this->runInVolume($container, 'du -sh /home/zap | cut -f1')) . '.');
        $io->writeln("Docker Desktop's disk image may take a while to shrink on the host - the space is free inside the VM immediately.");

        return Command::SUCCESS;
    }

    /**
     * A running daemon or HUD container, if any - preferred over starting a
     * throwaway one, and needed anyway to ask ZAP which session is open.
     * Matched by Compose's service label rather than container name, which
     * differs per launch (zap-daemon, <project>-zaproxy-run-<hash>, ...).
     */
    private function runningZapContainer(): ?string
    {
        $ids = trim((string) shell_exec('docker ps -q --filter label=com.docker.compose.service=zaproxy'));

        return $ids !== '' ? strtok($ids, "\n") : null;
    }

    /**
     * Path (minus .session) of the session ZAP currently has open, so it's
     * never deleted out from under a live scan. Null when there's nothing on
     * disk to protect: a HUD whose ZAP hasn't started yet (no browser on
     * /zap), or a daemon's unsaved session.
     */
    private function sessionInUse(string $container): ?string
    {
        $response = shell_exec('docker exec ' . escapeshellarg($container) . ' curl -s http://localhost:8090/JSON/core/view/sessionLocation/ 2>/dev/null');
        $location = json_decode((string) $response, true)['sessionLocation'] ?? '';

        return $location !== '' ? preg_replace('/\.session$/', '', $location) : null;
    }

    /**
     * @return array<int, array{dir: string, name: string, modified: int, kb: int}>
     */
    private function listSessions(?string $container): array
    {
        $script = 'for d in ' . self::SESSION_DIRS . '; do [ -d "$d" ] || continue; '
            . 'for f in "$d"/*.session; do [ -e "$f" ] || continue; n=$(basename "$f" .session); '
            . 'm=$(stat -c %Y "$d/$n.session"* | sort -n | tail -1); '
            . 'k=$(du -sk "$d/$n.session"* | awk \'{s+=$1} END {print s}\'); '
            . 'printf "%s\t%s\t%s\t%s\n" "$d" "$n" "$m" "$k"; done; done';

        $sessions = [];

        foreach (explode("\n", trim($this->runInVolume($container, $script))) as $line) {
            $fields = explode("\t", $line);

            // Only ever delete what matches the expected shape - anything
            // else in the output (a stray warning line, an odd filename) is
            // skipped rather than fed into rm.
            if (count($fields) !== 4
                || !preg_match('#^/home/zap/\.ZAP[A-Za-z_]*/sessions?$#', $fields[0])
                || !preg_match('/^[A-Za-z0-9._-]+$/', $fields[1])) {
                continue;
            }

            $sessions[] = ['dir' => $fields[0], 'name' => $fields[1], 'modified' => (int) $fields[2], 'kb' => (int) $fields[3]];
        }

        return $sessions;
    }

    /**
     * @return array<int, array{path: string, kb: int}>
     */
    private function listRotatedLogs(?string $container): array
    {
        $output = $this->runInVolume($container, 'for f in /home/zap/.ZAP*/zap.log.[0-9]*; do [ -e "$f" ] && du -sk "$f"; done');
        $logs = [];

        foreach (explode("\n", trim($output)) as $line) {
            if (preg_match('#^(\d+)\s+(/home/zap/\.ZAP[A-Za-z_]*/zap\.log\.\d+)$#', $line, $match)) {
                $logs[] = ['path' => $match[2], 'kb' => (int) $match[1]];
            }
        }

        return $logs;
    }

    /**
     * Runs a shell snippet where the zap-home volume is mounted: inside the
     * running ZAP container if there is one, otherwise a throwaway container
     * with ZAP itself never started (so no home-dir lock is taken).
     */
    private function runInVolume(?string $container, string $script): string
    {
        $command = $container !== null
            ? 'docker exec ' . escapeshellarg($container) . ' sh -c ' . escapeshellarg($script)
            : 'docker compose run -T --rm --no-deps --entrypoint sh zaproxy -c ' . escapeshellarg($script);

        // No stdin: `docker compose run` reads it by default, which swallowed
        // the answer meant for the confirmation prompt (confirmed live).
        return (string) shell_exec($command . ' < /dev/null 2>/dev/null');
    }

    private function humanSize(int $kb): string
    {
        return match (true) {
            $kb >= 1048576 => round($kb / 1048576, 1) . 'G',
            $kb >= 1024 => round($kb / 1024) . 'M',
            default => $kb . 'K',
        };
    }
}
