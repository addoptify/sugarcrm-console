<?php

namespace DRI\Migration\Command;

use DRI\Migration\AbstractCsvMigration;
use DRI\SugarCRM\Console\Command\ApplicationCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

require_once "custom/include/DRI/Migration/AbstractCsvMigration.php";

/**
 * @author Emil Kilhage
 */
class AbstractMigrationCommand extends ApplicationCommand
{
    /**
     * @var string
     */
    protected $className;

    /**
     * @var int
     */
    protected $parallel = 1;

    /**
     * @var int
     */
    protected $batchSize = 10;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var array
     */
    private $processes = array ();

    /**
     * @var array
     */
    private $timesRestarted = array ();

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED)
            ->addOption('parallel', 'P', InputOption::VALUE_REQUIRED, '', $this->parallel)
            ->addOption('batch', 'B', InputOption::VALUE_NONE, '')
            ->addOption('batch-size', 'S', InputOption::VALUE_REQUIRED, '', $this->batchSize)
            ->addOption('header', null, InputOption::VALUE_NONE, '')
            ->addOption('start', null, InputOption::VALUE_REQUIRED, '')
            ->addOption('stop', null, InputOption::VALUE_REQUIRED, '')
            ->addOption('length', null, InputOption::VALUE_REQUIRED, '')
            ->addOption('delimiter', null, InputOption::VALUE_REQUIRED, '')
            ->addOption('enclosure', null, InputOption::VALUE_REQUIRED, '')
            ->addOption('escape', null, InputOption::VALUE_REQUIRED, '')
            ->addOption('unique', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, '')
            ->addOption('column', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, '')
            ->addOption('default', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, '')
            ->addOption('map', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, '')
            ->addOption('alias', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, '')
            ->addOption('dry', null, InputOption::VALUE_NONE, '')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        if ($input->getOption('batch')) {
            $this->batch();
        } else {
            $this->import();
        }
    }

    /**
     * @return string
     */
    private function getProcessParams()
    {
        static $bool = array (
            'dry',
            'verbose',
            'header'
        );

        static $string = array (
            'parallel',
            'batch-size',
            'length',
            'delimiter',
            'enclosure',
            'escape'
        );

        static $array = array (
            'unique',
            'column',
            'map',
            'alias',
            'default',
        );

        $params = '';

        foreach ($bool as $param) {
            if ($this->input->getOption($param)) {
                $params .= ' --'.$param;
            }
        }

        foreach ($string as $param) {
            if ($this->input->getOption($param)) {
                $params .= ' --'.$param.'='.$this->input->getOption($param);
            }
        }

        foreach ($array as $param) {
            if (count($this->input->getOption($param)) > 0) {
                foreach ($this->input->getOption($param) as $enable) {
                    $params .= ' --'.$param.'='.$enable;
                }
            }
        }

        return $params;
    }

    /**
     * @param Process $process
     * @param int     $i
     * @param bool    $success
     */
    protected function checkProcess(Process $process, $i, $success)
    {
        $hash = spl_object_hash($process);
        if (!$success && (!isset($this->timesRestarted[$hash])
                || $this->timesRestarted[$hash] <= 3)) {
            sleep(10);

            if (!isset($this->timesRestarted[$hash])) {
                $this->timesRestarted[$hash] = 0;
            }

            ++$this->timesRestarted[$hash];
            $process->restart();
            echo $process->getOutput();
        } else {
            if (isset($this->timesRestarted[$hash])) {
                unset($this->timesRestarted[$hash]);
            }

            if ($process->isRunning()) {
                $process->stop();
            }

            unset($this->processes[$i]);
            echo $process->getOutput();
        }
    }

    /**
     *
     */
    protected function cycle()
    {
        sleep(1);

        foreach ($this->processes as $i => $process) {
            if (!$process->isRunning()) {
                $this->checkProcess($process, $i, $process->isSuccessful());
            } else {
                try {
                    $process->checkTimeout();
                } catch (ProcessTimedOutException $e) {
                    $this->checkProcess($process, $i, false);
                }
            }
        }
    }

    /**
     *
     */
    protected function wait()
    {
        while (count($this->processes) >= (int) $this->input->getOption('parallel')) {
            $this->cycle();
        }
    }

    /**
     * @param string $command
     * @param bool   $allowParallel
     * @param int    $try
     *
     * @return string
     */
    protected function runCommand($command, $allowParallel = false, $try = 1)
    {
        $that = $this;

        $process = new Process($command);

        try {
            $this->output->writeln($command);
            if ($this->input->getOption('parallel') > 1 && $allowParallel) {
                $process->start();

                $this->processes[] = $process;

                $this->wait();
            } else {
                $process->mustRun(function ($type, $data) use ($that) {
                    $that->output->write($data);
                });

                $output = $process->getOutput();

                echo $output;

                return $output;
            }
        } catch (ProcessFailedException $e) {
            echo "$e\n";

            if ($try === 3) {
                throw $e;
            }

            $this->output->writeln("excecution of command failed, sleeping 10 sec and retrying, try: $try");
            sleep(10);
            return $this->runCommand($command, $allowParallel, ++$try);
        }
    }

    /**
     *
     */
    private function import()
    {
        $migration = $this->createMigration();
        $this->configureMigration($migration);
        $migration->import();
    }

    /**
     * @return AbstractCsvMigration
     */
    private function createMigration()
    {
        return new $this->className();
    }

    /**
     * @param AbstractCsvMigration $migration
     */
    private function configureMigration(AbstractCsvMigration $migration)
    {
        $migration->setPath($this->input->getArgument('path'));
        $migration->setStart($this->input->getOption('start'));
        $migration->setStop($this->input->getOption('stop'));
        $migration->setVerbose($this->input->getOption('verbose'));
        $migration->setOutput($this->output);

        if ($this->input->getOption('length')) {
            $migration->setLength($this->input->getOption('length'));
        }

        if ($this->input->getOption('delimiter')) {
            $migration->setDelimiter($this->input->getOption('delimiter'));
        }

        if ($this->input->getOption('enclosure')) {
            $migration->setEnclosure($this->input->getOption('enclosure'));
        }

        if ($this->input->getOption('escape')) {
            $migration->setEscape($this->input->getOption('escape'));
        }

        if ($this->input->getOption('header')) {
            $migration->setHeader($this->input->getOption('header'));
        }

        $migration->setDry($this->input->getOption('dry'));

        if (count($this->input->getOption('map')) > 0) {
            $mapping = array();
            foreach ($this->input->getOption('map') as $item) {
                list($column, $field) = explode(':', $item);
                $mapping[$column] = $field;
            }

            $migration->setMapping($mapping);
        }

        if (count($this->input->getOption('alias')) > 0) {
            $alias = array();
            foreach ($this->input->getOption('alias') as $item) {
                list($column, $field) = explode(':', $item);

                if (isset($alias[$column])) {
                    if (is_array($alias[$column])) {
                        $alias[$column][] = $field;
                    } else {
                        $alias[$column] = array($alias[$column], $field);
                    }
                } else {
                    $alias[$column] = $field;
                }
            }

            $migration->setAlias($alias);
        }

        if (count($this->input->getOption('unique')) > 0) {
            $migration->setUnique($this->input->getOption('unique'));
        }

        if (count($this->input->getOption('column')) > 0) {
            $migration->setColumns($this->input->getOption('column'));
        }
    }

    /**
     *
     */
    protected function batch()
    {
        $path = $this->input->getArgument('path');
        $count = (int)exec("wc -l '{$path}'");
        $batchSize = $this->input->getOption('batch-size');

        $stop = $this->input->getOption('stop');
        if ($stop && $stop < $count) {
            $count = $stop;
        }

        $n = $count / $batchSize;
        $n = floor($n);

        $first = $this->input->getOption('start') ? : 0;
        $i = floor($first / $batchSize);

        $commands = array();
        for (; $i <= $n; $i++) {
            $start = $i * $batchSize;
            $stop = $start + $batchSize - 1;

            if ($start < $first) {
                $start = $first;
            }

            if ($stop > $count) {
                $stop = $count;
            }

            if ($stop === $start) {
                break;
            }

            $command = sprintf(
                'php -d memory_limit=%s ../bin/sugarcrm %s %s %s --start %d --stop %d',
                ini_get('memory_limit'),
                $this->getName(),
                $path,
                $this->getProcessParams(),
                $start,
                $stop
            );

            $commands[] = $command;
        }

        foreach ($commands as $command) {
            $this->runCommand($command, true);
        }
    }
}
