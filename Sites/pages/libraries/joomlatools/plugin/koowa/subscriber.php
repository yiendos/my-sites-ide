<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Subscriber Plugin
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Plugin\Koowa
 */
abstract class PlgKoowaSubscriber extends PlgKoowaAbstract implements KEventSubscriberInterface
{
    /**
     * List of subscribed publishers
     *
     * @var array
     */
    private $__publishers;

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config A ObjectConfig object with configuration options
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'priority'   => self::PRIORITY_NORMAL,
        ));

        parent::_initialize($config);
    }

    /**
     * Connect the plugin to the dispatcher
     *
     * @param $dispatcher
     */
    public function connect($dispatcher)
    {
        //Self subscribe the plugin to the koowa event publisher
        $this->subscribe($this->getObject('event.publisher'));
    }

    /**
     * Attach one or more listeners
     *
     * Event listeners always start with 'on' and need to be public methods.
     *
     * @param KEventPublisherInterface $publisher
     * @return array An array of public methods that have been attached
     */
    public function subscribe(KEventPublisherInterface $publisher)
    {
        $handle = $publisher->getHandle();

        if(!$this->isSubscribed($publisher));
        {
            $listeners = $this->getEventListeners();

            foreach ($listeners as $listener)
            {
                $publisher->addListener($listener, array($this, $listener), $this->getPriority());
                $this->__publishers[$handle][] = $listener;
            }
        }

        return $listeners;
    }

    /**
     * Detach all previously attached listeners for the specific dispatcher
     *
     * @param KEventPublisherInterface $publisher
     * @return void
     */
    public function unsubscribe(KEventPublisherInterface $publisher)
    {
        $handle = $publisher->getHandle();

        if($this->isSubscribed($publisher));
        {
            foreach ($this->__publishers[$handle] as $index => $listener)
            {
                $publisher->removeListener($listener, array($this, $listener));
                unset($this->__publishers[$handle][$index]);
            }
        }
    }

    /**
     * Check if the subscriber is already subscribed to the dispatcher
     *
     * @param  KEventPublisherInterface $publisher  The event dispatcher
     * @return boolean TRUE if the subscriber is already subscribed to the dispatcher. FALSE otherwise.
     */
    public function isSubscribed(KEventPublisherInterface $publisher)
    {
        $handle = $publisher->getHandle();
        return isset($this->__publishers[$handle]);
    }

    /**
     * Get the event listeners
     *
     * @return array
     */
    public static function getEventListeners()
    {
        $listeners = array();

        $reflection = new ReflectionClass(get_called_class());
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
        {
            if(substr($method->name, 0, 2) == 'on') {
                $listeners[] = $method->name;
            }
        }

        return $listeners;
    }

    /**
     * Get the priority of a subscriber
     *
     * @return  integer The subscriber priority
     */
    public function getPriority()
    {
        return $this->getConfig()->priority;
    }
}
