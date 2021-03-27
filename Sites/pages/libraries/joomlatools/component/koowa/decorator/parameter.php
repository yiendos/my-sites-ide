<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Used to decorate parameter objects coming from Joomla for easy getter/setter access
 *
 * @author  Ercan Ozkaya <https://github.com/ercanozkaya>
 * @package Koowa\Component\Koowa\Decorator
 */
class ComKoowaDecoratorParameter extends KObjectDecoratorAbstract
{
    public function __get($key)
    {
        return $this->getDelegate()->get($key);
    }

    public function __set($key, $value)
    {
        $this->getDelegate()->set($key, $value);
    }
}
