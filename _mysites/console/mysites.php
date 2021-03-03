<?php
// application.php

echo "\n" . dirname(__FILE__, 1) . '/vendor/autoload.php' .  "\n";

require dirname(__FILE__, 1) . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

use MySites\Console\Command\Box;

$application = new Application();


var_dump(get_declared_classes());
// ... register commands
$application->add(new Configure());


$application->run();