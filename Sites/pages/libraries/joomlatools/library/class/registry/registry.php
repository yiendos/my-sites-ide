<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Class Registry
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Class
 */
class KClassRegistry extends ArrayObject implements KClassRegistryInterface
{
    /**
     * The identifier aliases
     *
     * @var  array
     */
    protected $_aliases = array();

    /**
     * Get a class from the registry
     *
     * @param  string $class
     * @return  string  The class path
     */
    public function get($class)
    {
        if($this->offsetExists($class)) {
            $result = $this->offsetGet($class);
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Set a class path in the registry
     *
     * @param  string $class
     * @param  string $path
     * @return KClassRegistry
     */
    public function set($class, $path)
    {
        $this->offsetSet($class, $path);
        return $this;
    }

    /**
     * Check if a class exists in the registry
     *
     * @param  string $class
     * @return  boolean
     */
    public function has($class)
    {
        return $this->offsetExists($class);
    }

    /**
     * Remove a class from the registry
     *
     * @param  string $class
     * @return  KClassRegistry
     */
    public function remove($class)
    {
        $this->offsetUnset($class);
        return $this;
    }

    /**
     * Clears out all objects from the registry
     *
     * @return  KClassRegistry
     */
    public function clear()
    {
        $this->exchangeArray(array());
        return $this;
    }

    /**
     * Try to find an class path based on a class name
     *
     * @param   string  $class
     * @return  string The class path, or NULL if the class is not registered
     */
    public function find($class)
    {
        //Resolve the real identifier in case an alias was passed
        while(array_key_exists($class, $this->_aliases)) {
            $class = $this->_aliases[$class];
        }

        //Find the identifier
        if($this->offsetExists($class)) {
            $result = $this->offsetGet($class);
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Register an alias for a class
     *
     * @param string $class
     * @param string $alias
     * @return KClassRegistry
     */
    public function alias($class, $alias)
    {
        //Don't register the alias if it's the same as the class
        if($alias != $class) {
            $this->_aliases[$alias] = $class;
        }

        return $this;
    }

    /**
     * Get a list of all the identifier aliases
     *
     * @return array
     */
    public function getAliases()
    {
        return $this->_aliases;
    }

    /**
     * Get a list of all identifiers in the registry
     *
     * @return  array
     */
    public function getClasses()
    {
        return array_keys($this->getArrayCopy());
    }
}