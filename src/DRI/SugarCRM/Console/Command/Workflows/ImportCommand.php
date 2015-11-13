<?php

namespace DRI\SugarCRM\Console\Command\Workflows;

use DRI\SugarCRM\Console\Command\ApplicationCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Emil Kilhage
 */
class ImportCommand extends ApplicationCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('workflows:import');
        $this->addArgument('id', InputArgument::OPTIONAL, 'if you only want to import a single workflow');
        $this->addOption('directory', 'D', InputOption::VALUE_REQUIRED, 'target directory relative from the docroot', '../config/workflows');
        $this->addOption('purge', 'P', InputOption::VALUE_NONE, 'purges all workflows that does not exist in files');
        $this->addOption('dry', null, InputOption::VALUE_NONE, 'run the script in dry mode (no changes will be made)');
        $this->setDescription('Export workflow records from .json files');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!is_dir($input->getOption('directory'))) {
            mkdir($input->getOption('directory'), 0755, true);
        }

        if (null !== $this->input->getArgument('id')) {
            $this->import($this->input->getArgument('id'));
        } else {
            $ids = $this->listIds();

            if (count($ids) > 0) {
                array_map(array ($this, 'import'), $ids);
            } else {
                $output->writeln('<info>no workflows to import</info>');
            }

            if ($input->getOption('purge')) {
                $this->purge($ids);
            }
        }
    }

    /**
     * @param array $ids
     */
    private function purge(array $ids)
    {
        $query = new \SugarQuery();
        $query->from(new \WorkFlow());
        $query->select('id');
        $query->where()->notIn('id', $ids);

        foreach ($query->execute() as $row) {
            $workflow = \BeanFactory::retrieveBean('WorkFlow', $row['id']);

            if ($workflow) {
                $this->output->writeln("<comment>- Deleting {$workflow->module_dir} with id {$workflow->id}</comment>");

                if (!$this->input->getOption('dry')) {
                    $workflow->mark_deleted($workflow->id);
                }
            }
        }
    }

    /**
     * @return array
     * @throws \SugarQueryException
     */
    public function listIds()
    {
        $files =  glob(sprintf('%s/*.json', $this->input->getOption('directory')));

        return array_map(function ($file) {
            return basename($file, '.json');
        }, $files);
    }

    /**
     * @param string $id
     * @throws \Exception
     */
    public function import($id)
    {
        $file = sprintf('%s/%s.json', $this->input->getOption('directory'), $id);

        if (!file_exists($file)) {
            throw new \Exception("Unable to find file: $file");
        }

        $this->output->writeln("<comment>- Importing WorkFlow with id $id</comment>");

        $data = $this->readFile($file);

        /** @var \WorkFlow $workflow */
        $workflow = $this->findRecord('WorkFlow', $id);

        $this->syncRecord($workflow, $data);
    }

    /**
     * @param \SugarBean $bean
     * @param string    $link
     * @param array     $records
     */
    private function syncLink(\SugarBean $bean, $link, array $records)
    {
        $bean->load_relationship($link);

        $current = $bean->$link->getBeans();

        foreach ($records as $id => $data) {
            $record = $this->findRecord($bean->$link->getRelatedModuleName(), $id);

            if (isset($current[$id])) {
                unset($current[$id]);
            }

            $this->syncRecord($record, $data);
        }

        $this->deleteRecords($current);
    }

    /**
     * @param \SugarBean $bean
     * @param array      $data
     * @return bool
     */
    private function populateData(\SugarBean $bean, array $data)
    {
        $changes = false;

        foreach ($data as $fieldName => $value) {
            if ($bean->field_defs[$fieldName]['type'] === 'link') {
                continue;
            }

            if ($bean->$fieldName != $value) {

                if ($this->input->getOption('verbose')) {
                    $message = sprintf(
                        '<comment>   * updating field %s to \'%s\' on record with id %s in module %s, previous value: \'%s\'</comment>',
                        $fieldName,
                        $value,
                        $bean->id,
                        $bean->module_dir,
                        $bean->$fieldName
                    );
                    $this->output->writeln($message);
                }

                $bean->$fieldName = $value;
                $changes = true;
            }
        }

        return $changes;
    }

    /**
     * @param \SugarBean $bean
     * @param bool       $changes
     */
    private function saveRecord(\SugarBean $bean, $changes)
    {
        if ($bean->new_with_id) {
            $this->output->writeln("<comment>   * creating {$bean->module_dir} with id {$bean->id}</comment>");

            if (!$this->input->getOption('dry')) {
                $bean->save();
            }
        } elseif ($changes) {
            $this->output->writeln("<comment>   * updating {$bean->module_dir} with id {$bean->id}</comment>");

            if (!$this->input->getOption('dry')) {
                $bean->save();
            }
        } else {
            $this->output->writeln("<info>   * {$bean->module_dir} with id {$bean->id} is already synchronized</info>");
        }
    }

    /**
     * @param string $moduleName
     * @param string $id
     * @return \SugarBean
     */
    private function findRecord($moduleName, $id)
    {
        $workflow = \BeanFactory::retrieveBean($moduleName, $id, array(), false);

        if (null === $workflow) {
            $workflow = \BeanFactory::newBean($moduleName);
            $workflow->id = $id;
            $workflow->new_with_id = true;
        } elseif ($workflow->deleted === 1) {
            $workflow->deleted = 0;
        }

        return $workflow;
    }

    /**
     * @param \SugarBean[] $records
     */
    private function deleteRecords(array $records)
    {
        foreach ($records as $record) {
            $this->output->writeln("<comment>   * deleting {$record->module_dir} with id {$record->id}</comment>");

            if (!$this->input->getOption('dry')) {
                $record->mark_deleted($record->id);
            }
        }
    }

    /**
     * @param string $file
     * @return array
     * @throws \Exception
     */
    private function readFile($file)
    {
        $content = file_get_contents($file);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('json parse error: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * @param \SugarBean $bean
     * @param array      $data
     */
    private function syncRecord(\SugarBean $bean, array $data)
    {
        $changes = $this->populateData($bean, $data);

        $this->saveRecord($bean, $changes);

        foreach ($data as $fieldName => $value) {
            if ($bean->field_defs[$fieldName]['type'] === 'link') {
                $this->syncLink($bean, $fieldName, $value);
            }
        }
    }
}
