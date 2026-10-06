<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapInstallManifestsCommand extends Command
{
    /**
     * Where the Laravel stubs live, relative to this file
     */
    private const STUB_DIR = __DIR__ . '/../environment/security/zaproxy/stubs/laravel';

    /**
     * The manifest config keys, and the artisan command that generates each
     */
    private const MANIFESTS = [
        'seed_urls' => 'security:seed-urls',
        'write_routes' => 'security:seed-write-routes',
        'livewire_actions' => 'security:seed-livewire-actions',
    ];

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:zap-install-manifests')
            ->setDescription('Copy the security:* route-manifest commands into a target Laravel app, and wire them into its zap-config')
            ->addArgument('target', InputArgument::REQUIRED, 'Config name, matching contexts/<target>.zap-config.php')
            ->addOption('app-path', null, InputOption::VALUE_REQUIRED, "The app's root inside the fpm container (/opt/repos/<repo>/deploy) - only needed if the config has no app_path yet")
            ->addOption('user', null, InputOption::VALUE_REQUIRED, "The scan user's email, baked in as the commands' --user default (defaults to the config's user.username)")
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, "The site's base URL, baked in as the commands' --base-url default (defaults to the config's login URL scheme and host)")
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite command files that already exist in the app')
        ;
    }

    /**
     * Each target used to need these commands hand-copied from whichever
     * app last had them, then its config hand-edited to call them - and a
     * missing config line fails silently ($artisan returns [] on any
     * error), which is how a full scan once ran with no seed URLs at all.
     * This does both steps, then runs security:seed-urls to prove the
     * result actually produces something.
     *
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
        $appPath = $input->getOption('app-path') ?? $config['app_path'] ?? null;

        if ($appPath === null) {
            $io->error("contexts/{$target}.zap-config.php has no app_path - pass --app-path=/opt/repos/<repo>/deploy (the app's root inside the fpm container).");
            return Command::FAILURE;
        }

        $appPath = rtrim($appPath, '/');
        $hostAppPath = $this->hostPath($appPath);

        if ($hostAppPath === null || !is_file("{$hostAppPath}/artisan")) {
            $io->error("{$appPath} doesn't map to a Laravel app under Repos/ (expected /opt/repos/<repo>/... containing an artisan file).");
            return Command::FAILURE;
        }

        $scanUser = $input->getOption('user') ?? $config['user']['username'] ?? null;
        $loginUrl = $config['login']['url'] ?? '';
        $baseUrl = $input->getOption('base-url')
            ?? (parse_url($loginUrl, PHP_URL_HOST) ? parse_url($loginUrl, PHP_URL_SCHEME) . '://' . parse_url($loginUrl, PHP_URL_HOST) : null);

        if ($scanUser === null || $baseUrl === null) {
            $io->error('Could not work out the scan user or base URL from the config - pass --user and --base-url.');
            return Command::FAILURE;
        }

        $this->installStubs($io, "{$hostAppPath}/app/Console/Commands", rtrim($baseUrl, '/'), $scanUser, (bool) $input->getOption('force'));

        if (!$this->wireConfig($io, $configPath, $appPath)) {
            return Command::FAILURE;
        }

        return $this->verify($io, $appPath);
    }

    /**
     * Host path for a path inside the fpm container - Repos/ is mounted at
     * /opt/repos (see servers/nginx/docker-compose.yml)
     */
    private function hostPath(string $containerPath): ?string
    {
        if (!str_starts_with($containerPath, '/opt/repos/')) {
            return null;
        }

        return __DIR__ . '/../../Repos/' . substr($containerPath, strlen('/opt/repos/'));
    }

    /**
     * Renders each stub's placeholders and copies it in, never overwriting
     * an existing file without --force - the app's copy may have local
     * adjustments (skipped prefixes, admin middleware) worth keeping.
     */
    private function installStubs(SymfonyStyle $io, string $commandsDir, string $baseUrl, string $scanUser, bool $force): void
    {
        if (!is_dir($commandsDir)) {
            mkdir($commandsDir, 0755, true);
        }

        // Values land inside single-quoted PHP strings in the stubs
        $placeholders = [
            '{{ base_url }}' => addcslashes($baseUrl, "'\\"),
            '{{ scan_user }}' => addcslashes($scanUser, "'\\"),
        ];

        $rows = [];

        foreach (glob(self::STUB_DIR . '/*.php.stub') as $stub) {
            $file = basename($stub, '.stub');
            $destination = "{$commandsDir}/{$file}";

            if (is_file($destination) && !$force) {
                $rows[] = [$file, 'skipped (exists - use --force to overwrite)'];
                continue;
            }

            $existed = is_file($destination);
            file_put_contents($destination, strtr(file_get_contents($stub), $placeholders));
            $rows[] = [$file, $existed ? 'overwritten' : 'installed'];
        }

        $io->section('Commands');
        $io->table(['File', 'Result'], $rows);
        $io->writeln("Defaults baked in: --user={$scanUser} --base-url={$baseUrl}");
        $io->writeln("Review the skipped route prefixes and the admin middleware check in ResolvesRouteModelBindings.php for this app.");
    }

    /**
     * Adds whatever the config is missing - the $artisan helper, app_path
     * and each manifest key - leaving anything already there untouched. A
     * commented-out key counts as missing. The edited file is linted before
     * it's kept, so a malformed config is never left behind.
     */
    private function wireConfig(SymfonyStyle $io, string $configPath, string $appPath): bool
    {
        $original = file_get_contents($configPath);
        $php = $original;
        $added = [];

        if (!preg_match('/^\$artisan\s*=/m', $php)) {
            $helper = <<<PHP
            // The target app's root inside the fpm container. \$artisan runs a
            // command there and decodes its JSON output ([] on any failure).
            \$appPath = '{$appPath}';

            \$artisan = static function (string \$command) use (\$appPath): array {
                \$json = shell_exec("docker exec -w {\$appPath} fpm php artisan {\$command} 2>/dev/null");

                return json_decode(trim((string) \$json), true) ?? [];
            };


            PHP;
            $php = preg_replace('/^return \[/m', $helper . 'return [', $php, 1);
            $added[] = '$artisan helper';
        }

        $entries = '';

        if (!preg_match("/^\s*'app_path'\s*=>/m", $php)) {
            $entries .= "\n    'app_path' => \$appPath,\n";
            $added[] = 'app_path';
        }

        foreach (self::MANIFESTS as $key => $command) {
            if (!preg_match("/^\s*'{$key}'\s*=>/m", $php)) {
                $entries .= "\n    '{$key}' => \$artisan('{$command}'),\n";
                $added[] = $key;
            }
        }

        if ($entries !== '') {
            // Before the closing bracket of the returned array (the last "];")
            $position = strrpos($php, '];');
            $php = substr($php, 0, $position) . $entries . substr($php, $position);
        }

        $io->section('Config');

        if ($added === []) {
            $io->writeln('Already wired - nothing to add.');
            return true;
        }

        file_put_contents($configPath, $php);
        exec('php -l ' . escapeshellarg($configPath) . ' 2>&1', $lint, $lintExit);

        if ($lintExit !== 0) {
            file_put_contents($configPath, $original);
            $io->error("Editing the config produced invalid PHP, so it was left unchanged. Add these by hand: " . implode(', ', $added));
            return false;
        }

        $io->writeln('Added to ' . basename($configPath) . ': ' . implode(', ', $added));

        return true;
    }

    /**
     * Runs security:seed-urls in the fpm container - the same call the
     * config makes - so a broken install shows up now, not as a scan that
     * quietly had nothing to seed.
     */
    private function verify(SymfonyStyle $io, string $appPath): int
    {
        $io->section('Check');
        $command = 'docker exec -w ' . escapeshellarg($appPath) . ' fpm php artisan security:seed-urls';

        // stdout only - PHP warnings (e.g. Xdebug failing to connect) go to
        // stderr and would otherwise corrupt the JSON
        $urls = json_decode(trim((string) shell_exec("{$command} 2>/dev/null")), true);

        if (!is_array($urls)) {
            $io->error("security:seed-urls didn't return JSON - is the fpm container up? Output:\n" . trim((string) shell_exec("{$command} 2>&1 | tail -n 5")));
            return Command::FAILURE;
        }

        if ($urls === []) {
            $io->warning('security:seed-urls ran but returned no URLs - check the scan user exists in the app database and owns some records.');
            return Command::SUCCESS;
        }

        $io->success(count($urls) . ' seed URL(s) generated. Scans using this config now start from the full route table.');

        return Command::SUCCESS;
    }
}
