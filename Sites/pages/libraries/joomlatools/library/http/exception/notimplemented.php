<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Not Implemented Http Exception
 *
 * The server does not support the functionality required to fulfill the request.
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Http\Exception
 */
class KHttpExceptionNotImplemented extends KHttpExceptionAbstract
{
    protected $code = KHttpResponse::NOT_IMPLEMENTED;
}