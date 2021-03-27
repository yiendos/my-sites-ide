<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Koowa Template Engine
 *
 * @author  Johan Janssens <http://github.com/johanjanssens>
 * @package Koowa\Library\Template\Engine
 */
class KTemplateEngineKoowa extends KTemplateEngineAbstract
{
    /**
     * The engine file types
     *
     * @var string
     */
    protected static $_file_types = array('php');

    /**
     * Template stack
     *
     * Used to track recursive load calls during template evaluation
     *
     * @var array
     */
    protected $_stack;

    /**
     * Locations cache
     *
     * @var array
     */
    protected $_locations;

    /**
     * The template buffer
     *
     * @var KFilesystemStreamBuffer
     */
    protected $_buffer;

    /**
     * Constructor
     *
     * @param KObjectConfig $config   An optional ObjectConfig object with configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        //Reset the stack
        $this->_stack = array();

        //Intercept template exception
        $this->getObject('exception.handler')->addExceptionCallback(array($this, 'handleException'), true);
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config An optional ObjectConfig object with configuration options
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'functions'           => array(
                'import' => array($this, '_import'),
            ),
        ));

        parent::_initialize($config);
    }

    /**
     * Load a template by path
     *
     * @param   string  $url      The template url
     * @throws InvalidArgumentException If the template could not be located
     * @throws RuntimeException         If the template could not be loaded
     * @throws RuntimeException         If the template could not be compiled
     * @return KTemplateEngineKoowa
     */
    public function loadFile($url)
    {
        //Locate the template
        $file = $this->_locate($url);

        //Push the template on the stack
        array_push($this->_stack, $url);

        if(!$cache_file = $this->isCached($file))
        {
            //Load the template
            $content = file_get_contents($file);

            if($content === false) {
                throw new RuntimeException(sprintf('The template "%s" cannot be loaded.', $file));
            }

            //Compile the template
            $content = $this->_compile($content);
            if($content === false) {
                throw new RuntimeException(sprintf('The template "%s" cannot be compiled.', $file));
            }

            $this->_source = $this->cache($file, $content);
        }
        else $this->_source = $cache_file;

        return $this;
    }

    /**
     * Set the template source from a string
     *
     * @param  string  $source The template source
     * @throws RuntimeException If the template could not be compiled
     * @return KTemplateEngineKoowa
     */
    public function loadString($source, $url = null)
    {
        $name = crc32($url ?: $source);

        if(!$file = $this->isCached($name))
        {
            //Compile the template
            $source = $this->_compile($source);

            if($source === false) {
                throw new RuntimeException(sprintf('The template content cannot be compiled.'));
            }

            $file = $this->cache($name, $source);
        }

        $this->_source = $file;

        //Push the template on the stack
        array_push($this->_stack, $url);

        //Store the location
        if($url) {
            $this->_locations[$url] = $file;
        }

        return $this;
    }

    /**
     * Render a template
     *
     * @param   array   $data   The data to pass to the template
     * @throws RuntimeException If the template could not be rendered
     * @return string The rendered template source
     */
    public function render(array $data = array())
    {
        //Set the data
        $this->_data = $data;

        //Evaluate the template
        $content = $this->_evaluate();

        if ($content === false) {
            throw new RuntimeException(sprintf('The template "%s" cannot be evaluated.', $this->_source));
        }

        //Remove the template from the stack
        array_pop($this->_stack);

        return $content;
    }

    /**
     * Cache the compiled template source
     *
     * Write the template content to a file buffer. If cache is enabled the file will be buffer using cache settings
     * If caching is not enabled the file will be written to the temp path using a buffer://temp stream.
     *
     * @param  string $name     The file name
     * @param  string $source   The template source to cache
     * @throws RuntimeException If the template cache path is not writable
     * @throws RuntimeException If template cannot be cached
     * @return string The cached template file path
     */
    public function cache($name, $source)
    {
        if(!$file = parent::cache($name, $source))
        {
            $this->_buffer = $this->getObject('filesystem.stream.factory')->createStream('koowa-buffer://temp', 'w+b');
            $this->_buffer->truncate(0);
            $this->_buffer->write($source);

            $file = $this->_buffer->getPath();
        }

        return $file;
    }

    /**
     * Handle template exceptions
     *
     * If an ErrorException is thrown create a new exception and set the file location to the real template file.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function handleException(Exception &$exception)
    {
        if($template = end($this->_stack))
        {
            if($this->_source == $exception->getFile())
            {
                //Prevents any partial templates from leaking.
                ob_get_clean();

                //Re-create the exception and set the real file path
                if($exception instanceof ErrorException)
                {
                    $class = get_class($exception);
                    $file  = $this->getObject('template.locator.factory')->locate($template) ?: $this->_source;

                    $exception = new KTemplateExceptionError(
                        $exception->getMessage(),
                        $exception->getCode(),
                        $exception->getSeverity(),
                        $file,
                        $exception->getLine(),
                        $exception
                    );
                }
            }
        }
    }

    /**
     * Locate the template
     *
     * @param  string $url The template url
     * @throws InvalidArgumentException
     * @return string   The template real path
     */
    protected function _locate($url)
    {
        if(!isset($this->_locations[$url]))
        {
            //Locate the template
            if (!$file = $this->getObject('template.locator.factory')->locate($url)) {
                throw new InvalidArgumentException(sprintf('The template "%s" cannot be located.', $url));
            }

            $this->_locations[$url] = $file;
        }

        return $this->_locations[$url];
    }

    /**
     * Compile the template source
     *
     * If the a compile error occurs and exception will be thrown if the error cannot be recovered from or if debug
     * is enabled.
     *
     * @param  string $source The template source to compile
     * @throws KTemplateExceptionSyntaxError
     * @return string The compiled template content
     */
    protected function _compile($source)
    {
        //Convert PHP tags
        if (!ini_get('short_open_tag'))
        {
            // convert "<?=" to "<?php echo"
            $find = '/\<\?\s*=\s*(.*?)/';
            $replace = "<?php echo \$1";
            $source = preg_replace($find, $replace, $source);

            // convert "<?" to "<?php"
            $find = '/\<\?(?:php)?\s*(.*?)/';
            $replace = "<?php \$1";
            $source = preg_replace($find, $replace, $source);
        }

        //Compile to valid PHP
        $tokens   = token_get_all($source);

        $result = '';
        for ($i = 0; $i < sizeof($tokens); $i++)
        {
            if(is_array($tokens[$i]))
            {
                list($token, $content) = $tokens[$i];

                switch ($token)
                {
                    //Proxy registered functions through __call()
                    case T_STRING :

                        if(isset($this->_functions[$content]) )
                        {
                            $prev = (array) $tokens[$i-1];
                            $next = (array) $tokens[$i+1];

                            if($next[0] == '(' && $prev[0] !== T_OBJECT_OPERATOR) {
                                $result .= '$this->'.$content;
                                break;
                            }
                        }

                        $result .= $content;
                        break;

                    //Do not allow to use $this context
                    case T_VARIABLE:

                        if ('$this' == $content) {
                            throw new KTemplateExceptionSyntaxError('Using $this when not in object context');
                        }

                        $result .= $content;
                        break;

                    default:
                        $result .= $content;
                        break;
                }
            }
            else $result .= $tokens[$i] ;
        }

        return $result;
    }

    /**
     * Evaluate the template using a simple sandbox
     *
     * @return string The evaluated template content
     */
    protected function _evaluate()
    {
        ob_start();

        extract($this->getData(), EXTR_SKIP);
        include $this->_source;
        $content = ob_get_clean();

        return $content;
    }

    /**
     * Import a partial template
     *
     * If importing a partial merges the data passed in with the data from the call to render. If importing a different
     * template type jump out of engine scope back to the template.
     *
     * @param   string  $url      The template url
     * @param   array   $data     The data to pass to the template
     * @return  string The rendered template content
     */
    protected function _import($url, array $data = array())
    {
        //Store data for reset
        $_data = $this->getData();

        //Qualify relative template url
        if (!parse_url($url, PHP_URL_SCHEME))
        {
            if (!$base = end($this->_stack)) {
                throw new \RuntimeException('Cannot qualify partial template url');
            }

            $url = $this->getObject('template.locator.factory')
                ->createLocator($base)
                ->qualify($url, $base);

            if(array_search($url, $this->_stack))
            {
                throw new \RuntimeException(sprintf(
                    'Template recursion detected while importing "%s" in "%s"', $url, $base
                ));
            }
        }

        $type = pathinfo( $this->_locate($url), PATHINFO_EXTENSION);
        $data = array_merge((array) $this->getData(), $data);

        //If the partial requires a different engine create it and delegate
        if(!in_array($type, $this->getFileTypes()))
        {
            $result = $this->getTemplate()
                ->loadFile($url)
                ->render($data);
        }
        else $result = $this->loadFile($url)->render($data);

        //Reset the data
        $this->_data = $_data;

        return $result;
    }
}