<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Object Bootstrapper
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Object\Bootstrapper
 */
final class KObjectBootstrapper extends KObject implements KObjectBootstrapperInterface, KObjectSingleton
{
    /**
     * List of registered directories
     *
     * @var array
     */
    protected $_directories;

    /**
     * List of registered components
     *
     * @var array
     */
    protected $_components;

    /**
     * Component/domain map
     *
     * @var array
     */
    protected $_domains;

    /**
     * Namespace/path map
     *
     * @var array
     */
    protected $_namespaces;

    /**
     * List of registered applications
     *
     * @var array
     */
    protected $_applications;

    /**
     * List of config files
     *
     * @var array
     */
    protected $_files;

    /**
     * List of identifier aliases
     *
     * @var array
     */
    protected $_aliases;

    /**
     * Bootstrapped status.
     *
     * @var bool
     */
    protected $_bootstrapped;

    /**
     * Constructor.
     *
     * @param KObjectConfig $config An optional ObjectConfig object with configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        $this->_bootstrapped = false;

        //Force a reload if cache is enabled and we have already bootstrapped
        if($config->force_reload && $config->bootstrapped)
        {
            $config->bootstrapped   = false;
            $config->directories    = array();
            $config->components     = array();
            $config->domains        = array();
            $config->namespaces     = array();
            $config->files          = array();
            $config->aliases        = array();
            $config->identifiers    = array();
            $config->applications   = array();
        }

        $this->_directories  = KObjectConfig::unbox($config->directories);
        $this->_components   = KObjectConfig::unbox($config->components);
        $this->_domains      = KObjectConfig::unbox($config->domains);
        $this->_namespaces   = KObjectConfig::unbox($config->namespaces);
        $this->_files        = KObjectConfig::unbox($config->files);
        $this->_applications = KObjectConfig::unbox($config->applications);
        $this->_aliases      = KObjectConfig::unbox($config->aliases);
        $this->_identifiers  = KObjectConfig::unbox($config->identifiers);

    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config An optional ObjectConfig object with configuration options
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'force_reload' => false,
            'bootstrapped' => false,
            'directories'  => array(),
            'components'   => array(),
            'domains'      => array(),
            'namespaces'   => array(),
            'files'        => array(),
            'aliases'      => array(),
            'identifiers'  => array(),
            'applications' => array(),
        ));

        parent::_initialize($config);
    }

    /**
     * Bootstrap
     *
     * The bootstrap cycle can be run only once
     *
     * @return void
     */
    public function bootstrap()
    {
        $identifiers = $this->_identifiers;
        $aliases     = $this->_aliases;

        if(!$this->isBootstrapped())
        {
            $manager = $this->getObject('manager');

            /*
             * Setup the component class locator
             *
             * Locators are always setup as the  cannot be cached in the registry objects.
             */
            foreach($this->_namespaces as $namespace => $path) {
                $manager->getClassLoader()->getLocator('component')->registerNamespace($namespace, $path);
            }

            /*
             * Load resources
             *
             * If cache is enabled and the bootstrapper has been run we do not reload the config resources
             */
            if(!$this->getConfig()->bootstrapped)
            {
                $factory = $this->getObject('object.config.factory');

                foreach($this->_files as $filename)
                {
                    $array = $factory->fromFile($filename, false);

                    if(isset($array['priority'])) {
                        $priority = $array['priority'];
                    } else {
                        $priority = self::PRIORITY_NORMAL;
                    }

                    if(isset($array['aliases']))
                    {
                        if(!isset($aliases[$priority])) {
                            $aliases[$priority] = array();
                        }

                        $aliases[$priority] = array_merge($aliases[$priority], $array['aliases']);;
                    }

                    if(isset($array['identifiers']))
                    {
                        if(!isset($identifiers[$priority])) {
                            $identifiers[$priority] = array();
                        }

                        foreach ($array['identifiers'] as $identifier => $config)
                        {
                            if (array_key_exists($identifier, $identifiers[$priority]))
                            {
                                $existing = new KObjectConfig($identifiers[$priority][$identifier]);
                                $existing->append($config);

                                $identifiers[$priority][$identifier] = $existing->toArray();
                            }
                            else $identifiers[$priority][$identifier] = $config;
                        }
                    }
                }

                /*
                * Set the identifiers
                *
                * Collect identifiers by priority and then flatten the array.
                */
                $identifiers_flat = new KObjectConfig();

                krsort($identifiers);
                foreach ($identifiers as $identifier) {
                    $identifiers_flat->append($identifier);
                }

                $identifiers_flat = $identifiers_flat->toArray();

                foreach ($identifiers_flat as $identifier => $config) {
                    $manager->setIdentifier(new KObjectIdentifier($identifier, $config));
                }

                /*
                * Set the aliases
                *
                * Collect aliases by priority and then flatten the array.
                */
                $aliases_flat = array();

                foreach ($aliases as $priority => $merges) {
                    $aliases_flat = array_merge($merges, $aliases_flat);
                }

                foreach($aliases_flat as $alias => $identifier) {
                    $manager->registerAlias($identifier, $alias);
                }

                /*
                 * Reset the bootstrapper in the object manager
                 *
                 * If cache is enabled this will prevent the bootstrapper from reloading the config resources
                 */
                $identifier = new KObjectIdentifier('lib:object.bootstrapper', array(
                    'bootstrapped' => true,
                    'directories'  => $this->_directories,
                    'components'   => $this->_components,
                    'domains'      => $this->_domains,
                    'namespaces'   => $this->_namespaces,
                    'files'        => $this->_files,
                    'applications' => $this->_applications,
                    'aliases'      => $aliases_flat,
                ));

                $manager->setIdentifier($identifier)
                        ->setObject('lib:object.bootstrapper', $this);
            }
            else
            {
                foreach($aliases as $alias => $identifier) {
                    $manager->registerAlias($identifier, $alias);
                }
            }

            $this->_bootstrapped = true;
        }
    }

    /**
     * Register an application
     *
     * @param string  $name  The application name
     * @param string  $path  The application path
     * @return KObjectBootstrapper
     */
    public function registerApplication($name, $path, $bootstrap = false)
    {
        $this->_applications[$name] = $path;

        //Register the components for bootstrapping
        if($bootstrap)
        {
            $this->registerComponents($path);
            $this->getObject('manager')->getClassLoader()->setBasePath($path);
        }

        return $this;
    }

    /**
     * Register a component to be bootstrapped.
     *
     * If the component contains a /resources/config/bootstrapper.php file it will be registered. Class and object
     * locators will be setup for domain only components.
     *
     * @param string $name      The component name
     * @param string $path      The component path
     * @param string $domain    The component domain. Domain is optional and can be NULL
     * @return KObjectBootstrapper
     */
    public function registerComponent($name, $path, $domain = null)
    {
        $identifier = $this->getComponentIdentifier($name, $domain);

        //Prevent registering a component twice
        if(!isset($this->_components[$identifier]))
        {
            $this->_components[$identifier] = $path;

            //Only register components if the domain is set.
            if($domain)
            {
                //Set the component domain
                $this->_domains[$name] = $domain;

                //Set the component namespace
                $namespace = $this->getComponentNamespace($name);
                $this->getObject('manager')
                    ->getClassLoader()
                    ->getLocator('component')
                    ->registerNamespace($namespace, $path);

                $this->_namespaces[$namespace] = $path;
            }

            //Register the config file
            $this->registerFile($path .'/resources/config/bootstrapper.php');
        }

        return $this;
    }

    /**
     * Register components from a directory to be bootstrapped
     *
     * All the first level directories are assumed to be component folders and will be registered.
     *
     * @param string  $directory
     * @param string $domain    The component domain. Domain is optional and can be NULL
     * @return KObjectBootstrapper
     */
    public function registerComponents($directory, $domain = null)
    {
        if(!isset($this->_directories[$directory]))
        {
            foreach (new DirectoryIterator($directory) as $dir)
            {
                //Only get the component directory names
                if ($dir->isDot() || !$dir->isDir() || !preg_match('/^[a-zA-Z]+/', $dir->getBasename())) {
                    continue;
                }

                //Get the component path
                $path = $dir->getPathname();

                //Get the component name (strip prefix if it exists)
                $parts = explode('_', (string) $dir);

                if(count($parts) > 1) {
                    array_shift($parts);
                    $name = implode('_', $parts);
                } else {
                    $name = $parts[0];
                }

                $this->registerComponent($name, $path, $domain);
            }

            $this->_directories[$directory] = true;
        }

        return $this;
    }

    /**
     * Register a configuration file to be bootstrapped
     *
     * @param string $filename The absolute path to the file
     * @return KObjectBootstrapper
     */
    public function registerFile($filename)
    {
        if(file_exists($filename)) {
            $this->_files[$filename] = $filename;
        }

        return $this;
    }

    /**
     * Get the registered applications
     *
     * @return array
     */
    public function getApplications()
    {
        return array_keys($this->_applications);
    }

    /**
     * Get an application path
     *
     * @param string  $name   The application name
     * @return string|null Returns the application path if the application was registered. NULL otherwise
     */
    public function getApplicationPath($name)
    {
        $result = null;

        if(isset($this->_applications[$name])) {
            $result = $this->_applications[$name];
        }

        return $result;
    }

    /**
     * Get the registered components
     *
     * @return array
     */
    public function getComponents()
    {
        return array_keys($this->_components);
    }

    /**
     * Get a registered component domain
     *
     * @param string $name    The component name
     * @return string|null Returns the component domain if the component is registered. NULL otherwise
     */
    public function getComponentDomain($name)
    {
        $result = null;

        if(isset($this->_domains[$name])) {
            $result = $this->_domains[$name];
        }

        return $result;
    }

    /**
     * Get a registered component path
     *
     * @param string $name    The component name
     * @param string $domain  The component domain. Domain is optional and can be NULL
     * @return string Returns the component path if the component is registered. FALSE otherwise
     */
    public function getComponentPath($name, $domain = null)
    {
        $result = null;

        $identifier = $this->getComponentIdentifier($name, $domain);
        if(isset($this->_components[$identifier])) {
            $result = $this->_components[$identifier];
        }

        return $result;
    }

    /**
     * Get a registered component domain
     *
     * @param string $name    The component name
     * @param string $domain  The component domain. Domain is optional and can be NULL
     * @return string|null Returns the component class namespace if the component is registered. NULL otherwise
     */
    public function getComponentNamespace($name, $domain = null)
    {
        $result = null;

        $identifier = $this->getComponentIdentifier($name, $domain);
        if(isset($this->_components[$identifier])) {
            $result = ucfirst($name);
        }

        return $result;
    }

    /**
     * Get a hash based on a name and domain
     *
     * @param string $name    The component name
     * @param string $domain  The component domain. Domain is optional and can be NULL
     * @return string The hash
     */
    public function getComponentIdentifier($name, $domain = null)
    {
        if(!isset($domain)) {
            $domain = $this->getComponentDomain($name);
        }

        if($domain) {
            $hash = 'com://'.$domain.'/'.$name;
        } else {
            $hash = 'com:'.$name;
        }

        return $hash;
    }

    /**
     * Check if the bootstrapper has been run
     *
     * If you specify a specific component name the function will check if this component was bootstrapped.
     *
     * @param string $name    The component name
     * @param string $domain  The component domain. Domain is optional and can be NULL
     * @return bool TRUE if the bootstrapping has run FALSE otherwise
     */
    public function isBootstrapped($name = null, $domain = null)
    {
        if($name)
        {
            $identifier = $this->getComponentIdentifier($name, $domain);
            $result = $this->_bootstrapped && isset($this->_components[$identifier]);
        }
        else $result = $this->_bootstrapped;

        return $result;
    }
}
