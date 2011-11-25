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

/**
 * Ldap
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
class Ldap
{
	/**
	 * Some useful LDAP constants
	 */
	const LDAP_ORGANIZATIONAL_UNIT = 'OU';
	const LDAP_CONTAINER = 'CN';
	const LDAP_RESOURCE_LINK = 'ldap link';
	const LDAP_RESOURCE_RESULT = 'ldap result';

	/**
	 * @var array contains references to Ldap instances if multiple were loaded
	 */
	protected static $_instances = array();

	/**
	 * @var Ldap contains a reference to the default Ldap instance or null
	 */
	protected static $_default = null;

	/**
	 * @var string|int contains the name of the instance (string or numeric index)
	 */
	protected $_name = '';

	/**
	 * @var Ldap_Config contains the instance configuration object
	 */
	protected $_config = null;

	/**
	 * @var resource contains the LDAP link identifier when connected
	 */
	protected $_connection = null;

	/**
	 * @var bool contains a flag whether there's a binding to Ldap or not
	 */
	protected $_binded = false;

	/**
	 * @var string contains the Base DN of the Ldap binding
	 */
	protected $_base_dn = '';

	/**
	 * Prevent direct instantiation
	 */
	private final function __construct()
	{
	}

	/**
	 * Loads an instance of Ldap.
	 * Be warned: use it with care and at best only on new Ldap instances or you will lose whatever you had in that instance.
	 */
	private final function _load($name, $options = null)
	{
		// this sets the instance to a fresh one
		$this->_clear(true);

		// set the instance name
		$this->_set_name($name);

		// let's see if we have a config given or we just need to use our own
		$this->_config = Ldap_Config::forge((is_array($options) || is_string($options)) ? $options : \Config::load('ldap', true));
	}

	/**
	 * _clears the instance (this means that it unbinds from Ldap server and kills
	 * the
	 * connection). The instance name is the only thing that gets not _cleared.
	 * This is by design, as it could break the functionality.
	 */
	private function _clear($_clear_config = false)
	{
		$this->unbind();
		$this->_connection = null;
		$this->_binded = false;
		$this->_base_dn = '';
		if ($_clear_config)
		{
			$this->_config = array();
		}
	}

	/**
	 * Detects if Ldap is supported or not
	 */
	public final static function ldap_supported()
	{
		$return = function_exists('ldap_connect');

		return $return;
	}

	/**
	 * Forges a new Ldap instance
	 * @return String|Integer|Null
	 */
	public final static function forge($config = null, $override = false)
	{
		$return = null;

		// let's see if Ldap is even supported, if not why bother?
		if (static::ldap_supported())
		{
			// Named or non-named instance? That is the question
			if (is_string($config) && $config != '')
			{
				// we were given only the name of the instance
				$name = $config;

				// Create the instance only if we can override it or it does not exist
				if ($override || !isset(static::$_instances[$name]))
				{
					static::$_instances[$name] = new Ldap();
				}
				else
				{
					// We could not override the instance, so return null
					return $return;
				}

				// set the $config array to null for the _init process (loads from config file or
				// defaults)
				$config = null;

			}
			else if (is_array($config) && isset($config['name']))
			{
				// it's a named instance that we want
				$name = $config['name'];

				// get rid of the extra value in the config
				unset($config['name']);

				// Create the instance only if we can override it or it does not exist
				if ($override || !isset(static::$_instances[$name]))
				{
					static::$_instances[$name] = new Ldap();
				}
				else
				{
					// We could not override the instance, so return null
					return $return;
				}
			}
			else
			{
				// it's a non-named instance that we want
				static::$_instances[] = new Ldap();

				// as the items are inserted at the end, we just need to look for the last key
				// inserted to get the auto-numeric id it generated for the non-named instance
				$keys = static::instance_keys();
				$name = end($keys);
			}

			// set the Ldap instance name and initialize with the custom config
			static::$_instances[$name]->_init($name, $config);

			// If there's no default instance or the config array has the default parameter
			// set to true then set this instance as the default
			if (static::$_default === null || (isset($config['default']) && $config['default'] == true))
			{
				static::$_default = static::$_instances[$name];
			}

			// Set the return value to the generated instance. If we want to know it's name
			// all we have to do is call the get_name() function. Later on we can get the
			// instance again by calling the instance() function.
			$return = static::$_instances[$name];
		}
		else
		{
			// Oh my! We don't have Ldap support. What a bummer!
			throw new \Fuel_Exception('LDAP is not supported.');
		}

		return $return;
	}

	public static function is_instance($name)
	{
		$return = false;

		if (isset(static::$_instances[$name]))
		{
			$return = true;
		}

		return $return;
	}

	/**
	 * Checks wether there's at least one instance
	 */
	public final static function has_instances()
	{
		$return = false;

		if (!empty(static::$_instances))
		{
			$return = true;
		}

		return $return;
	}

	/**
	 * Gets an instance by name (or numeric id) or the default instance if no name is
	 * given (the instance that's first in the array. Note: be careful as to how PHP
	 * manages array, it does not order them by keys, they are just hash tables)
	 */
	public final static function instance($name = '')
	{
		$return = null;

		// action can take 3 values: named, definst, *anything else* (which results in an
		// exception)
		$action = 'exception';

		// let's define the action to take
		if (is_numeric($name) && $name >= 0)
		{
			// Get the numeric indexed (also named) instance
			$action = 'named';
		}
		else if (is_string($name))
		{
			if ($name == '')
			{
				// Get the default instance if there's one
				$action = 'definst';
			}
			else
			{
				// Get the named instance
				$action = 'named';
			}
		}

		// do the defined action
		switch($action)
		{
			case 'named':
				if (static::is_instance($name))
				{
					$return = static::$_instances[$name];
				}
				else
				{
					// throw exception 'There's no instance named that way'
					throw new Fuel_Exception('An instance named \'' . $name . '\' was not found. Please verify the name.');
				}
				break;
			case 'definst':
			// Let's get the default instance if there's one
				if (static::$_default !== null)
				{
					$return = static::$_default;
				}
				else
				{
					// throw exception 'Ldap has no instances'
					throw new \Fuel_Exception('There is no default Ldap instance. Try using forge() first to create one.');
				}
				break;
			default:
			// throw exception 'We can't handle the $name given
				throw new \Fuel_Exception('The function can only take a string or an integer as input parameter. An input of type: \'' . gettype($name) . '\' was given.');
				break;
		}

		return $return;
	}

	/**
	 * Gets the instances array
	 */
	public final static function instances()
	{
		return static::$_instances;
	}

	/**
	 * Gets an array with all instance keys
	 */
	public final static function instance_keys()
	{
		$return = array_keys(static::$_instances);

		return $return;
	}

	/**
	 * Sets the instance's name
	 */
	private final function _set_name($name)
	{
		if (is_string($name) || is_numeric($name))
		{
			$this->_name = $name;
		}
	}

	/**
	 * Gets the instance's name
	 */
	public function get_name()
	{
		return $this->_name;
	}

	/**
	 * Sets the instance's config. Be warned that this action _clears up the Ldap
	 * instance disconnecting un unbinding the connection
	 */
	public function set_config($config = array())
	{
		$this->_clear(true);
		$this->_config = static::parse_config($config, true);
	}

	/**
	 * Gets the current instance's config array or a config value
	 */
	public function get_config($key = '')
	{
		$return = null;
		if (is_string($key) && $key != '')
		{
			$return = ((isset($this->_config[$key])) ? $this->_config[$key] : $return);
		}
		else
		{
			$return = $this->_config;
		}
		return $return;
	}

	/**
	 * Gets the current instance's connection
	 */
	public function get_connection()
	{
		return $this->_connection;
	}

	/**
	 * Gets the base dn
	 */
	public function get_base_dn()
	{
		return $this->_base_dn;
	}

	/**
	 * Connect to Ldap
	 */
	public function connect($chain = false)
	{
		$return = false;

		// Aren't we alerady connected?
		if (!$this->is_connected())
		{
			// Get some configuration values needed to connect
			$domain_controller = trim($this->_random_domain_controller());
			if ($domain_controller !== '')
			{
				$host = $domain_controller;
				if ($this->_config->get_item('connection.ssl') == true)
				{
					$host = "ldaps://" . $host;
				}

				$port = $this->_config->get_item('connection.port');

				// Connect to that damn Ldap!
				$this->_connection = @ldap_connect($host, $port);
				if ($return = $this->is_connected())
				{
					// Set some ldap options for correct communication
					ldap_set_option($this->_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
					ldap_set_option($this->_connection, LDAP_OPT_REFERRALS, 0);

					// Start TLS if configured
					if ($this->_config->get_item('connection.tls') == true)
					{
						ldap_start_tls($this->_connection);
					}
				}
			}
			else
			{
				throw new \Fuel_Exception('Cannot connect: There are no domain controllers to connect to. Please check your configuration.');
			}
		}
		else
		{
			// We are already connected!
			$return = true;
		}

		// if we want to chain methods then set the response to this instance
		$return = (($chain) ? $this : $return);

		return $return;
	}

	/**
	 * Bind to Ldap anonymously or by using the master credentials in configuration
	 */
	public function bind($anonymous = false, $chain = false)
	{
		$return = false;

		// Are we connected? If not, then try to ensure we have a connection by using the
		// try_connect parameter
		if ($this->is_connected(true))
		{
			if ($anonymous)
			{
				// We don't need user and password as we are binding anonymously don't we?
				$master_user = '';
				$master_pwd = '';
			}
			else
			{
				// Prevent anonymous binding by checking if a username and password has been set
				if (!isset($this->_config['connection']['master_user']) || trim($this->_config['connection']['master_user'] == '') || !isset($this->_config['connection']['master_pwd']) || trim($this->_config['connection']['master_pwd']) == '')
				{
					return $return;
				}
				else
				{
					$master_user = static::full_qualified_id($this->_config['connection']['master_user'], $this->_config['domain_suffix']);
					$master_pwd = $this->_config['connection']['master_pwd'];
				}
			}

			// Bind the damn thing to Ldap!
			$this->_binded = @ldap_bind($this->_connection, $master_user, $master_pwd);
			if ($this->_binded)
			{
				// Succesful binding! Let's try to get the Base DN!
				$this->_base_dn = $this->_find_base_dn();
				if ($this->_base_dn != '')
				{
					$return = true;
				}
			}
		}
		else
		{
			throw new \Fuel_Exception('Cannot bind: there is no connection to LDAP server.');
		}

		// if we want to chain methods then set the response to this instance
		$return = (($chain) ? $this : $return);

		return $return;
	}

	/**
	 * Bind to Ldap with the given credentials
	 */
	public function bind_credentials($username_or_email, $password, $rebind_as_master = true)
	{
		$return = false;

		// Are we connected? If not, then try to ensure we have a connection by using the
		// try_connect parameter
		if ($this->is_connected(true))
		{
			// Prevent anonymous binding by checking if a username and password has been set
			$full_id = static::full_qualified_id($username_or_email, $this->_config['domain_suffix']);
			$password = (is_string($password) ? trim($password) : '');
			if ($full_id != '' && $password != '')
			{
				// Bind the damn thing to Ldap with credentials!
				$return = $this->_binded = @ldap_bind($this->_connection, $full_id, $password);
				if ($this->_binded)
				{
					// Succesful binding! Let's try to get the Base DN!
					$this->_base_dn = $this->_find_base_dn();
				}

				if ($rebind_as_master)
				{
					//  Try to rebind as master. There's no way to know if re-binding was correct
					// using the return value. Use $ldap->is_binded() after function call for that
					$this->bind();
				}
			}
		}
		else
		{
			throw new \Fuel_Exception('Cannot bind: there is no connection to LDAP server.');
		}

		return $return;
	}

	public function unbind()
	{
		// if Ldap is binded try to unbind it before so we free resources
		if ($this->_binded)
		{
			try
			{
				$this->_binded = @ldap_unbind($this->_connection);
			}
			catch(Exception $e)
			{
				// Do nothing here. This catch is left blank on purpose
			}
		}

		return !$this->_binded;
	}

	/**
	 * Generates an Ldap_Query to be executed in the Ldap server
	 */
	public function query($filter = '')
	{
		$return = Ldap_Query::forge($this);
		$return->set_filter($filter);

		return $return;
	}

	/**
	 * Creates an Auth instance. The Auth package must me present. This function will
	 * try to autoload it if is not already loaded.
	 */
	public function auth()
	{
		// Try to load Auth package if is not loaded
		\Fuel::add_package('auth');

		// Create the Auth instance for this Ldap
		$return = \Auth::forge(array('driver' => 'Ldap\LdapAuth', 'id' => $this->get_name(), 'ldap' => $this));

		return $return;
	}

	/**
	 * Disconnects (and unbinds) the Ldap connection.
	 * This is just an alias to the function _clear (which handles our unbinding and
	 * variable _clearup)
	 */
	public function disconnect()
	{
		$this->_clear();
	}

	/**
	 * Returns a randomized domain controller from config
	 */
	private function _random_domain_controller()
	{
		$return = null;

		// Do not use property directly, it behaves randomly
		$domain_controllers = $this->_config->domain_controllers;
		if (!empty($domain_controllers))
		{
			$return = $domain_controllers[array_rand($domain_controllers)];
		}

		return $return;
	}

	/**
	 * Checks if we are connected to Ldap (Note: it is not the same as binded!)
	 */
	public function is_connected($try_connect = false)
	{
		$return = false;

		if (isset($this->_connection) && is_resource($this->_connection) && get_resource_type($this->_connection) == self::LDAP_RESOURCE_LINK)
		{
			$return = true;
		}
		else if ($try_connect)
		{
			$return = $this->connect();
		}

		return $return;
	}

	/**
	 * Checks if we are binded to Ldap (Note: it is not the same as just connected!)
	 */
	public function is_binded($try_bind = false, $anonymous = false)
	{
		$return = false;

		if (isset($this->_binded) && $this->_binded == true)
		{
			$return = true;
		}
		else if ($try_bind)
		{
			$return = $this->bind($anonymous);
		}

		return $return;
	}

	/**
	 * Checks if there's an Ldap error
	 */
	public function has_error()
	{
		$return = false;

		if (@ldap_errno($this->_connection) !== 0)
		{
			$return = true;
		}

		return $return;
	}

	/**
	 * Gets the last Ldap error
	 */
	public function get_error()
	{
		$return = null;

		if ($this->is_connected())
		{
			$return['number'] = ldap_errno($this->_connection);
			$return['message'] = ldap_error($this->_connection);
		}

		return $return;
	}

	/**
	 * Tries to find the base dn of a binding
	 */
	private function _find_base_dn()
	{
		$return = '';

		// Get the naming contexts!
		$namingContexts = $this->_get_root_dse(array('defaultnamingcontext'));
		if ($namingContexts === null || !isset($namingContexts[0]['defaultnamingcontext'][0]))
		{
			// Let's try that again but with different parameters (Differences between
			// Windows and other Ldap sources)
			$namingContexts = $this->_get_root_dse(array('namingcontexts'));
			if ($namingContexts !== null)
			{
				// That was our last chance so if we don't have a naming context then it's an
				// empty string and that's that!
				if (isset($namingContexts[0]['namingcontexts'][0]))
				{
					$return = $namingContexts[0]['namingcontexts'][0];
				}
				else
				{
					$return = '';
				}
			}
			else
			{
				throw new \Fuel_Exception('Cannot find base dn.');
			}
		}
		else
		{
			$return = $namingContexts[0]['defaultnamingcontext'][0];
		}

		return $return;
	}

	/**
	 * Tries to get the root dse (naming context)
	 */
	private function _get_root_dse($attributes = array("*", "+"))
	{
		$return = null;

		if ($this->is_connected())
		{
			$sr = @ldap_read($this->_connection, null, 'objectClass=*', $attributes);
			if (is_resource($sr) && get_resource_type($sr) == self::LDAP_RESOURCE_RESULT)
			{
				$return = @ldap_get_entries($this->_connection, $sr);
			}
		}

		return $return;
	}

	public static function full_qualified_id($username_or_email, $domain_suffix = '')
	{
		$return = trim($username_or_email);
		$use_domain_suffix = false;

		// is the username_or_email a string and is not empty?
		// consider as empty also a string with only n whitespaces
		if (is_string($return) && $return != '')
		{
			// Is there an @ in the username_or_email string?
			if (($pos = strpos($return, '@')) !== false)
			{
				// We have a composited id with an arbitrary suffix
				// separate the username_or_email string into id and suffix
				$id = trim(substr($return, 0, $pos));
				$suffix = trim(substr($return, $pos + 1));

				// is the id part empty?
				if ($id != '')
				{
					// set the response to the id part and begin assembling the full qualified id
					$return = $id;

					// is the suffix part empty?
					if ($suffix != '')
					{
						// does the suffix part include another @?
						if (strpos($suffix, '@') === false)
						{
							// we can safely build the rest of the full qualified id
							$return .= '@' . $suffix;
						}
						else
						{
							throw new \Fuel_Exception('There can only be one @ in the username_or_email string');
						}
					}
					else
					{
						$use_domain_suffix = true;
					}
				}
				else
				{
					throw new \Fuel_Exception('The id part of the username_or_email string (usually the substring that is before the @) cannot be an empty string');
				}
			}
			else
			{
				$use_domain_suffix = true;
			}
		}
		else
		{
			throw new \Fuel_Exception('The username_or_email parameter must be a non-empty string');
		}

		// Let's use the domain_suffix part as needed if it's a non-empty string
		if ($use_domain_suffix && is_string($domain_suffix) && $domain_suffix != '')
		{
			// does the domain_suffix part include an @ or not?
			if (($pos = strpos($domain_suffix, '@') === false))
			{
				// the domain_suffix part does not include an @ so it's safely to build the rest
				// of the full qualified id
				$return .= '@' . $domain_suffix;
			}
			else if ($pos == 0)
			{
				// The domain_suffix includes an @ at the beginning, see if it's the only @
				if (strpos($domain_suffix, '@', 1) === false)
				{
					// there's only one @ in the domain_suffix and it's at the beginning so it's safe
					// to build the rest of the full qualified id
					$return .= $domain_suffix;
				}
				else
				{
					// throw exception
					throw new \Fuel_Exception('There can only be one @ in the domain_suffix string');
				}
			}
			else
			{
				// throw exception
				throw new \Fuel_Exception('The domain_suffix string is incorrect. If there\'s an @ in the string, it can only be at the beginning');
			}
		}

		return $return;
	}

	public static function get_username_id_part($username_or_email)
	{
		$return = trim($username_or_email);

		// is the username_or_email a string and is not empty?
		// consider as empty also a string with only n whitespaces
		if (is_string($return) && $return != '')
		{
			if (($pos = strpos($return, '@')) !== false)
			{
				// Get the id part of username_or_email
				$id = trim(substr($return, 0, $pos));

				// is the id part empty?
				if ($id != '')
				{
					$return = $id;
				}
				else
				{
					throw new \Fuel_Exception('The id part of the username_or_email string (usually the substring that is before the @) cannot be an empty string');
				}
			}
		}
		else
		{
			throw new \Fuel_Exception('The username_or_email parameter must be a non-empty string');
		}

		return $return;
	}

}

/* End of file ldap.php */
