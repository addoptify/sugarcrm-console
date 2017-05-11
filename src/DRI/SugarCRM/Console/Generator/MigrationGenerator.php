<?php

namespace DRI\SugarCRM\Console\Generator;

/**
 * @author Emil Kilhage
 */
class MigrationGenerator extends AbstractGenerator
{
    const SOURCE = 'source';
    const MODULE = 'module';
    const FORMAT = 'format';
    const FORCE = 'force';
    const DRY = 'dry';

    /**
     * @var array
     */
    protected $params = array (
        self::SOURCE => null,
        self::MODULE => null,
        self::FORMAT => 'csv',
        self::FORCE => false,
        self::DRY => false,
    );

    /**
     * @var array
     */
    protected $required = array (
        self::SOURCE,
        self::MODULE,
    );

    /**
     * @var string
     */
    protected $type = 'migration';

    /**
     *
     */
    public function generate()
    {
        $this->prepare();
        $this->parse();
    }

    public function parse()
    {
        $files = $this->getFiles($this->templatePath, '/\.php/');

        foreach ($files as $from) {
            $to = $this->getPath($from, $this->params);
            $content = $this->twig->render($from, $this->params);
            $this->writeFile($to, $content, $this->params);
        }
    }

    protected function prepare()
    {
        $this->params[self::SOURCE.'_lower'] = strtolower($this->params[self::SOURCE]);
        $this->params[self::SOURCE.'_upper'] = strtoupper($this->params[self::SOURCE]);
        $this->params[self::MODULE.'_lower'] = strtolower($this->params[self::MODULE]);
        $this->params[self::MODULE.'_upper'] = strtoupper($this->params[self::MODULE]);
    }
}
