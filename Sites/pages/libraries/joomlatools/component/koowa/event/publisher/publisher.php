<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Event Publisher
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Component\Koowa\Event\Publisher
 */
final class ComKoowaEventPublisher extends KEventPublisher
{
    /**
     * Constructor.
     *
     * @param KObjectConfig $config  An optional ObjectConfig object with configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        $this->getObject('exception.handler')->addExceptionCallback(array($this, 'publishException'));
    }

    /**
     * Publish an event by calling all listeners that have registered to receive it.
     *
     * If an event target is specified try to import the plugin group based on the package name of the target
     * before publishing the event.
     *
     * @param  string|KEventInterface             $event      The event name or a KEventInterface object
     * @param  array|Traversable|KEventInterface  $attributes An associative array, an object implementing the
     *                                                        KEventInterface or a Traversable object
     * @param  mixed                              $target     The event target
     *
     * @throws InvalidArgumentException  If the event is not a string or does not implement the KEventInterface
     * @return null|KEventInterface Returns the event object. If the chain is not enabled will return NULL.
     */
    public function publishEvent($event, $attributes = array(), $target = null)
    {
        //Try to load the plugin group
        if($target instanceof KObject)
        {
            $identifier = $target->getIdentifier()->toArray();
            $package    = $identifier['package'];

            JPluginHelper::importPlugin($package, null, true);
        }

        return parent::publishEvent($event, $attributes, $target);
    }

    /**
     * Publish an event by calling all listeners that have registered to receive it.
     *
     * Function will avoid a recursive loop when an exception is thrown during even publishing and output a generic
     * exception instead.
     *
     * @param  Exception           $exception  The exception to be published.
     * @param  array|Traversable    $attributes An associative array or a Traversable object
     * @param  mixed                $target     The event target
     * @return  KEvent
     */
    public function publishException(Exception $exception)
    {
        return parent::publishEvent('onException', ['exception' => $exception]);
    }
}
