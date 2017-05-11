<?php

namespace DRI\Migration;

use DRI\Migration\Exception\SkipRowException;
use DRI\Migration\Exception\TerminateException;
use Symfony\Component\Console\Output\OutputInterface;

require_once "custom/include/DRI/Migration/Exception/SkipRowException.php";
require_once "custom/include/DRI/Migration/Exception/TerminateException.php";

/**
 * @author Emil Kilhage
 */
abstract class AbstractCsvMigration
{
    /**
     * @var \DBManager
     */
    protected $db;

    /**
     * @var \LoggerManager
     */
    protected $logger;

    /**
     * @var int
     */
    protected $current = 0;

    /**
     * @var int
     */
    protected $processed = 0;

    /**
     * @var int
     */
    protected $start;

    /**
     * @var int
     */
    protected $stop;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var bool
     */
    protected $dry = false;

    /**
     * @var array!bool
     */
    protected $header = true;

    /**
     * @var array
     */
    protected $columns = array ();

    /**
     * @var array
     */
    protected $unique = array ();

    /**
     * @var array
     */
    protected $mapping = array ();

    /**
     * @var array
     */
    protected $alias = array ();

    /**
     * @var null|int
     */
    protected $columnsCount;

    /**
     * @var null|int
     */
    protected $length;

    /**
     * @var null|string
     */
    protected $delimiter = ',';

    /**
     * @var null|string
     */
    protected $enclosure = '"';

    /**
     * @var null|string
     */
    protected $escape = "\n";

    /**
     * @var string
     */
    protected $module;

    /**
     * @var string
     */
    protected $verbose = false;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     *
     */
    public function __construct()
    {
        $this->logger = \LoggerManager::getLogger();
        $this->db = \DBManagerFactory::getInstance();
    }

    /**
     * @param string $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param array $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * @param array $unique
     */
    public function setUnique(array $unique)
    {
        $this->unique = $unique;
    }

    /**
     * @param array $mapping
     */
    public function setMapping(array $mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * @param int $start
     */
    public function setStart($start)
    {
        $this->start = $start;
    }

    /**
     * @param int $stop
     */
    public function setStop($stop)
    {
        $this->stop = $stop;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @param bool $dry
     */
    public function setDry($dry)
    {
        $this->dry = $dry;
    }

    /**
     * @param array $header
     */
    public function setHeader($header)
    {
        $this->header = $header;
    }

    /**
     * @param array $columns
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
    }

    /**
     * @param int|null $length
     */
    public function setLength($length)
    {
        $this->length = $length;
    }

    /**
     * @param null|string $delimiter
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    /**
     * @param null|string $enclosure
     */
    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;
    }

    /**
     * @param null|string $escape
     */
    public function setEscape($escape)
    {
        $this->escape = $escape;
    }

    /**
     * Opens the file to be imported and delegates actions to be performed
     */
    public function import()
    {
        $file = fopen($this->path, 'rb');

        if ($file !== false) {
            try {
                while ($row = fgetcsv($file, $this->length, $this->delimiter, $this->enclosure, $this->escape)) {
                    try {
                        // if the file has a header use the first row as header
                        if (true === $this->header) {
                            $this->header = $row;
                            continue;
                        }

                        $this->current++;

                        if (null === $this->start || $this->current >= $this->start) {
                            if ($this->current === $this->start) {
                                $this->log('info', 'starting migration on row: '.$this->current);
                            } else {
                                $this->log('debug', 'processing row: '.$this->current);
                            }

                            $row = $this->createRow($row);
                            $this->process($row);
                            $this->processed++;
                        }

                        if (null !== $this->stop && $this->current >= $this->stop) {
                            $this->log('info', 'finishing migration on row: '.$this->current);
                            break;
                        }
                    } catch (SkipRowException $ex) {
                        $this->log('info', 'skipping row: '.$this->current);
                    } catch (\Exception $e) {
                        if ($e instanceof TerminateException) {
                            throw $e;
                        }

                        $this->log('fatal', $e->getMessage());
                    }
                }
            } catch (TerminateException $e) {
                $this->log('fatal', $e->getMessage());
                throw $e;
            }
        } else {
            throw new \RuntimeException('Unable to open file: '.$this->path);
        }
    }

    /**
     * @param string $level
     * @param string $message
     */
    protected function log($level, $message)
    {
        $this->logger->__call($level, $message);

        if ($this->verbose) {
            if (null !== $this->output) {
                $this->output->writeln("[$level] $message");
            } else {
                echo "[$level] $message\n";
            }
        }
    }

    /**
     * @param array $row
     */
    protected function process(array $row)
    {
        $row = $this->prepare($row);
        $this->log('debug', print_r($row, true));
        $this->validate($row);
        $bean = $this->find($row);
        $this->populate($bean, $row);
        $this->save($bean);
    }

    /**
     * @param array $row
     */
    protected function validate(array $row)
    {
        // optional
    }

    /**
     * @param array $row
     * @return \SugarBean
     */
    protected function find(array $row)
    {
        $bean = null;

        if (count($this->unique) > 0) {
            $bean = $this->findBean($row);
        }

        if (null === $bean) {
            $bean = $this->createBean($row);
            $this->log('debug', 'bean created');
        } else {
            $this->log('debug', 'bean found');
        }

        return $bean;
    }

    /**
     * @param \SugarBean $bean
     * @param array $row
     */
    protected function populate(\SugarBean $bean, array $row)
    {
        foreach ($row as $field => $value) {
            if (false !== $bean->getFieldDefinition($field)) {
                $bean->{$field} = $this->transform($field, $value);
            }
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    protected function transform($field, $value)
    {
        return $value;
    }

    /**
     * @param string $column
     * @return string
     */
    protected function getFieldName($column)
    {
        return isset($this->mapping[$column]) ? $this->mapping[$column] : $column;
    }

    /**
     * @param array $row
     * @return \SugarBean
     */
    protected function createBean(array $row)
    {
        $bean = \BeanFactory::newBean($this->module);

        foreach ($this->unique as $column) {
            $field = $this->getFieldName($column);
            $bean->{$field} = $row[$column];

            if ($field === 'id') {
                $bean->new_with_id = true;
            }
        }

        return $bean;
    }

    /**
     * @param array $row
     * @return null|\SugarBean
     */
    protected function findBean(array $row)
    {
        $bean = null;

        $query = new \SugarQuery();
        $query->select('id');
        $query->from(\BeanFactory::newBean($this->module));
        $query->limit(1);
        $where = $query->where();

        foreach ($this->unique as $column) {
            $field = $this->getFieldName($column);
            $where->equals($field, $row[$column]);
        }

        $results = $query->execute();

        if (count($results) > 0) {
            $bean = \BeanFactory::retrieveBean($this->module, $results[0]['id']);
        }

        return $bean;
    }

    /**
     * @param array $row
     * @return array
     */
    protected function createRow(array $row)
    {
        if (null === $this->columnsCount) {
            $this->columnsCount = count($this->header);
        }

        return array_combine($this->header, array_pad($row, $this->columnsCount, null));
    }

    /**
     * @param array $row
     * @return array
     */
    protected function prepare(array $row)
    {
        $new = array ();

        foreach ($row as $column => $value) {
            $new[$this->getFieldName($column)] = $value;
        }

        foreach ($this->alias as $column => $field) {
            if (is_array($field)) {
                foreach ($field as $f) {
                    $new[$f] = $new[$column];
                }
            } else {
                $new[$field] = $new[$column];
            }
        }

        return $new;
    }

    /**
     * @param \SugarBean $bean
     */
    protected function save(\SugarBean $bean)
    {
        if (!$this->dry) {
            $bean->save();
        }
    }
}
