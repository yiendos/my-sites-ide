<?php 

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Plugins\Discover;

class SparkCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:spark')
            ->setDescription('Spark your creativity to life, by bringing the IDE up')
            ->addOption('app', null, InputOption::VALUE_OPTIONAL, 'Which containers you would like to open', getenv('APP'))
        ;
    }
    /**
     * Invoke vs execute because you cannot Dependancy Inject the requirements because of the command concrete cast
     * Same same, same but different ensure that we execute the user input
     *
     * @param OutputInterface $output
     * @param Application $application
     * @param InputInterface $input
     * @return integer
     */
    public function __invoke(OutputInterface $output,InputInterface $input, SymfonyStyle $io): int
    {
        $app = $input->getOption('app');

        // an explicit --app is taken as-is, otherwise plugins marked autostart join APP
        // (an empty APP already starts every service, plugins included)
        if (!$input->hasParameterOption('--app') && trim((string) $app) !== '') {
            $autostart = array_merge(...array_values(array_map(
                fn (array $plugin): array => $plugin['autostart'] ? $plugin['services'] : [],
                Discover::load(),
            )));

            $app = implode(' ', array_unique([...preg_split('/\s+/', trim($app)), ...$autostart]));
        }

        $app = $this->withoutMissingServices((string) $app, $io);

        // server plugins (e.g. nginx) mount the shared certificate store whether or not a
        // certificate plugin is installed - create it here, or Docker creates it owned by root on Linux hosts
        foreach (['live', 'archive'] as $directory) {
            if (!is_dir(Ide::path("storage/certificates/{$directory}"))) {
                mkdir(Ide::path("storage/certificates/{$directory}"), 0755, true);
            }
        }

        $output->writeLn("docker compose up -d $app --remove-orphans");
        passthru("docker compose up -d $app --remove-orphans");

$ascii = <<<EOT
                                   /\
                              /\  // \\
                       /\    //\\///  \\\      /\
                      //\\  ///\////\\\\\  /\\
         /\          /  ^ \/^ ^/^  ^  ^ \/^ \/  ^ \
        / ^\    /\  / ^   /  ^/ ^ ^ ^   ^\ ^/  ^^  \
       /^   \  / ^\/ ^ ^   ^ / ^  ^    ^  \/ ^   ^  \       *
      /  ^ ^ \/^  ^\ ^ ^ ^   ^  ^   ^   ____  ^   ^  \     /|\
     / ^ ^  ^ \ ^  _\___________________|  |_____^ ^  \   /||o\
    / ^^  ^ ^ ^\  /______________________________\ ^ ^ \ /|o|||\
   /  ^  ^^ ^ ^  /________________________________\  ^  /|||||o|\
  /^ ^  ^ ^^  ^    ||___|___||||||||||||___|__|||      /||o||||||\       |
 / ^   ^   ^    ^  ||___|___||||||||||||___|__|||          | |           |
/ ^ ^ ^  ^  ^  ^   ||||||||||||||||||||||||||||||oooooooooo| |ooooooo  |
ooooooooooooooooooooooooooooooooooooooooooooooooooooooooo
EOT;

        $io->title('Welcome home'); 

        $output->writeLn([
            '',
            $ascii, 
        ]);  

        $output->writeln('<href=https://localhost>See your homepage</>');

        return Command::SUCCESS;
    }
    /**
     * Drops services the stack no longer has - an APP written before a
     * service moved into a plugin (e.g. cron, folded into the PHP plugin's
     * cli) or whose plugin was uninstalled - which would otherwise stop
     * `docker compose up` before it starts anything
     *
     * @param string $app
     * @param SymfonyStyle $io
     * @return string
     */
    private function withoutMissingServices(string $app, SymfonyStyle $io): string
    {
        $requested = preg_split('/\s+/', trim($app), -1, PREG_SPLIT_NO_EMPTY);
        $known = preg_split('/\s+/', trim((string) shell_exec('docker compose config --services 2>/dev/null')), -1, PREG_SPLIT_NO_EMPTY);

        // can't tell (e.g. the compose file doesn't parse) - leave it to compose to report
        if ($requested === [] || $known === []) {
            return $app;
        }

        $missing = array_diff($requested, $known);

        if ($missing !== []) {
            $io->warning('Skipping ' . implode(', ', $missing) . " - no such service (moved into a plugin, or its plugin isn't installed). Remove it from APP in .env.");
        }

        return implode(' ', array_intersect($requested, $known));
    }
}