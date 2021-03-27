<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Dispatcher Permissible Behavior
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Dispatcher\Behavior
 */
class KDispatcherBehaviorPermissible extends KControllerBehaviorAbstract
{
    /**
     * The permission object
     *
     * @var KDispatcherPermissionInterface
     */
    protected $_permission;

    /**
     * Constructor.
     *
     * @param   KObjectConfig $config Configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        $this->_permission = $config->permission;
    }

    /**
     * Initializes the default configuration for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config An optional ObjectConfig object with configuration options.
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'priority'   => self::PRIORITY_HIGH,
            'permission' => null,
        ));

        parent::_initialize($config);
    }

    /**
     * Command handler
     *
     * Only handles before.action commands to check authorization rules.
     *
     * @param KCommandInterface         $command    The command
     * @param KCommandChainInterface    $chain      The chain executing the command
     * @throws  KControllerExceptionRequestForbidden      If the user is authentic and the actions is not allowed.
     * @throws  KControllerExceptionRequestNotAuthorized  If the user is not authentic and the action is not allowed.
     * @return  boolean Return TRUE if action is permitted. FALSE otherwise.
     */
    public function execute(KCommandInterface $command, KCommandChainInterface $chain)
    {
        $parts = explode('.', $command->getName());

        if($parts[0] == 'before')
        {
            $action = $parts[1];

            if($this->canExecute($action) === false)
            {
                $message = 'Action '.ucfirst($action).' Not Allowed';

                if($this->getUser()->isAuthentic())
                {
                    if (!$this->getUser()->isEnabled()) {
                        $message = 'Account disabled';
                    }

                    throw new KControllerExceptionRequestForbidden($message);
                }
                else throw new KControllerExceptionRequestNotAuthorized($message);

                return false;
            }
        }

        return true;
    }

    /**
     * Check if an action can be executed
     *
     * @param   string  $action Action name
     * @return  boolean True if the action can be executed, otherwise FALSE.
     */
    public function canExecute($action)
    {
        //Check if the action is allowed
        $method = 'can'.ucfirst($action);

        if(!in_array($method, $this->getMixer()->getMethods()))
        {
            $actions = $this->getActions();
            $actions = array_flip($actions);

            $result = isset($actions[$action]);
        }
        else $result = $this->$method();

        return $result;
    }

    /**
     * Mixin Notifier
     *
     * This function is called when the mixin is being mixed. It will get the mixer passed in.
     *
     * @param KObjectMixable $mixer The mixer object
     * @return void
     */
    public function onMixin(KObjectMixable $mixer)
    {
        parent::onMixin($mixer);

        //Create and mixin the permission if it's doesn't exist yet
        if (!$this->_permission instanceof KDispatcherPermissionInterface)
        {
            $permission = $this->_permission;

            if (!$permission || (is_string($permission) && strpos($permission, '.') === false))
            {
                $identifier = $mixer->getIdentifier()->toArray();

                if($identifier['type'] == 'lib') {
                    $identifier['path'] = array('permission');
                } else {
                    $identifier['path'] = array('dispatcher', 'permission');
                }

                if ($permission) {
                    $identifier['name'] = $permission;
                }

                $permission = $this->getIdentifier($identifier);
            }

            if (!$permission instanceof KObjectIdentifierInterface) {
                $permission = $this->getIdentifier($permission);
            }

            $this->_permission = $mixer->mixin($permission);
        }
    }

    /**
     * Get an object handle
     *
     * Force the object to be enqueue in the command chain.
     *
     * @return string A string that is unique, or NULL
     * @see execute()
     */
    public function getHandle()
    {
        return KObjectMixinAbstract::getHandle();
    }
}