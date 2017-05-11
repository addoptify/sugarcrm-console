<?php

namespace DRI\SugarCRM\Console\Generator;

use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Emil Kilhage
 */
abstract class AbstractGenerator
{
    /**
     * @var array
     */
    protected $params = array ();

    /**
     * @var array
     */
    protected $required = array ();

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $templatePath;

    /**
     * @var string
     */
    protected $type;

    public function __construct()
    {
        $this->templatePath = dirname(dirname((__DIR__))).'/Resources/tpls/'.$this->type;
        $this->twig = new \Twig_Environment(new \Twig_Loader_Filesystem("$this->templatePath"));
        $this->filesystem = new Filesystem();
    }

    /**
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     *
     */
    abstract public function generate();

    /**
     * @param $file
     * @param $content
     * @param array $arguments
     *
     * @throws \Exception
     */
    protected function writeFile($file, $content, array $arguments)
    {
        if ($this->filesystem->exists($file) && !$arguments['force']) {
            echo "$file already exists, skipping \n";
        } elseif (!$arguments['dry']) {
            $this->filesystem->dumpFile($file, $content);
            echo "$file has been created! \n";
        } else {
            echo "$file will be created! \n";
        }
    }

    /**
     * @param string $file
     * @param array $arguments
     * @return string
     */
    protected function getPath($file, $arguments)
    {
        foreach ($arguments as $key => $value) {
            if (is_string($value)) {
                $file = str_replace(strtoupper($key), $value, $file);
            }
        }

        $file = str_replace($this->templatePath.'/', '', $file);

        return $file;
    }

    /**
     * Function to retrieve all file names of matching pattern in a directory (and it's subdirectories)
     * example: getFiles('./modules', '.+/EditView.php/'); // grabs all EditView.phps
     *
     * @param string $dir directory to look in [ USE ./ in front of the $dir! ]
     * @param string $pattern optional pattern to match against
     * @return array
     */
    protected function getFiles($dir, $pattern = null)
    {
        $files = array ();

        if (!is_dir($dir)) {
            return array ();
        }

        $d = dir($dir);

        while ($e = $d->read())
        {
            if (0 === strpos($e, '.')) {
                continue;
            }

            $file = $dir . '/' . $e;
            if (is_dir($file)) {
                $files = array_merge($files, $this->getFiles($file, $pattern));
            } else {
                if (empty($pattern)) {
                    $files[] = $file;
                } elseif (preg_match($pattern, $file)) {
                    $files[] = $file;
                }
            }
        }

        foreach ($files as &$file) {
            $file = str_replace($this->templatePath.'/', '', $file);
        }

        return $files;
    }
}
