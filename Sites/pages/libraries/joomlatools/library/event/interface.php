<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Event
 *
 * You can call the method stopPropagation() to abort the execution of further listeners in your event listener.
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Event
 */
interface KEventInterface
{
    /**
     * Priority levels
     */
    const PRIORITY_HIGHEST = 1;
    const PRIORITY_HIGH    = 2;
    const PRIORITY_NORMAL  = 3;
    const PRIORITY_LOW     = 4;
    const PRIORITY_LOWEST  = 5;

    /**
     * Get the event name
     *
     * @return string	The event name
     */
    public function getName();

    /**
     * Set the event name
     *
     * @param string $name The event name
     * @return KEvent
     */
    public function setName($name);

    /**
     * Get the event target
     *
     * @return object	The event target
     */
    public function getTarget();

    /**
     * Set the event target
     *
     * @param mixed $target	The event target
     * @return KEvent
     */
    public function setTarget($target);

    /**
     * Set attributes
     *
     * Overwrites existing attributes
     *
     * @param  array|Traversable $attributes
     * @throws InvalidArgumentException If the attributes are not an array or are not traversable.
     * @return KEvent
     */
    public function setAttributes($attributes);

    /**
     * Get all arguments
     *
     * @return array
     */
    public function getAttributes();

    /**
     * Get an attribute
     *
     * If the attribute does not exist, the $default value will be returned.
     *
     * @param  string $name The attribute name
     * @param  mixed $default
     * @return mixed
     */
    public function getAttribute($name, $default = null);

    /**
     * Set an attribute
     *
     * @param  string $name The attribute
     * @param  mixed $value
     * @return KEvent
     */
    public function setAttribute($name, $value);

    /**
     * Returns whether further event listeners should be triggered.
     *
     * @return boolean 	TRUE if the event can propagate. Otherwise FALSE
     */
    public function canPropagate();

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no further event listener will be triggered once
     * any trigger calls stopPropagation().
     *
     * @return KEvent
     */
    public function stopPropagation();
}
