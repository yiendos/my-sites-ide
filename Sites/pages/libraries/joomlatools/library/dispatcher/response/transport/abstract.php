<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Abstract Dispatcher Transport Abstract
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Dispatcher\Response\Transport
 */
abstract class KDispatcherResponseTransportAbstract extends KObject implements KDispatcherResponseTransportInterface
{
    /**
     * The filter priority
     *
     * @var integer
     */
    protected $_priority;

    /**
     * Response object
     *
     * @var	object
     */
    protected $_response;

    /**
     * Constructor.
     *
     * @param KObjectConfig $config 	An optional KObjectConfig object with configuration options.
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        $this->_priority = $config->priority;
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param 	KObjectConfig $config 	An optional ObjectConfig object with configuration options.
     * @return 	void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'priority' => self::PRIORITY_NORMAL,
        ));

        parent::_initialize($config);
    }

    /**
     * Get the priority of a behavior
     *
     * @return  integer The command priority
     */
    public function getPriority()
    {
        return $this->_priority;
    }

    /**
     * Send response
     *
     * @param KDispatcherResponseInterface $response
     * @return boolean  Returns true if the response has been send, otherwise FALSE
     */
    public function send(KDispatcherResponseInterface $response)
    {
        return true;
    }
}