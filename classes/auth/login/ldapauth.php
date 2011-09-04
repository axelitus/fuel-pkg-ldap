<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Ldap;

/**
 * Ldap Package
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann
 * @copyright   2011 Axel Pardemann
 */
class Auth_Login_LdapAuth extends \Auth_Login_Driver
{
	protected $_ldap = null;

	protected function __construct(Array $config = array())
	{
		if (!isset($config['ldap']))
		{
			// The ldap instance is needed, so create a default one
			$this->_ldap = Ldap::forge();
		}
		else
		{
			if (is_string($config['ldap']) || is_numeric($config['ldap']))
			{
				if (Ldap::has_instance($config['ldap']))
				{
					$this->_ldap = Ldap::instance($config['ldap']);
				}
				else
				{
					throw new \Fuel_Exception('The Ldap instance is needed and the given config\'s ldap parameter is not a name for an existing Ldap instance.');
				}
			}
			else if (is_object($config['ldap']) && get_class($config['ldap']) == 'Ldap\Ldap')
			{
				$this->_ldap = $config['ldap'];
			}
			else
			{
				throw new \Fuel_Exception('The Ldap instance is needed. The config\'s ldap parameter is not an Ldap instance, nor is it a valid name or a valid index for an existing Ldap instance.');
			}
		}

		// Set the Auth's id to match the Ldap's id
		$config['id'] = $this->_ldap->get_name();

		// Unset the config's ldap as it's not needed anymore
		unset($config['ldap']);

		parent::__construct($config);
	}

	/**
	 * Initializes the LdapAuth. This function gets automatically called by the
	 * Autoloader
	 */
	public static function _init()
	{
		\Config::load('ldapauth', true, false, true);
	}

	// ------------------------------------------------------------------------

	protected $_user = null;

	/**
	 * Perform the actual login check
	 *
	 * @return  bool
	 */
	protected function perform_check()
	{

	}

	/**
	 * Login method
	 *
	 * @return  bool  whether login succeeded
	 */
	public function login($username_or_email = '', $password = '')
	{
		$response = false;
		
		$username_or_email = (($temp = trim($username_or_email)) ? $temp : trim(\Input::post(\Config::get('ldapauth.username_post_key', 'username'))));
		$password = (($temp = trim($password)) ? $temp : trim(\Input::post(\Config::get('ldapauth.password_post_key', 'password'))));

		if (empty($username_or_email) or empty($password))
		{
			return false;
		}

		$authenticated = $this->_ldap->bind_credentials($username_or_email, $password, true);
		if ($authenticated)
		{
			// TODO: Build the query to retrieve the user object
			
			/*
			if ($this->user == false)
			{
				$this->user = \Config::get('simpleauth.guest_login', true) ? static::$guest_login : false;
				\Session::delete('username');
				\Session::delete('login_hash');
				return false;
			}

			\Session::set('username', $this->user['username']);
			\Session::set('login_hash', $this->create_login_hash());
			 * */
			$response = true;
		}
		
		return $response;
	}

	/**
	 * Logout method
	 */
	public function logout()
	{
	}

	/**
	 * Get User Identifier of the current logged in user
	 * in the form: array(driver_id, user_id)
	 *
	 * @return  array
	 */
	public function get_user_id()
	{
	}

	/**
	 * Get User Groups of the current logged in user
	 * in the form: array(array(driver_id, group_id), array(driver_id, group_id),
	 * etc)
	 *
	 * @return  array
	 */
	public function get_groups()
	{
	}

	/**
	 * Get emailaddress of the current logged in user
	 *
	 * @return  string
	 */
	public function get_email()
	{

	}

	/**
	 * Get screen name of the current logged in user
	 *
	 * @return  string
	 */
	public function get_screen_name()
	{
	}

	/**
	 * Does an Ldap binding with user credentials to validate a login
	 */
	private function authenticate($username_or_email = '', $password = '', $rebind_as_master = true)
	{
		$response = false;

		$rebind = ($rebind_as_master && $this->_ldap->is_binded());

		if ($this->_ldap->is_connected(true))
		{
			$full_id = Ldap::full_qualified_id($username_or_email);
			if ($full_id != '' && is_string($password) && $password != '')
			{
				$this->_ldap->bind_credentials($full_id, $password, $rebind);
			}
		}
		else
		{
			throw new \Fuel_Exception('Cannot authenticate: there is no connection to LDAP server.');
		}

		return $response;
	}

}
