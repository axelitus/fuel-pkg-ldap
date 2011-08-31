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
class Ldap
{
	/**
	 * Some useful constants
	 */
	const LDAP_FOLDER = 'OU';
	const LDAP_CONTAINER = 'CN';
	const DEFAULT_PORT = 389;
	const DEFAULT_SSL_PORT = 636;
	const LDAP_RESOURCE_LINK = 'ldap link';
	const LDAP_RESOURCE_RESULT = 'ldap result';

	/**
	 * @var Array contains references if multiple were loaded
	 */
	protected static $_instances = array();

	/**
	 * @var Array contains the instance configuration
	 * TODO: Migrate all config settings to an apropriate Class
	 */
	protected $_config = array();

	/**
	 * @var String|Integer contains the name of the instance (string or numeric
	 * index)
	 */
	protected $_name = '';

	/**
	 * @var Resource contains the LDAP link identifier when connected
	 */
	protected $_connection = null;

	/**
	 * @var Bool contains a flag whether the it's binded to Ldap or not
	 */
	protected $_binded = false;

	/**
	 * @var String contains the Base DN of the Ldap binding
	 */
	protected $_base_dn = '';

	/**
	 * Prevent direct instantiation
	 */
	final private function __construct()
	{
	}

	/**
	 * Detects if Ldap is supported or not
	 */
	public static function ldap_supported()
	{
		$response = false;
		if (function_exists('ldap_connect'))
		{
			$response = true;
		}
		return $response;
	}

	/**
	 * Forges a new Ldap instance
	 * @return String|Integer|Null
	 */
	final public static function forge($config = array(), $override = false)
	{
		$response = null;

		// let's see if Ldap is even supported, if not why bother?
		if (static::ldap_supported())
		{
			// Named or non-named instance? That is the question
			if (is_string($config) && $config != '')
			{
				// we were given only the name of the instance
				$name = $config;

				static::$_instances[$name] = new Ldap();

				// set the $config array to an empty array for the _init process
				$config = array();

			}
			else if (is_array($config) && isset($config['name']))
			{
				// it's a named instance that we want
				$name = $config['name'];

				// get rid of the extra value in the config
				unset($config['name']);

				// Create the instance only if we can override it or it does not exist
				if ($override || !isset(static::$_instances[$id]))
				{
					static::$_instances[$name] = new Ldap();
				}
				else
				{
					// We could not override the instance, so return null
					return $response;
				}
			}
			else
			{
				// it's a non-named instance that we want
				static::$_instances[] = new Ldap();

				// as the items are inserted at the end, we just need to look for the last key
				// inserted to get the auto-numeric id it generated for the non-named instance
				$keys = static::get_instance_keys();
				$name = end($keys);
			}

			// set the Ldap instance name and initialize with the custom config
			static::$_instances[$name]->_init($name, $config);

			// Set the return value to the generated instance. If we want to know it's name
			// all
			// we have to do is call the get_name() function. Later on we can get the
			// instance
			// again by calling the get_instance() function.
			$response = static::$_instances[$name];
		}
		else
		{
			// Oh my! We don't have Ldap support. What a bummer!
			throw new \Fuel_Exception('LDAP is not supported.');
		}

		return $response;
	}

	/**
	 * Checks wetherthere's at least one instance
	 */
	public static function has_instances()
	{
		$response = false;

		if (!empty(static::$_instances))
		{
			$response = true;
		}

		return $response;
	}

	public static function has_instance($name)
	{
		$response = false;

		if (static::has_instances())
		{
			if ((is_string($name) && $name != '') || (is_numeric($name) && $name >= 0))
			{
				$response = isset(static::$_instances[$name]);
			}
		}

		return $response;
	}

	/**
	 * Gets an instance by name (or numeric id) or the default instance (the instance
	 * that's first in the array. Note: be careful as to how PHP manages array, it
	 * does not order them by keys, they are just hash tables)
	 */
	public static function get_instance($name = '')
	{
		$response = null;

		if (static::has_instance($name))
		{
			$response = static::$_instances[$name];
		}
		else
		{
			// Let's get the default instance
			$keys = static::get_instance_keys();
			$name = reset($keys);
			$response = static::$_instances[$name];
		}

		return $response;
	}

	/**
	 * Gets an array with all instance keys
	 */
	public static function get_instance_keys()
	{
		$response = array_keys(static::$_instances);

		return $response;
	}

	/**
	 * Initializes an instances of Ldap. Be warned: use it wiht care and at best only
	 * on new Ldap instances or you will lose whatever you had in that instance.
	 */
	final private function _init($name, $config = array())
	{
		// this sets the instance to a fresh one
		$this->clean(true);

		// set the instance name
		$this->set_name($name);

		// let's see if we have a config given or we just need to use our own
		$config = (is_array($config) && !empty($config)) ? $config : \Config::load('ldap', true);

		// check and cleanup the config array given using secure mode
		$this->_config = static::parse_config($config);
	}

	/**
	 * Cleans the instance (this means that it unbinds from Ldap server and kills the
	 * connection). The instance name is the only thing that gets not cleaned.
	 * This is by design, as it could break the functionality.
	 */
	final private function clean($clean_config = false)
	{
		// if Ldap is binded try to unbind it before so we free resources
		if ($this->_binded)
		{
			try
			{
				@ldap_unbind($this->_connection);
			}
			catch(Exception $e)
			{
				// Do nothing here. This catch is left blank on purpose
			}
		}

		$this->_connection = null;
		$this->_binded = false;
		$this->_base_dn = '';
		if ($clean_config)
		{
			$this->_config = array();
		}
	}

	/**
	 * Parses a config array. There are two modes: secure and not secure.
	 * - Secure mode: it looks for the values using the proper keys and sees if they
	 * are of the proper type.
	 * - Non secure mode: it copies the config array. (for now)
	 *
	 * Either way it sets the proper key's values to an acceptable given value or a
	 * default value (which may not work).
	 */
	final public static function parse_config($config, $secure = true)
	{
		$response = array();

		// Do we have a proper array to parse?
		if (is_array($config))
		{
			// Which mode are we running? secure or non-secure?
			if ($secure)
			{
				// check domain_controllers
				$response['domain_controllers'] = array();
				if (isset($config['domain_controllers']) && is_array($config['domain_controllers']))
				{
					foreach ($config['domain_controllers'] as $key => $value)
					{
						if (is_string($value))
						{
							$response['domain_controllers'][] = $value;
						}
					}
				}

				// check domain_suffix
				$response['domain_suffix'] = ((isset($config['domain_suffix']) && is_string($config['domain_suffix'])) ? $config['domain_suffix'] : '');

				// check connection details
				$response['connection'] = array();
				if (isset($config['connection']) && is_array($config['connection']))
				{
					// check use_ssl
					$response['connection']['use_ssl'] = ((isset($config['connection']['use_ssl']) && is_bool($config['connection']['use_ssl'])) ? $config['connection']['use_ssl'] : false);

					// check use_tls
					$response['connection']['use_tls'] = ((isset($config['connection']['use_tls']) && is_bool($config['connection']['use_tls'])) ? $config['connection']['use_tls'] : false);

					// check port
					$response['connection']['port'] = ((isset($config['connection']['port']) && is_numeric($config['connection']['port']) && $config['connection']['port'] >= 0 && $config['connection']['port'] <= 65535) ? $config['connection']['port'] : (($config['connection']['use_ssl']) ? self::DEFAULT_SSL_PORT : self::DEFAULT_PORT));

					// check master_user
					$response['connection']['master_user'] = ((isset($config['connection']['master_user']) && is_string($config['connection']['master_user'])) ? $config['connection']['master_user'] : '');

					// check master_pwd
					$response['connection']['master_pwd'] = ((isset($config['connection']['master_pwd']) && is_string($config['connection']['master_pwd'])) ? $config['connection']['master_pwd'] : '');
				}
			}
			else
			{
				// TODO: check for the values using proper the keys but omitting for types
				$response = $config;
			}
		}
		else
		{
			// Initialize a default array with default values as we were not given a config
			// array we can parse.
			// TODO: decide if we better throw an Exception as this would be a failed
			// function run. In the meantime this is ok.
			$response = array('domain_controllers' => array(), 'domain_suffix' => '', 'connection' => array('port' => self::DEFAULT_PORT, 'master_user' => '', 'master_pwd' => '', 'use_ssl' => false, 'use_tls' => false));
		}

		return $response;
	}

	/**
	 * Sets the instance's name
	 */
	private final function set_name($name)
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
	 * Sets the instance's config. Be warned that this action cleans up the LDap
	 * instance disconnecting un unbinding the connection
	 */
	public function set_config($config = array())
	{
		$this->clean(true);
		$this->_config = static::parse_config($config, true);
	}

	/**
	 * Gets the current instance's config array or a config value
	 */
	public function get_config($key = '')
	{
		$response = null;
		if (is_string($key) && $key != '')
		{
			$response = ((isset($this->_config[$key])) ? $this->_config[$key] : $response);
		}
		else
		{
			$response = $this->_config;
		}
		return $response;
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
		$response = false;

		// Aren't we alerady connected?
		if (!$this->is_connected())
		{
			// Get some configuration values needed to connect
			$domain_controller = trim($this->_random_domain_controller());
			if ($domain_controller !== '')
			{
				$host = $domain_controller;
				if (isset($this->_config['connection']['use_ssl']) && $this->_config['connection']['use_ssl'] == true)
				{
					$host = "ldaps://" . $host;
					$port = ((isset($this->_config['connection']['port'])) ? $this->_config['connection']['port'] : self::DEFAULT_SSL_PORT);
				}
				else
				{
					$port = (($this->_config['connection']['port']) ? $this->_config['connection']['port'] : self::DEFAULT_PORT);
				}

				// Connect to that damn Ldap!
				$this->_connection = @ldap_connect($host, $port);
				if ($response = $this->is_connected())
				{
					// Set some ldap options for correct communication
					ldap_set_option($this->_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
					ldap_set_option($this->_connection, LDAP_OPT_REFERRALS, 0);

					// Start TLS if configured
					if (isset($this->_config['connection']['use_tls']) && $this->_config['connection']['use_tls'] == true)
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
			$response = true;
		}

		// if we want to chain methods then set the response to this instance
		$response = (($chain) ? $this : $response);

		return $response;
	}

	/**
	 * Bind to Ldap
	 */
	public function bind($anonymous = false, $chain = false)
	{
		$response = false;

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
					return $response;
				}
				else
				{
					$domain_suffix = (isset($this->_config['domain_suffix']) ? '@' . $this->_config['domain_suffix'] : '');
					$master_user = $this->_config['connection']['master_user'] . $domain_suffix;
					$master_pwd = $this->_config['connection']['master_pwd'];
				}
			}

			// Bind the damn thing to Ldap!
			$this->_binded = @ldap_bind($this->_connection, $master_user, $master_pwd);
			if ($this->_binded)
			{
				// Succesful binding! Let's try to get the Base DN!
				$this->_base_dn = $this->_find_base_dn();
				if ($this->_base_dn !== '')
				{
					$response = true;
				}
			}
		}
		else
		{
			throw new \Fuel_Exception('Cannot bind: there is no connection to LDAP server.');
		}

		// if we want to chain methods then set the response to this instance
		$response = (($chain) ? $this : $response);

		return $response;
	}

	/**
	 * Generates an Ldap_Query to be executed in the Ldap server
	 */
	public function query($filter = '')
	{
		$response = Ldap_Query::forge($this);
		$response->set_filter($filter);

		return $response;
	}

	/**
	 * Disconnects (and unbinds) the Ldap connection.
	 * This is just an alias to the function clean (which handles our unbinding and
	 * variable cleanup)
	 */
	public function disconnect()
	{
		$this->clean();
	}

	/**
	 * Returns a randomized domain controller from config
	 */
	private function _random_domain_controller()
	{
		$response = null;
		if (isset($this->_config['domain_controllers']) && is_array($this->_config['domain_controllers']) && !empty($this->_config['domain_controllers']))
		{
			$response = $this->_config['domain_controllers'][array_rand($this->_config['domain_controllers'])];
		}
		return $response;
	}

	/**
	 * Checks if we are connected to Ldap (Note: it is not the same as binded!)
	 */
	public function is_connected($try_connect = false)
	{
		$response = false;

		if (isset($this->_connection) && is_resource($this->_connection) && get_resource_type($this->_connection) == self::LDAP_RESOURCE_LINK)
		{
			$response = true;
		}
		else if ($try_connect)
		{
			$response = $this->connect();
		}

		return $response;
	}

	/**
	 * Checks if we are binded to Ldap (Note: it is not the same as just connected!)
	 */
	public function is_binded($try_bind = false, $anonymous = false)
	{
		$response = false;

		if (isset($this->_binded) && $this->_binded == true)
		{
			$response = true;
		}
		else if ($try_bind)
		{
			$response = $this->bind($anonymous);
		}

		return $response;
	}

	/**
	 * Checks if there's an Ldap error
	 */
	public function has_error()
	{
		$response = false;

		if (@ldap_errno($this->_connection) !== 0)
		{
			$response = true;
		}

		return $response;
	}

	/**
	 * Gets the last Ldap error
	 */
	public function get_error()
	{
		$response = null;

		if ($this->is_connected())
		{
			$response['number'] = ldap_errno($this->_connection);
			$response['message'] = ldap_error($this->_connection);
		}

		return $response;
	}

	/**
	 * Tries to find the base dn of a binding
	 */
	private function _find_base_dn()
	{
		$response = '';

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
					$response = $namingContexts[0]['namingcontexts'][0];
				}
				else
				{
					$response = '';
				}
			}
			else
			{
				throw new \Fuel_Exception('Cannot find base dn.');
			}
		}
		else
		{
			$response = $namingContexts[0]['defaultnamingcontext'][0];
		}

		return $response;
	}

	/**
	 * Tries to get the root dse (naming context)
	 */
	private function _get_root_dse($attributes = array("*", "+"))
	{
		$response = null;

		if ($this->is_connected())
		{
			$sr = @ldap_read($this->_connection, null, 'objectClass=*', $attributes);
			if (is_resource($sr) && get_resource_type($sr) == self::LDAP_RESOURCE_RESULT)
			{
				$response = @ldap_get_entries($this->_connection, $sr);
			}
		}

		return $response;
	}

}

/* End of file ldap.php */
