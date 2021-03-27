<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Http Message Parameters
 *
 * Container class that handles the aggregations of HTTP parameters as a collection
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Http\Message
 */
class KHttpMessageParameters extends KObjectArray
{
    /**
     * Constructor
     *
     * @param KObjectConfig $config  An optional ObjectConfig object with configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        //Add the parameters
        $this->add(KObjectConfig::unbox($config->parameters));
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   KObjectConfig $config An optional ObjectConfig object with configuration options
     * @return  void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'parameters' => array(),
        ));

        parent::_initialize($config);
    }

    /**
     * Get all parameters and filter them
     *
     * @param   mixed   $filter Filter(s), can be a Filter object, a filter name, an array of filter names or a filter identifier
     * @return  mixed   The sanitized data
     */
    public function all($filter)
    {
        $result = $this->toArray();

        // If the value is null return the default
        if(!empty($result))
        {

            // Filter the data
            if(!($filter instanceof KFilterInterface)) {
                $filter = $this->getObject('filter.factory')->createChain($filter);
            }

            $result = $filter->sanitize($result);
        }

        return $result;
    }

    /**
     * Get a filtered parameter
     *
     * @param   string  $identifier Parameter identifier, eg .foo.bar
     * @param   mixed   $filter     Filter(s), can be a Filter object, a filter name, an array of filter names or a filter
     *                              identifier
     * @param   mixed   $default    Default value when the variable doesn't exist
     * @return  mixed   The sanitized parameter
     */
    public function get($identifier, $filter, $default = null)
    {
        $keys = $this->_parseIdentifier($identifier);

        $result = $this->toArray();
        foreach($keys as $key)
        {
            if(array_key_exists($key, $result)) {
                $result = $result[$key];
            } else {
                $result = null;
                break;
            }
        }

        // If the value is null return the default
        if(!is_null($result))
        {
            // Filter the data
            if(!($filter instanceof KFilterInterface)) {
                $filter = $this->getObject('filter.factory')->createChain($filter);
            }

            $result = $filter->sanitize($result);
        }
        else $result = $default;

        return $result;
    }

    /**
     * Set a parameter
     *
     * @param   mixed   $identifier Parameter identifier, eg foo.bar
     * @param   mixed   $value     Parameter value
     * @param   boolean $replace    Whether to replace the actual value or not (true by default)
     * @throws \UnexpectedValueException If the content is not a string are cannot be casted to a string.
     * @return KHttpMessageParameters
     */
    public function set($identifier, $value, $replace = true)
    {
        if (!is_null($value) && !is_scalar($value) && !is_array($value) && !(is_object($value) && method_exists($value, '__toString')))
        {
            throw new UnexpectedValueException(
                'The http parameter value must be a string or object implementing __toString(), "'.gettype($value).'" given.'
            );
        }

        $keys = $this->_parseIdentifier($identifier);

        foreach(array_reverse($keys, true) as $key)
        {
            if ($replace !== true && isset($this[$key])) {
                break;
            }

            $value = array($key => $value);
            $this->_data = $this->_mergeArrays($this->_data, $value);
        }

        return $this;
    }

    /**
     * Check if a variable exists based on an identifier
     *
     * @param   string  $identifier Parameter identifier, eg foo.bar
     * @return  boolean
     */
    public function has($identifier)
    {
        $keys = $this->_parseIdentifier($identifier);

        foreach($keys as $key)
        {
            if(array_key_exists($key, $this->_data)) {
                return true;
            };
        }

        return false;
    }

    /**
     * Adds new parameters the current HTTP parameters set.
     *
     * This function will not add parameters that already exist.
     *
     * @param array $parameters An array of HTTP headers
     * @return KHttpMessageParameters
     */
    public function add(array $parameters)
    {
        foreach ($parameters as $identifier => $value) {
            $this->set($identifier, $value, false);
        }

        return $this;
    }

    /**
     * Removes a parameter.
     *
     * @param string $identifier The parameter name
     * @return KHttpMessageParameters
     */
    public function remove($identifier)
    {
        $keys = $this->_parseIdentifier($identifier);

        foreach($keys as $key)
        {
            if(array_key_exists($key, $this->_data))
            {
                unset($this->_data[$key]);
                break;
            };
        }

        return $this;
    }

    /**
     * Clear the current parameters
     *
     * @return KHttpMessageParameters
     */
    public function clear()
    {
        $this->_data = array();
        return $this;
    }

    /**
     * Returns the parameters as a query string.
     *
     * @return string The headers
     */
    public function toString()
    {
        return http_build_query($this->_data, '', '&');
    }

    /**
     * Strips slashes recursively on an array
     *
     * @param   array $value Array of (nested arrays of) strings
     * @return  array The input array with stripslashes applied to it
     */
    protected function _stripSlashes( $value )
    {
        if(!is_object($value)) {
            $value = is_array( $value ) ? array_map( array( $this, '_stripSlashes' ), $value ) : stripslashes( $value );
        }

        return $value;
    }

    /**
     * Parse the variable identifier
     *
     * @param   string  $identifier Parameter identifier
     * @return  array   The array of variables
     */
    protected function _parseIdentifier($identifier)
    {
        $parts = array();

        // Split the variable name into it's parts
        if(strpos($identifier, '.') !== false) {
            $parts = explode('.', $identifier);
        } else {
            $parts[] = $identifier;
        }

        return $parts;
    }

    /**
     * Merge two arrays recursively
     *
     * Matching keys' values in the second array overwrite those in the first array, as is the case with array_merge.
     *
     * Parameters are passed by reference, though only for performance reasons. They're not altered by this function
     * and the data types of the values in the arrays are unchanged.
     *
     * @param array $array1
     * @param array $array2
     * @return array An array of values resulted from merging the arguments together.
     */
    protected function _mergeArrays( array &$array1, array &$array2 )
    {
        $args   = func_get_args();
        $merged = array_shift($args);

        foreach($args as $array)
        {
            foreach ( $array as $key => &$value )
            {
                if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) ){
                    $merged [$key] = $this->_mergeArrays ( $merged [$key], $value );
                } else {
                    $merged [$key] = $value;
                }
            }
        }

        return $merged;
    }

    /**
     * Allow PHP casting of this object
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}