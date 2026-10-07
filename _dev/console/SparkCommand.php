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
}