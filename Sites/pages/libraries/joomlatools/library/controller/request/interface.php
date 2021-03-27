<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Controller Request Interface
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Controller\Request
 */
interface KControllerRequestInterface
{
    /**
     * Set the request query
     *
     * @param  array $query
     * @return KControllerRequestInterface
     */
    public function setQuery($query);

    /**
     * Get the request query
     *
     * @return KHttpMessageParameters
     */
    public function getQuery();

    /**
     * Set the request data
     *
     * @param  array $data
     * @return KControllerRequestInterface
     */
    public function setData($data);

    /**
     * Get the request data
     *
     * @return KHttpMessageParameters
     */
    public function getData();

    /**
     * Set the request format
     *
     * @param $format
     * @return KControllerRequestInterface
     */
    public function setFormat($format);

    /**
     * Return the request format
     *
     * @return  string  The request format or NULL if no format could be found
     */
    public function getFormat();

    /**
     * The timestamp of the start of the request, with microsecond precision
     *
     * @see: https://www.php.net/manual/en/reserved.variables.server.php
     * @return float
     */
    public function getTime();
}