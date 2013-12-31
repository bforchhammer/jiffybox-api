<?php
require 'vendor/autoload.php';

$application = new Symfony\Component\Console\Application();
$application->add(new \bforchhammer\JiffyBoxApi\console\GenerateServiceDescriptionCommand());
$application->run();
