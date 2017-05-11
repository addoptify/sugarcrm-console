<?php

namespace DRI\SugarCRM\Console\Generator;

/**
 * This file is part of the DRI Sugar CRM Module Creator library
 *
 * Copyright (c) 2013 Emil Kilhage, DRI Nordic
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT
 *   See LICENSE shipped with this library.
 */
class ModuleCreator extends AbstractGenerator
{
    /**
     * @var array
     */
    private static $arguments = array (
        'assignable' => false,
        'team_security' => false,
        'force' => false,
        'help' => false,
        'audited' => false,
        'dry' => false,
        'importable' => false,
        'template' => 'basic',
        'template_class_name' => 'Basic',
        'templates' => array ('basic'),
        'defined_fields' => array (),
        'object_name' => null,
        'module_name' => null,
        'table_name' => null,
        'object_name_auc' => null,
        'module_name_auc' => null,
    );

    /**
     * @var string
     */
    protected $type = 'new-module';

    /**
     *
     * @param array $args
     * @return array
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public static function parseArgs(array $args)
    {
        global $sugar_flavor;

        $arguments = array_merge(self::$arguments, $args);

        if (empty($arguments['module_name']))
        {
            $arguments['module_name'] = $arguments['object_name'] . 's';
        }

        if (empty($arguments['table_name']))
        {
            $arguments['table_name'] = strtolower($arguments['module_name']);
        }

        $arguments['object_name_auc'] = strtoupper($arguments['object_name']);
        $arguments['module_name_auc'] = strtoupper($arguments['module_name']);
        $arguments['template_class_name'] = ucfirst($arguments['template']);

        $arguments['team_security'] = !empty($arguments['team_security']) && $sugar_flavor !== 'CE';

        if ($arguments['template'] !== 'basic')
        {
            $arguments['templates'][] = $arguments['template'];
        }

        if ($arguments['team_security'])
        {
            $arguments['templates'][] = 'team_security';
        }

        if ($arguments['assignable'])
        {
            $arguments['templates'][] = 'assignable';
        }

        if (empty($arguments['translation_plural'])) {
            $arguments['translation_plural'] = self::translate($arguments['module_name']);
        }

        if (empty($arguments['translation_singular'])) {
            $arguments['translation_singular'] = self::translate($arguments['object_name']);
        }

        return $arguments;
    }

    /**
     * @param string $module
     * @return string
     */
    public static function translate($module)
    {
        $module = str_replace('DRI_', '', $module);
        $module = preg_replace('/([a-z]{1})([A-Z]{1})/', '$1 $2', $module);
        return $module;
    }

    /**
     * {@inheritdoc}
     */
    public function generate()
    {
        $this->addModule($this->params);
    }

    /**
     * @global string $sugar_flavor
     * @param array $arguments
     */
    public function addModule(array $arguments = array ())
    {
        $arguments = self::parseArgs($arguments);

        foreach ($arguments['templates'] as $template) {
            \VardefManager::addTemplate(
                $arguments['module_name'],
                $arguments['object_name'],
                $template
            );
        }

        $arguments['defined_fields'] = array_keys($GLOBALS['dictionary'][$arguments['object_name']]['fields']);

        $files = $this->getFiles($this->templatePath, '/\.php/');

        foreach ($files as $from) {
            $to = $this->getPath($from, $arguments);
            $content = $this->twig->render($from, $arguments);
            $this->writeFile($to, $content, $arguments);
        }
    }

    /**
     * @param array $arguments
     */
    public function migrateModule(array $arguments = array ())
    {
        $arguments = self::parseArgs($arguments);

        $files = $this->getFiles($this->templatePath.'/modules/MODULE_NAME/clients', '/\.php/');

        foreach ($files as $from) {
            $to = $this->getPath($from, $arguments);
            $content = $this->twig->render($from, $arguments);
            $this->writeFile($to, $content, $arguments);
        }
    }
}
