<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Model Context Interface
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Model\Context
 */
interface KModelContextInterface extends KCommandInterface
{
    /**
     * Get the model state
     *
     * @return KModelState
     */
    public function getState();

    /**
     * Get the model entity
     *
     * @return KModelEntityInterface
     */
    public function getEntity();

    /**
     * Get the identity key
     *
     * @return mixed
     */
    public function getIdentityKey();
}