<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Abstract Session Handler
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\User\Session\Handler
 * @link    http://www.php.net/manual/en/function.session-set-save-handler.php
 */
abstract class KUserSessionHandlerAbstract extends KObject implements KUserSessionHandlerInterface
{
    /**
     * The handler that was registered
     *
     * @var object
     * @see isRegistered()
     */
    static protected $_registered = null;

    /**
     * Constructor
     *
     * @param KObjectConfig|null $config  An optional ObjectConfig object with configuration options
     * @throws RuntimeException If the session handler is not available
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        if (!$this->isSupported())
        {
            $name = $this->getIdentifier()->name;
            throw new RuntimeException('The ' . $name . ' session handler is not available');
        }

        //Register the functions of this class with the PHP session handler
        if ($config->auto_register) {
            $this->register();
        }
    }

    /**
     * Initializes the default configuration for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config  An optional ObjectConfig object with configuration options.
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'auto_register' => false,
        ));

        parent::_initialize($config);
    }

    /**
     * Register the functions of this class with PHP's session handler
     *
     * @see http://php.net/session-set-save-handler
     * @return void
     */
    public function register()
    {
        session_set_save_handler(
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'gc')
        );

        static::$_registered = $this;
    }

    /**
     * Initialize the session handler backend
     *
     * @param   string  $save_path     The path to the session object
     * @param   string  $session_name  The name of the session
     * @return  boolean  True on success, false otherwise
     */
    public function open($save_path, $session_name)
    {
        return true;
    }

    /**
     * Close the session handler backend
     *
     * @return  boolean  True on success, false otherwise
     */
    public function close()
    {
        return true;
    }

    /**
     * Read session data for a particular session identifier from the session handler backend
     *
     * @param   string  $session_id  The session identifier
     * @return  string  The session data
     */
    public function read($session_id)
    {
        /*
         * It turns out that session_start() doesn't like the read method of a custom session handler
         * returning false or null if there's no session in existence.
         *
         * See: https://stackoverflow.com/a/48245947
         * See: http://php.net/manual/en/function.session-start.php#120589
         */
        return '';

    }

    /**
     * Write session data to the session handler backend
     *
     * @param   string  $session_id    The session identifier
     * @param   string  $session_data  The session data
     * @return  boolean  True on success, false otherwise
     */
    public function write($session_id, $session_data)
    {
        return true;
    }

    /**
     * Destroy the data for a particular session identifier in the session handler backend
     *
     * @param   string  $session_id  The session identifier
     * @return  boolean  True on success, false otherwise
     */
    public function destroy($session_id)
    {
        return true;
    }

    /**
     * Garbage collect stale sessions from the SessionHandler backend.
     *
     * @param   integer  $maxlifetime  The maximum age of a session
     * @return  boolean  True on success, false otherwise
     */
    public function gc($maxlifetime = null)
    {
        return true;
    }

    /**
     * Is this handler registered with the PHP's session handler
     *
     * @return boolean  True on success, false otherwise
     */
    public function isRegistered()
    {
        if (self::$_registered === $this) {
            return true;
        }

        return false;
    }

    /**
     * Test to see if the session handler is available.
     *
     * @return  boolean  True on success, false otherwise
     */
    public function isSupported()
    {
        return true;
    }
}
