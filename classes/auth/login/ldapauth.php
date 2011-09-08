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

		if ($username_or_email != '' and $password != '')
		{
			// binds using given credentials but rebinds as master when authenticated
			$authenticated = $this->_ldap->bind_credentials($username_or_email, $password);

			// Did the credentials authenticate?
			if ($authenticated)
			{
				// Set the parameters to get the users info
				$params = array('&', 'objectCategory=Person', 'objectClass=User', 'sAMAccountName=' . Ldap::get_username_id_part($username_or_email));
				$filter = Ldap_Query_Builder::build($params);

				// Get all user data from Ldap query. This query should return only 1 result
				$result = $this->_ldap->query($filter)->execute('', '*');

				if (($count = count($result)) == 1)
				{
					// We set the user to the returned result item and remove the counts, flatten the
					// value attributes, and lower case the keys
					$this->_user = $result[0];

					// Format the user array
					// @formatter:off
					$this->_user = Ldap_Query_Result_Formatter::format($this->_user,
						Ldap_Query_Result_Formatter::LDAP_FORMAT_REMOVE_COUNTS
						| Ldap_Query_Result_Formatter::LDAP_FORMAT_NO_NUM_INDEX
						| Ldap_Query_Result_Formatter::LDAP_FORMAT_FLATTEN_VALUES
						| Ldap_Query_Result_Formatter::LDAP_FORMAT_KEYS_CASE_LOWER
						| Ldap_Query_Result_Formatter::LDAP_FORMAT_SORT_BY_ATTRIBUTES,
						Ldap_Query_Result_Formatter::LDAP_RESULT_LEVEL_ITEM);
					//@formatter:on

					if ($this->_user == null || empty($this->_user))
					{
						\Session::delete('username');
					}
					else
					{
						\Session::set('username', $this->_user['samaccountname']);
					}
				}
				else if ($count == 0)
				{
					throw new \Fuel_Exception('There where no results with the given $username_or_email parameter. Maybe the user does not exist');
				}
				else
				{
					throw new \Fuel_Exception('There where multiple results with the given $username_or_email parameter. Is your LDAP directory correct?');
				}

				$response = true;
			}
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
		$response = array($this->get_id(), ((isset($this->_user['objectguid'])? Ldap_Query_Result_Formatter::guid_bin_to_str($this->_user['objectguid']) : null))); 
		
		return $response;
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
