<?php

namespace bforchhammer\JiffyBoxApi\console;

use bforchhammer\JiffyBoxApi\JiffyBoxClient;
use bforchhammer\JiffyBoxApi\service\ServiceDescriptionBuilder;
use Guzzle\Service\Description\Operation;
use Guzzle\Service\Description\ServiceDescription;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateServiceDescriptionCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('jiffybox:generate-service-description')
            ->setDescription('Generate JiffyBox service descriptions from the API documentation.')
            ->addArgument(
                'token',
                InputArgument::REQUIRED,
                'JiffyBox token'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $token = $input->getArgument('token');
        $client = JiffyBoxClient::factory(array('token' => $token));

        $builder = new ServiceDescriptionBuilder();
        $modules = $client->listModules();
        foreach ($modules as $module => $moduleDescription) {
            $output->writeln('<info>Generating description for ' . $module . ' ...</info>');
            $documentation = $client->getDocumentation($module);

            // Ignore the description key.
            unset($documentation['description']);

            // Parse each remaining entry in the array as an operation.
            foreach ($documentation as $path => $command) {
                foreach ($command as $method => $details) {
                    try {
                        $operation = $builder->parseOperation($module, $path, $method, $details);
                        $output->writeln('- <info>Added operation ' . $operation->getName() . '</info>');
                    } catch (\InvalidArgumentException $e) {
                        $output->writeln('- <error>Invalid operation: ' . $e->getMessage() . '</error>');
                    }
                }
            }
            //break; // avoid overloading api with too many requests.
        }
        $sd = $builder->getServiceDescription();

        $operations = $sd->getOperations();
        $rows = array();
        foreach ($operations as $operation) {
            $rows[] = array($operation->getHttpMethod(), $operation->getUri(), $operation->getName(), $operation->getSummary());
        }

        $table = $this->getHelperSet()->get('table');
        $table
            ->setHeaders(array('HTTP Method', 'URI', 'Operation', 'Description'))
            ->setRows($rows);
        $table->render($output);

        $output->writeln(print_r($sd->toArray(), 1));
    }

}