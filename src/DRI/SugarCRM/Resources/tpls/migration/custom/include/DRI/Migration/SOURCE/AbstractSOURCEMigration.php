<?php

namespace DRI\Migration\{{source}};

use DRI\Migration\AbstractCsvMigration;

require_once "custom/include/DRI/Migration/AbstractCsvMigration.php";

/**
 * @author Emil Kilhage
 */
abstract class Abstract{{source}}Migration extends AbstractCsvMigration
{
    /**
     * @var string
     */
    protected $source = '{{source}}';
}
