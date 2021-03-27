<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Http Message Interface
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Http\Message
 */
interface KHttpMessageInterface
{
    /**
     * Set the header parameters
     *
     * @param  array $parameters
     * @return KHttpMessageInterface
     */
    public function setHeaders($parameters);

    /**
     * Get the headers container
     *
     * @return KHttpMessageHeaders
     */
    public function getHeaders();

    /**
     * Sets the HTTP protocol version (1.0 or 1.1).
     *
     * @param string $version The HTTP protocol version
     * @return KHttpMessage
     */
    public function setVersion($version);

    /**
     * Gets the HTTP protocol version.
     *
     * @return string The HTTP protocol version
     */
    public function getVersion();

    /**
     * Sets the response content.
     *
     * Valid types are strings, numbers, and objects that implement a __toString() method.
     *
     * @param mixed  $content   The content
     * @param string $type      The content type
     * @throws UnexpectedValueException If the content is not a string are cannot be casted to a string.
     * @return KHttpMessage
     */
    public function setContent($content, $type = null);

    /**
     * Get message content
     *
     * @return mixed
     */
    public function getContent();

    /**
     * Sets the message content type
     *
     * @param string $type Content type
     * @return KHttpMessage
     */
    public function setContentType($type);

    /**
     * Retrieves the message content type
     *
     * @return string Character set
     */
    public function getContentType();

    /**
     * Return the message format
     *
     * @return  string  The message format NULL if no format could be found
     */
    public function getFormat();

    /**
     * Sets a format
     *
     * @param string $format The format
     * @throws UnexpectedValueException If the format hasn't been registered.
     * @return KHttpMessage
     */
    public function setFormat($format);

    /**
     * Render the message as a string
     *
     * @return string
     */
    public function toString();
}