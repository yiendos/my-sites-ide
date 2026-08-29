<?php

namespace Yiendos\MySitesIde;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapDaemonCommand extends Command
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
            ->setName('ide:zap-daemon')
            ->setDescription('Ensure a ZAP daemon is running and its API is reachable, starting a headless one if needed')
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
        if ($this->isReachable()) {
            $io->writeln('ZAP daemon already up.');
            return Command::SUCCESS;
        }

        // The Insights addon's report-generation hook throws NoSuchMethodError
        // (an addon/core version mismatch in this weekly build) whenever it
        // processes an auth-related stat during report generation, killing
        // the whole daemon - reproduced via both /OTHER/core/other/htmlreport/
        // and reports/action/generate, and confirmed live under real active-scan
        // load (exitCode=2, not an OOM's 137).
        //
        // Passing -addonuninstall on the SAME command line as -daemon does NOT
        // work - confirmed via the daemon's own boot log timestamps: -daemon
        // initializes every bundled extension (including Insights, registering
        // its crash-prone hook) before the command-line -addonuninstall action
        // even runs, so by the time it reports success the damage is already
        // done for that process's lifetime. Since each --rm container is
        // otherwise stateless, the fix needs the uninstall to happen and
        // *persist* before any daemon ever loads extensions - hence the
        // zap-home named volume (see zaproxy/docker-compose.yml) shared
        // between this one-shot uninstall pass and the daemon run below.
        // Confirmed via boot log: with this, the "Installed add-ons" list no
        // longer contains insights at all, not just "uninstalled after load".
        $io->writeln('Ensuring the buggy Insights addon stays uninstalled...');
        shell_exec('docker compose run --rm zaproxy zap.sh -addonuninstall insights -cmd 2>&1');

        // -d backgrounds this at the Docker level (unlike ide:zap-hud, which
        // uses passthru() to stream the interactive Desktop UI in the
        // foreground) - no shell job control or output redirection needed
        // here since nobody's watching this one interactively. No webswing/UI
        // overhead either - ide:zap-context only ever needs the bare API.
        $io->writeln('Starting a headless ZAP daemon...');
        shell_exec('docker compose run -d --rm --name zap-daemon zaproxy zap.sh -daemon -host 0.0.0.0 -port 8090 -config api.disablekey=true 2>&1');

        $waited = 0;
        while (!$this->isReachable()) {
            if ($waited >= 60) {
                $io->error('ZAP daemon did not become reachable within 60s.');
                return Command::FAILURE;
            }

            sleep(2);
            $waited += 2;
        }

        $io->writeln("ZAP daemon ready after {$waited}s.");

        // A real active scan OOM-killed this daemon (exitCode 137, confirmed
        // via `docker events`) under genuine sustained load - the exact
        // thread-explosion class of crash originally diagnosed and fixed for
        // ide:zap-hud's webswing flow via ascan.threadPerHost/delayInMs. That
        // equivalent setting never got carried over to this daemon.
        //
        // Passing it as `-config ascan.threadPerHost=N` on this same command
        // line does NOT work, unlike e.g. `-config api.disablekey` - verified
        // live: the resulting daemon reports ThreadPerHost=8 regardless (a
        // suspiciously exact 2x the 4-core cpuset, i.e. ZAP's own CPU-derived
        // default silently wins), the same "extension reads its own defaults
        // before command-line -config values for it apply" timing class as
        // the Insights addon-uninstall bug above. Setting it via the API
        // instead, once the daemon is confirmed reachable, is what actually
        // sticks - checked via ascan/view/optionThreadPerHost reading back 2
        // afterwards, not just trusting the setter's own "OK" response.
        Dotenv::createImmutable(__DIR__ . '/../environment/security/zaproxy')->safeLoad();
        $this->zapApi($io, 'ascan/action/setOptionThreadPerHost', [
            'Integer' => (string) (getenv('ZAP_ASCAN_THREADS_PER_HOST') ?: 2),
        ]);
        $this->zapApi($io, 'ascan/action/setOptionDelayInMs', [
            'Integer' => (string) (getenv('ZAP_ASCAN_DELAY_MS') ?: 0),
        ]);

        return Command::SUCCESS;
    }

    /**
     * The ZAP API is only reachable from inside the container's own network
     * namespace (see ZapContextCommand) - `docker compose exec` also fails
     * outright with a non-zero exit if no container is running for the
     * service at all, which doubles as the "nothing running yet" check.
     *
     * @return boolean
     */
    private function isReachable(): bool
    {
        $response = shell_exec('docker compose exec zaproxy curl -s http://localhost:8090/JSON/core/view/version/ 2>/dev/null');

        return is_string($response) && str_contains($response, 'version');
    }
}
