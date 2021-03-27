<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * User Interface
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\User
 */
interface KUserInterface extends KObjectEquatable
{
    /**
     * Returns the id of the user
     *
     * @return int The id
     */
    public function getId();

    /**
     * Returns the email of the user
     *
     * @return string The email
     */
    public function getEmail();

    /**
     * Returns the name of the user
     *
     * @return string The name
     */
    public function getName();

    /**
     * Returns the roles of the user
     *
     * @return array An array of role id's
     */
    public function getRoles();

    /**
     * Checks if the user has a role.
     *
     * @param  mixed|array $role A role name or an array containing role names.
     * @param  bool        $strict If true, the user has to have all the provided roles, not just one
     * @return bool True if the user has at least one of the provided roles, false otherwise.
     */
    public function hasRole($role, $strict = false);

    /**
     * Returns the groups the user is part of
     *
     * @return array An array of group id's
     */
    public function getGroups();

    /**
     * Checks if the user is part of a group
     *
     * @param  mixed|array $group A role name or an array containing group names.©≈©
     * @param  bool        $strict If true, the user needs to be part of all provided group(s), not just one.
     * @return bool
     */
    public function hasGroup($group, $strict = false);

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text password will be salted, encoded, and
     * then compared to this value.
     *
     * @return string The password
     */
    public function getPassword();

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string The salt
     */
    public function getSalt();

    /**
     * The user has been successfully authenticated
     *
     * @param  boolean $strict If true, checks if the user has been authenticated for this request explicitly
     * @return Boolean
     */
    public function isAuthentic($strict = false);

    /**
     * Checks whether the user account is enabled.
     *
     * @return Boolean
     */
    public function isEnabled();

    /**
     * Checks whether the user account has expired.
     *
     * @return Boolean
     */
    public function isExpired();

    /**
     * Sets the user as authenticated for the request
     *
     * @return $this
     */
    public function setAuthentic();

    /**
     * Get an user attribute
     *
     * @param   string  $identifier Attribute identifier, eg .foo.bar
     * @param   mixed   $default Default value when the attribute doesn't exist
     * @return  mixed   The value
     */
    public function get($identifier, $default = null);

    /**
     * Set an user attribute
     *
     * @param   mixed   $identifier Attribute identifier, eg foo.bar
     * @param   mixed   $value      Attribute value
     * @return KUserInterface
     */
    public function set($identifier, $value);

    /**
     * Check if a user attribute exists
     *
     * @param   string  $identifier Attribute identifier, eg foo.bar
     * @return  boolean
     */
    public function has($identifier);

    /**
     * Removes an user attribute
     *
     * @param string $identifier Attribute identifier, eg foo.bar
     * @return KUserInterface
     */
    public function remove($identifier);

    /**
     * Get the user data as an array
     *
     * @return array An associative array of data
     */
    public function toArray();


}