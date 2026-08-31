<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapContextCommand extends Command
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
            ->setName('ide:zap-context')
            ->setDescription('Build (or rebuild) a ZAP authenticated-scan context from contexts/<target>.zap-config.php')
            ->addArgument('target', InputArgument::REQUIRED, 'Config name, matching contexts/<target>.zap-config.php')
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
        $target = $input->getArgument('target');
        $configPath = __DIR__ . "/../environment/security/zaproxy/contexts/{$target}.zap-config.php";

        if (!is_file($configPath)) {
            return $this->scaffoldConfig($io, $target, $configPath);
        }

        $config = require $configPath;
        $contextName = $config['target'];

        // The ZAP API is only reachable from inside the container's own network
        // namespace - it silently drops connections that don't present as true
        // loopback, which a host->container port-forward does not on Docker
        // Desktop for Mac. So every call goes via `docker compose exec`, not a
        // direct HTTP request - ide:zap-daemon ensures something's actually
        // running to exec into, starting a headless one if needed (a full
        // ide:zap-hud session works fine too, if one's already open).
        $daemonExit = $this->getApplication()->find('ide:zap-daemon')->run(new ArrayInput([]), $output);

        if ($daemonExit !== Command::SUCCESS) {
            $io->error('Could not reach a ZAP daemon.');
            return Command::FAILURE;
        }

        // Rebuilding from scratch each run avoids duplicate/stale contexts from
        // repeated invocations - removeContext errors harmlessly if it's not there yet.
        $this->zapApi($io, 'context/action/removeContext', ['contextName' => $contextName], allowMissing: true);
        $created = $this->zapApi($io, 'context/action/newContext', ['contextName' => $contextName]);
        $contextId = $created['contextId'] ?? null;

        if ($contextId === null) {
            $io->error('Failed to create ZAP context - is `ide:zap-hud` running?');
            return Command::FAILURE;
        }

        foreach ($config['scope']['include'] ?? [] as $regex) {
            $this->zapApi($io, 'context/action/includeInContext', ['contextName' => $contextName, 'regex' => $regex]);
        }

        foreach ($config['scope']['exclude'] ?? [] as $regex) {
            $this->zapApi($io, 'context/action/excludeFromContext', ['contextName' => $contextName, 'regex' => $regex]);
        }

        $authMethodConfigParams = http_build_query([
            'loginUrl' => $config['login']['url'],
            'loginPageUrl' => $config['login']['page_url'] ?? $config['login']['url'],
            'loginRequestData' => $config['login']['request_data'],
        ]);

        $this->zapApi($io, 'authentication/action/setAuthenticationMethod', [
            'contextId' => $contextId,
            'authMethodName' => 'formBasedAuthentication',
            'authMethodConfigParams' => $authMethodConfigParams,
        ]);

        if (!empty($config['indicator']['logged_in'])) {
            $this->zapApi($io, 'authentication/action/setLoggedInIndicator', [
                'contextId' => $contextId,
                'loggedInIndicatorRegex' => $config['indicator']['logged_in'],
            ]);
        }

        if (!empty($config['indicator']['logged_out'])) {
            $this->zapApi($io, 'authentication/action/setLoggedOutIndicator', [
                'contextId' => $contextId,
                'loggedOutIndicatorRegex' => $config['indicator']['logged_out'],
            ]);
        }

        $newUser = $this->zapApi($io, 'users/action/newUser', [
            'contextId' => $contextId,
            'name' => $config['user']['name'],
        ]);
        $userId = $newUser['userId'] ?? null;

        if ($userId === null) {
            $io->error('Failed to create ZAP user.');
            return Command::FAILURE;
        }

        $credentialParams = http_build_query([
            'username' => $config['user']['username'],
            'password' => $config['user']['password'],
        ]);

        $this->zapApi($io, 'users/action/setAuthenticationCredentials', [
            'contextId' => $contextId,
            'userId' => $userId,
            'authCredentialsConfigParams' => $credentialParams,
        ]);

        $this->zapApi($io, 'users/action/setUserEnabled', [
            'contextId' => $contextId,
            'userId' => $userId,
            'enabled' => 'true',
        ]);

        $contextFile = "/zap/wrk/{$target}.context";
        $hostContextFile = __DIR__ . "/../environment/security/zaproxy/reports/{$target}.context";

        $this->zapApi($io, 'context/action/exportContext', [
            'contextName' => $contextName,
            'contextFile' => $contextFile,
        ]);

        // No API action sets the authentication verification strategy - a fresh
        // context always defaults to POLL_URL with no poll URL configured, which
        // crashes every authentication check. EACH_RESP (check the indicator on
        // every response) looks like the obvious alternative but has no caching,
        // so it re-authenticates before most requests and self-locks against any
        // login rate limit. POLL_URL WITH a poll URL set is correct: it caches
        // its result for pollFrequency (60s) - verified live, 0 vs 15 re-logins
        // for the same scan. Only reachable by editing the exported XML and
        // re-importing it, since the API has no action for this field either.
        //
        // A never-imported context has no <pollurl> element at all (ZAP only
        // emits it once a context has round-tripped through XML at least once),
        // so this injects it after <strategy> rather than replacing one that
        // doesn't exist yet.
        if (!empty($config['indicator']['poll_url'])) {
            $xml = file_get_contents($hostContextFile);
            $pollUrl = htmlspecialchars($config['indicator']['poll_url'], ENT_XML1);
            $xml = preg_replace(
                '/<strategy>.*?<\/strategy>/',
                "<strategy>POLL_URL</strategy>\n            <pollurl>{$pollUrl}</pollurl>",
                $xml
            );
            file_put_contents($hostContextFile, $xml);

            $this->zapApi($io, 'context/action/removeContext', ['contextName' => $contextName]);
            $this->zapApi($io, 'context/action/importContext', ['contextFile' => $contextFile]);
            $this->zapApi($io, 'context/action/exportContext', [
                'contextName' => $contextName,
                'contextFile' => $contextFile,
            ]);
        }

        $io->success("Exported context to _dev/environment/security/zaproxy/reports/{$target}.context (user: {$config['user']['name']})");

        return Command::SUCCESS;
    }

    /**
     * Scaffolds a new <target>.zap-config.php from the tracked example
     * rather than requiring a manual copy - substitutes the example's
     * placeholder hostname throughout. Can't guess real credentials, login
     * field names, or an indicator regex, so this always stops short of
     * actually building anything - the point is removing the copy/rename
     * step, not the "fill in what only you know" step.
     */
    private function scaffoldConfig(SymfonyStyle $io, string $target, string $configPath): int
    {
        $examplePath = __DIR__ . '/../environment/security/zaproxy/contexts/example.zap-config.php';

        if (!is_file($examplePath)) {
            $io->error("No config found at _dev/environment/security/zaproxy/contexts/{$target}.zap-config.php, and no example.zap-config.php to scaffold from.");
            return Command::FAILURE;
        }

        $scaffold = str_replace(
            ['example.test', 'example\.test'],
            ["{$target}.test", "{$target}\.test"],
            file_get_contents($examplePath)
        );
        file_put_contents($configPath, $scaffold);

        $io->warning([
            "No config existed for '{$target}' - scaffolded _dev/environment/security/zaproxy/contexts/{$target}.zap-config.php from the example.",
            "Edit its login/indicator/user details for {$target}.test, then run this command again.",
        ]);

        return Command::FAILURE;
    }
}
