<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Abstract User
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\User
 */
abstract class KUserAbstract extends KObject implements KUserInterface
{
    /**
     * The user data
     *
     * @var KObjectConfig
     */
    private $__data;

    /**
     * Constructor
     *
     * @param KObjectConfig $config An optional KObjectConfig object with configuration options.
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        //Set the user properties and attributes
        $this->setData($config->data);
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config An optional ObjectConfig object with configuration options.
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'data' => array(
                'id'         => 0,
                'email'      => '',
                'name'       => '',
                'roles'      => array(),
                'groups'     => array(),
                'password'   => '',
                'salt'       => '',
                'authentic'  => false,
                'enabled'    => true,
                'expired'    => false,
                'attributes' => array(),
            )
        ));

        parent::_initialize($config);
    }

    /**
     * Set the user data from an array
     *
     * @param  array $data An associative array of data
     * @return KUserAbstract
     */
    public function setData($data)
    {
        $this->__data = new KObjectConfigJson($data);
        return $this;
    }

    /**
     * Get the user data
     *
     * @return KObjectConfig
     */
    public function getData()
    {
        return $this->__data;
    }

    /**
     * Returns the id of the user
     *
     * @return int The id
     */
    public function getId()
    {
        return $this->getData()->id;
    }

    /**
     * Returns the email of the user
     *
     * @return string The email
     */
    public function getEmail()
    {
        return $this->getData()->email;
    }

    /**
     * Returns the name of the user
     *
     * @return string The name
     */
    public function getName()
    {
        return $this->getData()->name;
    }

    /**
     * Returns the roles of the user
     *
     * @return array An array of role id's
     */
    public function getRoles()
    {
        return KObjectConfig::unbox($this->getData()->roles);
    }

    /**
     * Checks if the user has a role.
     *
     * @param  mixed|array $role A role name or an array containing role names.
     * @param  bool        $strict If true, the user has to have all the provided roles, not just one
     * @return bool
     */
    public function hasRole($role, $strict = false)
    {
        $roles = (array) $role;

        if($strict) {
            $result = !array_diff($roles, $this->getRoles());
        } else {
            $result =  (bool) array_intersect($this->getRoles(), $roles);
        }

        return $result;
    }

    /**
     * Returns the groups the user is part of
     *
     * @return array An array of group id's
     */
    public function getGroups()
    {
        return KObjectConfig::unbox($this->getData()->groups);
    }

    /**
     * Checks if the user is part of a group
     *
     * @param  mixed|array $group A role name or an array containing group names.
     * @param  bool        $strict If true, the user needs to be part of all provided group(s), not just one.
     * @return bool
     */
    public function hasGroup($group, $strict = false)
    {
        $groups = (array) $group;

        if($strict) {
            $result = !array_diff($groups, $this->getGroups());
        } else {
            $result = (bool) array_intersect($this->getGroups(), $groups);
        }

        return $result;
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text password will be salted, encoded, and
     * then compared to this value.
     *
     * @return string The password
     */
    public function getPassword()
    {
        return $this->getData()->password;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string The salt
     */
    public function getSalt()
    {
        return $this->getData()->salt;
    }

    /**
     * The user has been successfully authenticated
     *
     * @param  boolean $strict If true, checks if the user has been authenticated for this request explicitly
     * @return Boolean
     */
    public function isAuthentic($strict = false)
    {
        return $this->getData()->authentic;
    }

    /**
     * Checks whether the user account is enabled.
     *
     * @return Boolean
     */
    public function isEnabled()
    {
        return $this->getData()->enabled;
    }

    /**
     * Checks whether the user account has expired.
     *
     * @return Boolean
     */
    public function isExpired()
    {
        return $this->getData()->expired;
    }

    /**
     * Sets the user as authenticated for the request
     *
     * @return $this
     */
    public function setAuthentic()
    {
        $this->getData()->authentic = true;

        return $this;
    }

    /**
     * Get an user attribute
     *
     * @param   string  $identifier Attribute identifier, eg .foo.bar
     * @param   mixed   $default Default value when the attribute doesn't exist
     * @return  mixed   The value
     */
    public function get($identifier, $default = null)
    {
        $attributes = $this->getData()->attributes;

        $result = $default;
        if(isset($attributes[$identifier])) {
            $result = $attributes[$identifier];
        }

        return $result;
    }

    /**
     * Set an user attribute
     *
     * @param   mixed   $identifier Attribute identifier, eg foo.bar
     * @param   mixed   $value      Attribute value
     * @return KUserAbstract
     */
    public function set($identifier, $value)
    {
        $attributes = $this->getData()->attributes;
        $attributes[$identifier] = $value;

        return $this;
    }

    /**
     * Check if a user attribute exists
     *
     * @param   string  $identifier Attribute identifier, eg foo.bar
     * @return  boolean
     */
    public function has($identifier)
    {
        $attributes = $this->getData()->attributes;
        if(isset($attributes[$identifier])) {
            return true;
        }

        return false;
    }

    /**
     * Removes an user attribute
     *
     * @param string $identifier Attribute identifier, eg foo.bar
     * @return KUserAbstract
     */
    public function remove($identifier)
    {
        if(isset($attributes[$identifier])) {
            unset($attributes[$identifier]);
        }

        return $this;
    }

    /**
     * Check if the user is equal
     *
     * @param KObjectInterface|KUserInterface $user
     * @return Boolean
     */
    public function equals(KObjectInterface $user)
    {
        if($user instanceof KUserInterface)
        {
            if($user->getEmail() == $this->getEmail())
            {
                if($user->getPassword() == $this->getPassword()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the user data as an array
     *
     * @return array An associative array of data
     */
    public function toArray()
    {
        return KObjectConfig::unbox($this->getData());
    }

    /**
     * Dumping user object
     *
     * @return mixed
     */
    public function __debugInfo()
    {
        return $this->toArray();
    }
}