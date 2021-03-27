<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * View Context
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\View\Context
 */
class KViewContext extends KCommand implements KViewContextInterface
{
    /**
     * Set the view data
     *
     * @param array $data
     * @return KViewContext
     */
    public function setData($data)
    {
        return KObjectConfig::set('data', $data);
    }

    /**
     * Get the view data
     *
     * @return array
     */
    public function getData()
    {
        return KObjectConfig::get('data');
    }
}