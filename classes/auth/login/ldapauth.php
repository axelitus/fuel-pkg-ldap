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

namespace Ldap;

// @formatter:off
class LdapAuthException extends \FuelException {}
// @formatter:on

/**
 * Auth_Login_LdapAuth
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
class Auth_Login_LdapAuth extends \Auth_Login_Driver
{
	// @formatter:off
	protected static $_needed_attr = array(
		'objectguid',
		'samaccountname',
		'mail',
		'memberof',
		'pwdlastset'
	);
	// @formatter:on

	/**
	 * @var
	 */
	protected $_ldap = null;

	/**
	 * Initializes the LdapAuth. This function gets automatically called by the Autoloader
	 */
	public static function _init()
	{
		\Config::load('ldapauth', true);
	}

	/**
	 * Prevent direct instantiation
	 */
	protected function __construct(array $config = array())
	{
		if ( ! isset($config['ldap']))
		{
			// The ldap instance is needed, so create a default one
			$this->_ldap = Ldap::forge($config['id']);
		}
		else
		{
			if (is_string($config['ldap']))
			{
				if (Ldap::exists($config['ldap']))
				{
					$this->_ldap = Ldap::instance($config['ldap']);
				}
				else
				{
					throw new \FuelException('The Ldap instance is needed and the given config\'s ldap parameter is not a name for an existing Ldap instance.');
				}
			}
			else if (is_object($config['ldap']) && get_class($config['ldap']) == 'Ldap\Ldap')
			{
				$this->_ldap = $config['ldap'];
			}
			else
			{
				throw new \FuelException('The Ldap instance is needed. The config\'s ldap parameter is not an Ldap instance, nor is it a valid name for an existing Ldap instance.');
			}
		}

		// Set the Auth's id to match the Ldap's id
		$config['id'] = $this->_ldap->get_name();

		// Unset the config's ldap as it's not needed anymore
		unset($config['ldap']);

		// Call the Auth_Login_Driver class constructor
		parent::__construct($config);
	}

	/**
	 * @var  Ldap_Query_Result when login succeeded
	 */
	protected $user = null;

	/**
	 * Builds the query attributes to include the needed ones
	 */
	protected function get_query_attributes($as_string = false)
	{
		$return = (is_array($return = \Config::get('ldapauth.attributes', array('*'))) ? $return : array('*'));
		if (count($return) != 1 || $return[0] != '*')
		{
			$return = array_unique(\Arr::merge(static::$_needed_attr, $return));
		}

		natcasesort($return);
		$return = (($as_string) ? implode(', ', $return) : $return);

		return $return;
	}

	/**
	 * Cleans the LdapAuth session variables and user info
	 */
	private function _clean()
	{
		$this->user = null;
		\Session::delete('username');
		\Session::delete('login_hash');
	}

	/**
	 * Creates a temporary hash that will validate the current login
	 *
	 * @return  string
	 */
	public function create_login_hash()
	{
		if (empty($this->user))
		{
			throw new LdapAuthException('User not logged in, can\'t create login hash.');
		}

		// TODO: find a better way to have a login hash, hopefully without changing the schema
		$login_hash = sha1(\Config::get('ldapauth.login_hash_salt').$this->user['samaccountname'].$this->user['pwdlastset']);

		return $login_hash;
	}

	/**
	 * Perform the actual login check
	 *
	 * @return  bool
	 */

	protected function perform_check()
	{
		$username = \Session::get('username');
		$login_hash = \Session::get('login_hash');

		// only worth checking if there's both a username and login-hash
		if ( ! empty($username) and ! empty($login_hash))
		{
			if (is_null($this->user) or ($this->user['samaccountname'] != $username))
			{
				// Set the filter to get the users info
				$filter = Ldap_Query_Builder::build(array('&', 'objectCategory=Person', 'objectClass=User', 'sAMAccountName='.$username));

				// Get all user data from Ldap query.
				$attr = $this->get_query_attributes();
				$result = $this->_ldap->query($filter)->execute('', $attr);

				// The result should have only 1 row
				if (($count = count($result)) == 1)
				{
					// We set the user to the returned result item and remove the counts, flatten the
					// value attributes, and lower case the keys
					$this->user = $result[0];

					// @formatter:off
					// Format the user array
					$this->user = Ldap_Query_Result_Formatter::format($this->user,
						Ldap_Query_Result_Formatter::FORMAT_REMOVE_COUNTS |
						Ldap_Query_Result_Formatter::FORMAT_NO_NUM_INDEX |
						Ldap_Query_Result_Formatter::FORMAT_FLATTEN_VALUES |
						Ldap_Query_Result_Formatter::FORMAT_KEYS_CASE_LOWER |
						Ldap_Query_Result_Formatter::FORMAT_SORT_BY_ATTRIBUTES,
						Ldap_Query_Result_Formatter::RESULT_LEVEL_ITEM
					);
					//@formatter:on
				}
			}

			// return true when login was verified
			if ( ! is_null($this->user) and $this->create_login_hash() === $login_hash)
			{
				return true;
			}
		}

		// no valid login when still here, ensure empty session
		$this->_clean();

		return false;
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

		if ($this->_ldap->bind_credentials($username_or_email, $password))
		{
			$samaccountname = (($pos = strpos($username_or_email, '@')) === false) ? $username_or_email : substr($username_or_email, 0, $pos);

			// Set the filter to get the users info
			$filter = Ldap_Query_Builder::build(array('&', 'objectCategory=Person', 'objectClass=User', 'sAMAccountName='.$samaccountname));

			// Get all user data from Ldap query.
			$attr = $this->get_query_attributes();
			$result = $this->_ldap->query($filter)->execute('', $attr);

			// The result should have only 1 row
			if (($count = count($result)) == 1)
			{
				// We set the user to the returned result item and remove the counts, flatten the
				// value attributes, and lower case the keys
				$this->user = $result[0];

				// @formatter:off
				// Format the user array
				$this->user = Ldap_Query_Result_Formatter::format($this->user, 
					Ldap_Query_Result_Formatter::FORMAT_REMOVE_COUNTS |
					Ldap_Query_Result_Formatter::FORMAT_NO_NUM_INDEX |
					Ldap_Query_Result_Formatter::FORMAT_FLATTEN_VALUES |
					Ldap_Query_Result_Formatter::FORMAT_KEYS_CASE_LOWER |
					Ldap_Query_Result_Formatter::FORMAT_SORT_BY_ATTRIBUTES,
					Ldap_Query_Result_Formatter::RESULT_LEVEL_ITEM
				);
				//@formatter:on
				
				//var_dump($this->user); exit;
				if ($this->user == null || empty($this->user))
				{
					$this->_clean();
					return false;
				}
				else
				{
					\Session::set('username', $this->user['samaccountname']);
					\Session::set('login_hash', $this->create_login_hash());
					\Session::instance()->rotate();
					return true;
				}
			}
			else if ($count == 0)
			{
				throw new \FuelException('There where no results with the given $username_or_email parameter. Maybe the user does not exist.');
			}
			else
			{
				throw new \FuelException('There where multiple results with the given $username_or_email parameter. Is your LDAP directory correct?');
			}
		}

		return false;
	}

	/**
	 * Logout method
	 */
	public function logout()
	{
		$this->_clean();
		return true;
	}

	public function get_dn()
	{
		if (empty($this->user))
		{
			return false;
		}

		return $this->user['dn'];
	}

	/**
	 * Get User Identifier of the current logged in user
	 * in the form: array(driver_id, user_id)
	 *
	 * @return  array
	 */
	public function get_user_id()
	{
		if (empty($this->user))
		{
			return false;
		}

		$return = array($this->get_id(), ((isset($this->user['objectguid']) ? Ldap_Query_Result_Formatter::guid_bin_to_str($this->user['objectguid']) : null)));

		return $return;
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
		if (empty($this->user))
		{
			return false;
		}

		return $this->user['memberof'];
	}

	/**
	 * Get emailaddress of the current logged in user
	 *
	 * @return  string
	 */
	public function get_email()
	{
		if (empty($this->user))
		{
			return false;
		}

		return $this->user['mail'];
	}

	/**
	 * Get screen name of the current logged in user
	 *
	 * @return  string
	 */
	public function get_screen_name()
	{
		if (empty($this->user))
		{
			return false;
		}

		return $this->user['samaccountname'];
	}

}
