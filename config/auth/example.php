<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.1
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

/**
 * NOTICE:
 *
 * If you need to make modifications to the default configuration, copy
 * this file to your app/config folder, and make them in there.
 *
 * This will allow you to upgrade this package without losing your custom config.
 */
/**
 * Ldap Auth Config example
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
return array(
	/**
	 * Choose which attributes are retrieved. Always included: objectguid, samaccountname, mail, memberof, pwdlastset
	 */
	'attributes' => array('*'),
	
	/**
	 * The login hash field to use in the AD to store login hash. Set to false if you don't want to use the login hash.
	 * You must ensure that the login hash is valid and writable. Use carefully as any value in this field will be overwritten
	 * by the login hash.
	 */
	'login_hash_field' => false,
	
	/**
	 * Salt for the login hash
	 */
	'login_hash_salt' => 'put_some_salt_in_here',
	
	/**
	 * $_POST key for login username
	 */
	'username_post_key' => 'username',

	/**
	 * $_POST key for login password
	 */
	'password_post_key' => 'password'
);
