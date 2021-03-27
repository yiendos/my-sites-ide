<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Template
 *
 * @author  Johan Janssens <http://github.com/johanjanssens>
 * @package Koowa\Library\Template
 */
class KTemplate extends KTemplateAbstract implements KTemplateFilterable, KTemplateHelperable, KObjectInstantiable
{
    /**
     * The template parameters
     *
     * @var KObjectConfigInterface
     */
    private $__parameters;

    /**
     * List of template filters
     *
     * @var array
     */
    private $__filters;

    /**
     * Filter queue
     *
     * @var	KObjectQueue
     */
    private $__filter_queue;

    /**
     * Excluded types
     *
     * @var array
     */
    protected $_excluded_types;

    /**
     * Constructor
     *
     * Prevent creating instances of this class by making the constructor private
     *
     * @param KObjectConfig $config   An optional ObjectConfig object with configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        //Set the filter queue
        $this->__filter_queue = $this->getObject('lib:object.queue');

        //Add the filters
        $this->addFilters($config->filters);

        //Set the parameters
        $this->setParameters($config->parameters);

        //Set the excluded types
        $this->_excluded_types = KObjectConfig::unbox($config->excluded_types);
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config  An optional ObjectConfig object with configuration options.
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'parameters' => array(),
            'filters'    => array(),
            'functions'  => array(
                'escape'     => array($this, 'escape'),
                'helper'     => array($this, 'invoke'),
                'parameters' => array($this, 'getParameters')
            ),
            'cache'           => false,
            'cache_namespace' => 'koowa',
            'excluded_types'  => array('html'),
        ));

        parent::_initialize($config);
    }

    /**
     * Instantiate the translator and decorate with the cache decorator if cache is enabled.
     *
     * @param   KObjectConfigInterface  $config   A ObjectConfig object with configuration options
     * @param   KObjectManagerInterface	$manager  A ObjectInterface object
     * @return  KTemplateInterface
     */
    public static function getInstance(KObjectConfigInterface $config, KObjectManagerInterface $manager)
    {
        $class    = $manager->getClass($config->object_identifier);
        $instance = new $class($config);
        $config   = $instance->getConfig();

        if($config->cache)
        {
            $class = $manager->getClass('lib:template.cache');

            if($class::isSupported())
            {
                $instance = $instance->decorate('lib:template.cache');
                $instance->setNamespace($config->cache_namespace);
            }
        }

        return $instance;
    }

    /**
     * Load a template by path
     *
     * @param   string  $url      The template url
     * @throws \InvalidArgumentException If the template could not be located
     * @return KTemplate
     */
    public function loadFile($url)
    {
        //Locate the template
        $locator = $this->getObject('template.locator.factory')->createLocator($url);

        if (!$file = $locator->locate($url)) {
            throw new InvalidArgumentException(sprintf('The template "%s" cannot be located.', $url));
        }

        $type = pathinfo($file, PATHINFO_EXTENSION);

        if(!in_array($type, $this->_excluded_types))
        {
            //Create the template engine
            $config = array(
                'template' => $this,
                'functions' => $this->_functions
            );

            $this->_source = $this->getObject('template.engine.factory')
                ->createEngine($file, $config)
                ->loadFile($url);
        }
        else parent::loadFile($url);

        return $this;
    }

    /**
     * Set the template content from a string
     *
     * Overrides TemplateInterface:loadString() and allows to define the type of content. If a type is set
     * an engine for the type will be created. If no type is set we will assumed the content has already been
     * rendered.
     *
     * @param  string   $source  The template content
     * @param  integer  $type    The template type.
     * @param  string   $url     The template url
     * @return KTemplate
     */
    public function loadString($source, $type = null, $url = null)
    {
        if($type && !in_array($type, $this->_excluded_types))
        {
            //Create the template engine
            $config = array(
                'template'  => $this,
                'functions' => $this->_functions
            );

            $this->_source = $this->getObject('template.engine.factory')
                ->createEngine($type, $config)
                ->loadString($source,  $url);
        }
        else parent::loadString($source);

        return $this;
    }

    /**
     * Render the template
     *
     * @param   array   $data     An associative array of data to be extracted in local template scope
     * @return string The rendered template source
     */
    public function render(array $data = array())
    {
        parent::render($data);

        if($this->_source instanceof KTemplateEngineInterface)
        {
            $this->_source = $this->_source->render($data);
            $this->_source = $this->filter();
        }

        return $this->_source;
    }

    /**
     * Filter template content
     *
     * @return string The filtered template source
     */
    public function filter()
    {
        if(is_string($this->_source))
        {
            //Filter the template
            foreach($this->__filter_queue as $filter) {
                $filter->filter($this->_source);
            }
        }

        return $this->_source;
    }

    /**
     * Escape a string
     *
     * By default the function uses htmlspecialchars to escape the string
     *
     * @param string $string String to to be escape
     * @return string Escaped string
     */
    public function escape($string)
    {
        if(is_string($string)) {
            $string = htmlspecialchars($string, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8', false);
        }

        return $string;
    }

    /**
     * Invoke a template helper
     *
     * This function accepts a partial identifier, in the form of helper.method or schema:package.helper.method. If
     * a partial identifier is passed a full identifier will be created using the template identifier.
     *
     * If the state have the same string keys, then the parameter value for that key will overwrite the state.
     *
     * @param    string   $identifier Name of the helper, dot separated including the helper function to call
     * @param    array    $params     An optional associative array of functions parameters to be passed to the helper
     * @return   string   Helper output
     * @throws   \BadMethodCallException If the helper function cannot be called.
     */
    public function invoke($identifier, $params = array())
    {
        //Get the function and helper based on the identifier
        $parts      = explode('.', $identifier);
        $function   = array_pop($parts);
        $identifier = array_pop($parts);

        //Handle schema:package.helper.function identifiers
        if(!empty($parts)) {
            $identifier = implode('.', $parts).'.template.helper.'.$identifier;
        }

        $helper = $this->createHelper($identifier, $params);

        //Call the helper function
        if (!is_callable(array($helper, $function))) {
            throw new BadMethodCallException(get_class($helper) . '::' . $function . ' not supported.');
        }

        //Merge the parameters if helper asks for it
        if ($helper instanceof KTemplateHelperParameterizable) {
            $params = array_merge($this->getParameters()->toArray(), $params);
        }

        return $helper->$function($params);
    }

    /**
     * Set the template parameters
     *
     * @param  array $parameters Set the template parameters
     * @return KTemplate
     */
    public function setParameters($parameters)
    {
        $this->__parameters = new KObjectConfig($parameters);
        return $this;
    }

    /**
     * Get the model state object
     *
     * @return KObjectConfigInterface
     */
    public function getParameters()
    {
        return $this->__parameters;
    }

    /**
     * Get a template helper
     *
     * @param    mixed $helper ObjectIdentifierInterface
     * @param    array $config An optional associative array of configuration settings
     *
     * @throws  UnexpectedValueException
     * @return  KTemplateHelperInterface
     */
    public function createHelper($helper, $config = array())
    {
        //Create the complete identifier if a partial identifier was passed
        if (is_string($helper) && strpos($helper, '.') === false)
        {
            $identifier = $this->getIdentifier()->toArray();

            if($identifier['type'] != 'lib') {
                $identifier['path'] = array('template', 'helper');
            } else {
                $identifier['path'] = array('helper');
            }

            $identifier['name'] = $helper;
        }
        else $identifier = $this->getIdentifier($helper);

        //Create the template helper
        $helper = $this->getObject($identifier, array_merge($config, array('template' => $this)));

        //Check the helper interface
        if (!($helper instanceof KTemplateHelperInterface))
        {
            throw new UnexpectedValueException(
                "Template helper $identifier does not implement KTemplateHelperInterface"
            );
        }

        return $helper;
    }

    /**
     * Add template filters
     *
     * @param  array $filters A mixed array of template filters
     * @return KTemplateAbstract
     */
    public function addFilters($filters)
    {
        foreach((array)KObjectConfig::unbox($filters) as $key => $value)
        {
            if (is_numeric($key)) {
                $this->addFilter($value);
            } else {
                $this->addFilter($key, $value);
            }
        }

        return $this;
    }

    /**
     * Attach a filter for template transformation
     *
     * @param   mixed $filter An object that implements ObjectInterface, ObjectIdentifier object
     *                         or valid identifier string
     * @param   array $config An optional associative array of configuration settings
     * @throws UnexpectedValueException
     * @return KTemplateAbstract
     */
    public function addFilter($filter, $config = array())
    {
        //Create the complete identifier if a partial identifier was passed
        if (is_string($filter) && strpos($filter, '.') === false)
        {
            $identifier = $this->getIdentifier()->toArray();
            $identifier['path'] = array('template', 'filter');
            $identifier['name'] = $filter;

            $identifier = $this->getIdentifier($identifier);
        }
        else $identifier = $this->getIdentifier($filter);

        if (!$this->hasFilter($identifier))
        {
            $filter = $this->getObject($identifier, array_merge($config, array('template' => $this)));

            if (!($filter instanceof KTemplateFilterInterface))
            {
                throw new UnexpectedValueException(
                    "Template filter $identifier does not implement KTemplateFilterInterface"
                );
            }

            //Store the filter
            $this->__filters[(string)$identifier] = $filter;

            //Enqueue the filter
            $this->__filter_queue->enqueue($filter, $filter->getPriority());
        }

        return $this;
    }

    /**
     * Check if a filter exists
     *
     * @param   mixed $filter An object that implements ObjectInterface, ObjectIdentifier object
     *                         or valid identifier string
     * @return  boolean	TRUE if the filter exists, FALSE otherwise
     */
    public function hasFilter($filter)
    {
        //Create the complete identifier if a partial identifier was passed
        if (is_string($filter) && strpos($filter, '.') === false)
        {
            $identifier = $this->getIdentifier()->toArray();
            $identifier['path'] = array('template', 'filter');
            $identifier['name'] = $filter;

            $identifier = $this->getIdentifier($identifier);
        }
        else $identifier = $this->getIdentifier($filter);

        return isset($this->__filters[(string)$filter]);
    }

    /**
     * Get a filter by identifier
     *
     * @param   mixed $filter       An object that implements ObjectInterface, ObjectIdentifier object
     *                              or valid identifier string
     * @throws UnexpectedValueException
     * @return KTemplateFilterInterface|null
     */
    public function getFilter($filter)
    {
        $result = null;

        //Create the complete identifier if a partial identifier was passed
        if (is_string($filter) && strpos($filter, '.') === false)
        {
            $identifier = $this->getIdentifier()->toArray();
            $identifier['path'] = array('template', 'filter');
            $identifier['name'] = $filter;

            $identifier = $this->getIdentifier($identifier);
        }
        else $identifier = $this->getIdentifier($filter);

        if(isset($this->__filters[(string)$identifier])) {
            $result = $this->__filters[(string)$identifier];
         }

        return $result;
    }

    /**
     * Deep clone of this instance
     *
     * @return void
     */
    public function __clone()
    {
        parent::__clone();

        $this->__parameters = clone $this->__parameters;
        $this->__filter_queue = clone $this->__filter_queue;
    }
}