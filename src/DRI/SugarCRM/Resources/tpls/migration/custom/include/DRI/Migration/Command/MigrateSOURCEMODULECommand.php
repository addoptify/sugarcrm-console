<?php

namespace DRI\Migration\Command;

require_once "custom/include/DRI/Migration/Command/AbstractMigrationCommand.php";
require_once "custom/include/DRI/Migration/{{source}}/{{module}}Migration.php";

/**
 * @author Emil Kilhage
 */
class Migrate{{source}}{{module}}Command extends AbstractMigrationCommand
{
    /**
     * @var string
     */
    protected $className = \DRI\Migration\{{source}}\{{module}}Migration::class;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('migrate:{{source_lower}}:{{module_lower}}')
            ->setDescription('Migrates {{module}} from {{source}}');
    }
}
