<?php

namespace DRI\SugarCRM\Console\Command\Generate;

use DRI\SugarCRM\Console\Command\ApplicationCommand;
use DRI\SugarCRM\Console\Generator\MigrationGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Emil Kilhage
 */
class GenerateMigrationCommand extends ApplicationCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('generate:migration')
            ->addArgument('source', InputArgument::REQUIRED, '')
            ->addArgument('entity', InputArgument::REQUIRED, '')
            ->addOption('format', 'F', InputOption::VALUE_REQUIRED, '', 'csv')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '')
            ->addOption('dry', 'd', InputOption::VALUE_NONE, '')
            ->setDescription('Generates a new migration');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $generator = new MigrationGenerator();

        $generator->setParams(array (
            MigrationGenerator::SOURCE => $input->getArgument('source'),
            MigrationGenerator::MODULE => $input->getArgument('entity'),
            MigrationGenerator::FORMAT => $input->getOption('format'),
            MigrationGenerator::FORCE => $input->getOption('force'),
            MigrationGenerator::DRY => $input->getOption('dry'),
        ));

        $generator->generate();

        $params = $generator->getParams();

        $text = <<<TXT

The installation of migration for entity {$input->getArgument('entity')} from {$input->getArgument('source')} is completed.

As a final step you need to install the command into the ./commands.php

<?php

require_once __DIR__ . '/docroot/custom/include/DRI/Migration/Command/Migrate{$input->getArgument('source')}{$input->getArgument('entity')}Command.php';

return array (
    new \DRI\Migration\Command\Migrate{$input->getArgument('source')}{$input->getArgument('entity')}Command(),
);

After you have done this you will be able to import {$input->getArgument('entity')} from {$input->getArgument('source')} trough the following command:

 $ bin/sugarcrm migrate:{$params[MigrationGenerator::SOURCE.'_lower']}:{$params[MigrationGenerator::MODULE.'_lower']} ~/Downloads/{$params[MigrationGenerator::MODULE.'_lower']}.csv

TXT;

        $output->writeln($text);
    }
}
